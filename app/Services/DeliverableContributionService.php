<?php

namespace App\Services;

use App\Models\Activity;
use App\Models\Agreement;
use App\Models\AgreementActivityHistory;
use App\Models\DeliverableContribution;
use App\Models\AgreementDeliverable;
use App\Support\ActivityTypeDuration;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

class DeliverableContributionService
{
    public function syncForAgreement(Agreement $agreement): void
    {
        $agreement->loadMissing([
            'agreementActivityHistories',
            'deliverables.program',
            'deliverables.activityType',
            'deliverables.users',
            'deliverables.teams',
        ]);

        DeliverableContribution::query()
            ->where('agreement_id', $agreement->id)
            ->delete();

        if ($agreement->deliverables->isEmpty() || $agreement->agreementActivityHistories->isEmpty()) {
            return;
        }

        $rows = [];
        foreach ($agreement->deliverables as $deliverable) {
            if ($deliverable->retired_at) {
                continue;
            }

            $rows = array_merge(
                $rows,
                $this->buildDeliverableContributionRows(
                    $agreement->agreementActivityHistories,
                    $agreement->id,
                    $deliverable
                )
            );
        }

        if (!empty($rows)) {
            DeliverableContribution::query()->insert($rows);
        }
    }

    public function syncForActivity(Activity $activity): void
    {
        $activity->loadMissing([
            'activityType',
            'contactTime',
            'participantTimes',
            'participants',
            'programs',
            'agreements.teams.users',
            'agreements.deliverables.program',
            'agreements.deliverables.activityType',
            'agreements.deliverables.users',
            'agreements.deliverables.teams',
        ]);

        $this->syncHistoryForActivity($activity);

        DeliverableContribution::query()
            ->where('activity_id', $activity->id)
            ->delete();

        $contactFamilyId = $activity->activityType?->contact_family_id;
        if (!$contactFamilyId) {
            return;
        }

        $historyByAgreement = $activity->agreementActivityHistories
            ->groupBy(fn (AgreementActivityHistory $history) => (int) $history->agreement_id);

        $rows = [];

        foreach ($activity->agreements as $agreement) {
            $agreementHistory = $historyByAgreement->get((int) $agreement->id, collect());

            foreach ($agreement->deliverables as $deliverable) {
                if ($deliverable->retired_at || (int) $deliverable->contact_family_id !== (int) $contactFamilyId) {
                    continue;
                }

                $rows = array_merge(
                    $rows,
                    $this->buildDeliverableContributionRows(
                        $agreementHistory,
                        $agreement->id,
                        $deliverable
                    )
                );
            }
        }

        if (!empty($rows)) {
            DeliverableContribution::query()->insert($rows);
        }
    }

    private function syncHistoryForActivity(Activity $activity): void
    {
        $existingTeamSnapshotsByAgreementUser = AgreementActivityHistory::query()
            ->where('activity_id', $activity->id)
            ->whereNotNull('contributor_user_id')
            ->get()
            ->mapWithKeys(fn (AgreementActivityHistory $history) => [
                $history->agreement_id . ':' . $history->contributor_user_id => $history->team_ids_snapshot,
            ]);

        AgreementActivityHistory::query()
            ->where('activity_id', $activity->id)
            ->delete();

        $contactFamilyId = $activity->activityType?->contact_family_id;
        if (!$contactFamilyId) {
            return;
        }

        $programIds = $activity->programs
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->values()
            ->all();

        $participantTimesByUser = $activity->participantTimes
            ->filter(fn ($time) => $time->user_id)
            ->keyBy(fn ($time) => (int) $time->user_id);

        $completionCount = max(1, (int) ($activity->completion_count ?? 1));
        $duration = ActivityTypeDuration::fromActivity($activity);
        $allottedTotals = $duration->totalForCompletionCount($completionCount);
        $hasAllottedTime = $duration->hasDuration();

        $rows = [];

        foreach ($activity->agreements as $agreement) {
            $agreementTeamIdsByUser = $agreement->teams
                ->flatMap(function ($team) {
                    return $team->users->map(fn ($user) => [
                        'user_id' => (int) $user->id,
                        'team_id' => (int) $team->id,
                    ]);
                })
                ->groupBy('user_id')
                ->map(fn ($entries) => $entries->pluck('team_id')->unique()->values()->all());

            // these rows can be flattened if refactor to remove contribution_kind on activity history
            // not sure why this would need to be on table, it can be removed and place all vals into one row
            // row for contact completion
            $rows[] = $this->buildHistoryRow(
                $agreement->id,
                $activity,
                $contactFamilyId,
                $programIds,
                null,
                null,
                'completion',
                $completionCount,
                null,
                null,
                null
            );

            // row for contactallotted time
            if ($hasAllottedTime) {
                $rows[] = $this->buildHistoryRow(
                    $agreement->id,
                    $activity,
                    $contactFamilyId,
                    $programIds,
                    null,
                    null,
                    'allotted_time',
                    null,
                    null,
                    $allottedTotals['allotted_hours'],
                    $allottedTotals['allotted_days']
                );
            }

            // row for contact time
            if ($activity->contactTime && (float) $activity->contactTime->activity_hours > 0) {
                $rows[] = $this->buildHistoryRow(
                    $agreement->id,
                    $activity,
                    $contactFamilyId,
                    $programIds,
                    null,
                    null,
                    'time',
                    null,
                    (float) $activity->contactTime->activity_hours,
                    null,
                    null,
                    (float) $activity->contactTime->prep_hours,
                    (float) $activity->contactTime->follow_up_hours
                );
            }

            foreach ($activity->participants as $participant) {
                $userId = (int) $participant->id;
                $snapshotKey = $agreement->id . ':' . $userId;
                $teamIdsSnapshot = $existingTeamSnapshotsByAgreementUser->has($snapshotKey)
                    ? $existingTeamSnapshotsByAgreementUser->get($snapshotKey)
                    : $agreementTeamIdsByUser->get($userId, []);

                // row for participant completion
                $rows[] = $this->buildHistoryRow(
                    $agreement->id,
                    $activity,
                    $contactFamilyId,
                    $programIds,
                    $userId,
                    $teamIdsSnapshot,
                    'completion',
                    $completionCount,
                    null,
                    null,
                    null
                );

                // row for participant allotted time
                if ($hasAllottedTime) {
                    $rows[] = $this->buildHistoryRow(
                        $agreement->id,
                        $activity,
                        $contactFamilyId,
                        $programIds,
                        $userId,
                        $teamIdsSnapshot,
                        'allotted_time',
                        null,
                        null,
                        $allottedTotals['allotted_hours'],
                        $allottedTotals['allotted_days']
                    );
                }

                $participantTime = $participantTimesByUser->get($userId);
                if (!$participantTime || (float) $participantTime->hours <= 0) {
                    continue;
                }

                // row for participant observed time
                $rows[] = $this->buildHistoryRow(
                    $agreement->id,
                    $activity,
                    $contactFamilyId,
                    $programIds,
                    $userId,
                    $teamIdsSnapshot,
                    'time',
                    null,
                    (float) $participantTime->hours,
                    null,
                    null,
                    (float) $participantTime->prep_hours,
                    (float) $participantTime->follow_up_hours
                );
            }
        }

        if (!empty($rows)) {
            $rows = array_map(function (array $row) {
                $row['program_ids_snapshot'] = $row['program_ids_snapshot'] !== null
                    ? json_encode($row['program_ids_snapshot'])
                    : null;
                $row['team_ids_snapshot'] = $row['team_ids_snapshot'] !== null
                    ? json_encode($row['team_ids_snapshot'])
                    : null;

                return $row;
            }, $rows);

            AgreementActivityHistory::query()->insert($rows);
            $activity->unsetRelation('agreementActivityHistories');
            $activity->load('agreementActivityHistories');
        }
    }

    /**
     * @param array<int, int>|null $teamIdsSnapshot
     */
    private function buildHistoryRow(
        int $agreementId,
        Activity $activity,
        int $contactFamilyId,
        array $programIds,
        ?int $contributorUserId,
        ?array $teamIdsSnapshot,
        string $contributionKind,
        ?int $completionUnits,
        ?float $activityHours,
        ?float $allottedHours,
        ?float $allottedDays,
        float $prepHours = 0,
        float $followUpHours = 0
    ): array {
        return [
            'agreement_id' => $agreementId,
            'activity_id' => $activity->id,
            'contact_family_id' => $contactFamilyId,
            'activity_type_id' => $activity->activity_type_id,
            'contributor_user_id' => $contributorUserId,
            'activity_date' => $activity->engagement_date,
            'contribution_kind' => $contributionKind,
            'completion_units' => $completionUnits,
            'activity_hours' => $activityHours,
            'prep_hours' => $prepHours,
            'follow_up_hours' => $followUpHours,
            'allotted_hours' => $allottedHours,
            'allotted_days' => $allottedDays,
            'program_ids_snapshot' => $programIds,
            'team_ids_snapshot' => $teamIdsSnapshot,
            'cancelled' => (bool) $activity->cancelled,
            'not_yet_complete' => (bool) $activity->not_yet_complete,
            'created_at' => now(),
            'updated_at' => now(),
        ];
    }

    /**
     * @param \Illuminate\Support\Collection<int, AgreementActivityHistory> $agreementHistory
     * @return array<int, array<string, mixed>>
     */
    private function buildDeliverableContributionRows(
        $agreementHistory,
        int $agreementId,
        AgreementDeliverable $deliverable
    ): array {
        $basis = $deliverable->contribution_basis;
        $metric = $deliverable->metric_type;
        $timeBasis = $metric === 'time' ? ($deliverable->time_basis ?? 'observed') : null;
        $includeAdditional = (bool) $deliverable->include_additional_time;
        $fingerprint = sha1(implode('|', [
            $deliverable->id,
            $metric,
            $timeBasis ?? '',
            $basis,
            $deliverable->user_grouping_mode,
            $includeAdditional ? '1' : '0',
        ]));

        $matchingHistory = $agreementHistory->filter(function (AgreementActivityHistory $history) use ($deliverable) {
            if ((int) $history->contact_family_id !== (int) $deliverable->contact_family_id) {
                return false;
            }

            if ($deliverable->activity_type_id && (int) $history->activity_type_id !== (int) $deliverable->activity_type_id) {
                return false;
            }

            if ($deliverable->program_id && !collect($history->program_ids_snapshot ?? [])->contains((int) $deliverable->program_id)) {
                return false;
            }

            return true;
        })->values();

        if ($basis === 'contact') {
            return $this->buildContactContributionRows(
                $matchingHistory,
                $agreementId,
                $deliverable->id,
                $metric,
                $timeBasis,
                $includeAdditional,
                $fingerprint
            );
        }

        return $this->buildUserContributionRows(
            $matchingHistory,
            $agreementId,
            $deliverable,
            $metric,
            $timeBasis,
            $includeAdditional,
            $fingerprint
        );
    }

    private function buildContactContributionRows(
        $matchingHistory,
        int $agreementId,
        int $deliverableId,
        string $metric,
        ?string $timeBasis,
        bool $includeAdditional,
        string $fingerprint
    ): array {
        // build contributions based on deliverable metric and basis requirements - not exact copies of history rows
        // history rows have everything, contributions are more specific
        // I LIED - history rows and contributions are pretty similar...
        // REFACTOR - activity history has a contribution_kind, so it is creeping on contributions in scope...
        if ($metric === 'completion') {
            $historyRows = $matchingHistory
                ->whereNull('contributor_user_id')
                ->where('contribution_kind', 'completion')
                ->values();

            return $historyRows->map(function (AgreementActivityHistory $history) use ($agreementId, $deliverableId, $fingerprint) {
                return [
                    'agreement_activity_history_id' => $history->id,
                    'agreement_deliverable_id' => $deliverableId,
                    'agreement_id' => $agreementId,
                    'activity_id' => $history->activity_id,
                    'contributor_user_id' => null,
                    'contribution_kind' => 'completion',
                    'source_assignment_type' => 'contact',
                    'counted_attribution_basis' => 'contact',
                    'credited_units' => $this->creditedCompletionUnits($history),
                    'credited_hours' => null,
                    'credited_allotted_hours' => null,
                    'credited_allotted_days' => null,
                    'prep_hours' => 0,
                    'follow_up_hours' => 0,
                    'rules_fingerprint' => $fingerprint,
                    'cancelled' => (bool) $history->cancelled,
                    'not_yet_complete' => (bool) $history->not_yet_complete,
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            })->all();
        }

        if ($timeBasis === 'allotted') {
            $historyRows = $matchingHistory
                ->whereNull('contributor_user_id')
                ->where('contribution_kind', 'allotted_time')
                ->values();

            return $historyRows->map(function (AgreementActivityHistory $history) use ($agreementId, $deliverableId, $fingerprint) {
                return [
                    'agreement_activity_history_id' => $history->id,
                    'agreement_deliverable_id' => $deliverableId,
                    'agreement_id' => $agreementId,
                    'activity_id' => $history->activity_id,
                    'contributor_user_id' => null,
                    'contribution_kind' => 'allotted_time',
                    'source_assignment_type' => 'contact',
                    'counted_attribution_basis' => 'contact',
                    'credited_units' => null,
                    'credited_hours' => null,
                    'credited_allotted_hours' => $history->allotted_hours !== null ? (float) $history->allotted_hours : null,
                    'credited_allotted_days' => $history->allotted_days !== null ? (float) $history->allotted_days : null,
                    'prep_hours' => 0,
                    'follow_up_hours' => 0,
                    'rules_fingerprint' => $fingerprint,
                    'cancelled' => (bool) $history->cancelled,
                    'not_yet_complete' => (bool) $history->not_yet_complete,
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            })->all();
        }

        $historyRows = $matchingHistory
            ->whereNull('contributor_user_id')
            ->where('contribution_kind', 'time')
            ->values();

        return $historyRows->map(function (AgreementActivityHistory $history) use ($agreementId, $deliverableId, $includeAdditional, $fingerprint) {
            return [
                'agreement_activity_history_id' => $history->id,
                'agreement_deliverable_id' => $deliverableId,
                'agreement_id' => $agreementId,
                'activity_id' => $history->activity_id,
                'contributor_user_id' => null,
                'contribution_kind' => 'time',
                'source_assignment_type' => 'contact',
                'counted_attribution_basis' => 'contact',
                'credited_units' => null,
                'credited_hours' => (float) ($history->activity_hours ?? 0)
                    + ($includeAdditional ? (float) $history->prep_hours + (float) $history->follow_up_hours : 0),
                'credited_allotted_hours' => null,
                'credited_allotted_days' => null,
                'prep_hours' => $includeAdditional ? (float) $history->prep_hours : 0,
                'follow_up_hours' => $includeAdditional ? (float) $history->follow_up_hours : 0,
                'rules_fingerprint' => $fingerprint,
                'cancelled' => (bool) $history->cancelled,
                'not_yet_complete' => (bool) $history->not_yet_complete,
                'created_at' => now(),
                'updated_at' => now(),
            ];
        })->all();
    }

    private function buildUserContributionRows(
        $matchingHistory,
        int $agreementId,
        AgreementDeliverable $deliverable,
        string $metric,
        ?string $timeBasis,
        bool $includeAdditional,
        string $fingerprint
    ): array {
        $eligibleUsersById = $deliverable->users
            ->keyBy(fn ($user) => (int) $user->id);
        $deliverableTeams = $deliverable->teams->keyBy(fn ($team) => (int) $team->id);

        $contributionKind = match (true) {
            $metric === 'completion' => 'completion',
            $timeBasis === 'allotted' => 'allotted_time',
            default => 'time',
        };

        $matchedHistoryRows = $matchingHistory
            ->whereNotNull('contributor_user_id')
            ->where('contribution_kind', $contributionKind)
            ->map(function (AgreementActivityHistory $history) use ($eligibleUsersById, $deliverableTeams) {
                $sourceAssignmentType = $this->resolveUserHistoryMatch(
                    $history,
                    $eligibleUsersById,
                    $deliverableTeams
                );

                if ($sourceAssignmentType === null) {
                    return null;
                }

                return [
                    'history' => $history,
                    'source_assignment_type' => $sourceAssignmentType,
                ];
            })
            ->filter()
            ->values();

        return $matchedHistoryRows->map(function (array $matchedRow) use (
            $agreementId,
            $deliverable,
            $metric,
            $timeBasis,
            $includeAdditional,
            $fingerprint,
            $contributionKind
        ) {
            /** @var AgreementActivityHistory $history */
            $history = $matchedRow['history'];
            $sourceAssignmentType = $matchedRow['source_assignment_type'];

            if ($metric === 'completion') {
                return [
                    'agreement_activity_history_id' => $history->id,
                    'agreement_deliverable_id' => $deliverable->id,
                    'agreement_id' => $agreementId,
                    'activity_id' => $history->activity_id,
                    'contributor_user_id' => $history->contributor_user_id,
                    'contribution_kind' => 'completion',
                    'source_assignment_type' => $sourceAssignmentType,
                    'counted_attribution_basis' => 'user',
                    'credited_units' => $this->creditedCompletionUnits($history),
                    'credited_hours' => null,
                    'credited_allotted_hours' => null,
                    'credited_allotted_days' => null,
                    'prep_hours' => 0,
                    'follow_up_hours' => 0,
                    'rules_fingerprint' => $fingerprint,
                    'cancelled' => (bool) $history->cancelled,
                    'not_yet_complete' => (bool) $history->not_yet_complete,
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            }

            if ($timeBasis === 'allotted') {
                return [
                    'agreement_activity_history_id' => $history->id,
                    'agreement_deliverable_id' => $deliverable->id,
                    'agreement_id' => $agreementId,
                    'activity_id' => $history->activity_id,
                    'contributor_user_id' => $history->contributor_user_id,
                    'contribution_kind' => 'allotted_time',
                    'source_assignment_type' => $sourceAssignmentType,
                    'counted_attribution_basis' => 'user',
                    'credited_units' => null,
                    'credited_hours' => null,
                    'credited_allotted_hours' => $history->allotted_hours !== null ? (float) $history->allotted_hours : null,
                    'credited_allotted_days' => $history->allotted_days !== null ? (float) $history->allotted_days : null,
                    'prep_hours' => 0,
                    'follow_up_hours' => 0,
                    'rules_fingerprint' => $fingerprint,
                    'cancelled' => (bool) $history->cancelled,
                    'not_yet_complete' => (bool) $history->not_yet_complete,
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            }

            return [
                'agreement_activity_history_id' => $history->id,
                'agreement_deliverable_id' => $deliverable->id,
                'agreement_id' => $agreementId,
                'activity_id' => $history->activity_id,
                'contributor_user_id' => $history->contributor_user_id,
                'contribution_kind' => 'time',
                'source_assignment_type' => $sourceAssignmentType,
                'counted_attribution_basis' => 'user',
                'credited_units' => null,
                'credited_hours' => (float) ($history->activity_hours ?? 0)
                    + ($includeAdditional ? (float) $history->prep_hours + (float) $history->follow_up_hours : 0),
                'credited_allotted_hours' => null,
                'credited_allotted_days' => null,
                'prep_hours' => $includeAdditional ? (float) $history->prep_hours : 0,
                'follow_up_hours' => $includeAdditional ? (float) $history->follow_up_hours : 0,
                'rules_fingerprint' => $fingerprint,
                'cancelled' => (bool) $history->cancelled,
                'not_yet_complete' => (bool) $history->not_yet_complete,
                'created_at' => now(),
                'updated_at' => now(),
            ];
        })->all();
    }

    private function creditedCompletionUnits(AgreementActivityHistory $history): float
    {
        if ($history->not_yet_complete) {
            return 0;
        }

        return (float) ($history->completion_units ?? 0);
    }

    private function resolveUserHistoryMatch(
        AgreementActivityHistory $history,
        Collection $eligibleUsersById,
        Collection $deliverableTeamsById
    ): ?string {
        $activityDate = CarbonImmutable::parse($history->activity_date)->startOfDay();
        $historyTeamIds = collect($history->team_ids_snapshot ?? [])
            ->map(fn ($id) => (int) $id)
            ->values();

        if ($deliverableTeamsById->isNotEmpty() && $historyTeamIds->isNotEmpty()) {
            foreach ($historyTeamIds as $teamId) {
                $team = $deliverableTeamsById->get($teamId);

                if (!$team) {
                    continue;
                }

                $assignedAt = $team->pivot->assigned_at
                    ? CarbonImmutable::parse($team->pivot->assigned_at)->startOfDay()
                    : null;
                $unassignedAt = $team->pivot->unassigned_at
                    ? CarbonImmutable::parse($team->pivot->unassigned_at)->startOfDay()
                    : null;

                if ((!$assignedAt || !$activityDate->lt($assignedAt)) && (!$unassignedAt || !$activityDate->gt($unassignedAt))) {
                    return 'team';
                }
            }
        }

        $user = $eligibleUsersById->get((int) $history->contributor_user_id);

        if ($user) {
            $assignedAt = $user->pivot->assigned_at
                ? CarbonImmutable::parse($user->pivot->assigned_at)->startOfDay()
                : null;
            $unassignedAt = $user->pivot->unassigned_at
                ? CarbonImmutable::parse($user->pivot->unassigned_at)->startOfDay()
                : null;

            if ((!$assignedAt || !$activityDate->lt($assignedAt)) && (!$unassignedAt || !$activityDate->gt($unassignedAt))) {
                return $user->pivot->source_team_id ? 'team' : 'user';
            }
        }

        return null;
    }
}
