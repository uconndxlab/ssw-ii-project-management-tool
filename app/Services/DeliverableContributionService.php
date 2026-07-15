<?php

namespace App\Services;

use App\Models\Activity;
use App\Models\Agreement;
use App\Models\AgreementActivityHistory;
use App\Models\DeliverableContribution;
use App\Models\AgreementDeliverable;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

class DeliverableContributionService
{
    public function syncForAgreement(Agreement $agreement): void
    {
        $agreement->loadMissing([
            'agreementActivityHistories',
            'deliverables.program',
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

            $rows[] = [
                'agreement_id' => $agreement->id,
                'activity_id' => $activity->id,
                'contact_family_id' => $contactFamilyId,
                'activity_type_id' => $activity->activity_type_id,
                'contributor_user_id' => null,
                'activity_date' => $activity->engagement_date,
                'contribution_kind' => 'completion',
                'completion_units' => 1,
                'activity_hours' => null,
                'prep_hours' => 0,
                'follow_up_hours' => 0,
                'program_ids_snapshot' => $programIds,
                'team_ids_snapshot' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ];

            if ($activity->contactTime && (float) $activity->contactTime->activity_hours > 0) {
                $rows[] = [
                    'agreement_id' => $agreement->id,
                    'activity_id' => $activity->id,
                    'contact_family_id' => $contactFamilyId,
                    'activity_type_id' => $activity->activity_type_id,
                    'contributor_user_id' => null,
                    'activity_date' => $activity->engagement_date,
                    'contribution_kind' => 'time',
                    'completion_units' => null,
                    'activity_hours' => (float) $activity->contactTime->activity_hours,
                    'prep_hours' => (float) $activity->contactTime->prep_hours,
                    'follow_up_hours' => (float) $activity->contactTime->follow_up_hours,
                    'program_ids_snapshot' => $programIds,
                    'team_ids_snapshot' => null,
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            }

            foreach ($activity->participants as $participant) {
                $userId = (int) $participant->id;

                $rows[] = [
                    'agreement_id' => $agreement->id,
                    'activity_id' => $activity->id,
                    'contact_family_id' => $contactFamilyId,
                    'activity_type_id' => $activity->activity_type_id,
                    'contributor_user_id' => $userId,
                    'activity_date' => $activity->engagement_date,
                    'contribution_kind' => 'completion',
                    'completion_units' => 1,
                    'activity_hours' => null,
                    'prep_hours' => 0,
                    'follow_up_hours' => 0,
                    'program_ids_snapshot' => $programIds,
                    'team_ids_snapshot' => $agreementTeamIdsByUser->get($userId, []),
                    'created_at' => now(),
                    'updated_at' => now(),
                ];

                $participantTime = $participantTimesByUser->get($userId);
                if (!$participantTime || (float) $participantTime->hours <= 0) {
                    continue;
                }

                $rows[] = [
                    'agreement_id' => $agreement->id,
                    'activity_id' => $activity->id,
                    'contact_family_id' => $contactFamilyId,
                    'activity_type_id' => $activity->activity_type_id,
                    'contributor_user_id' => $userId,
                    'activity_date' => $activity->engagement_date,
                    'contribution_kind' => 'time',
                    'completion_units' => null,
                    'activity_hours' => (float) $participantTime->hours,
                    'prep_hours' => (float) $participantTime->prep_hours,
                    'follow_up_hours' => (float) $participantTime->follow_up_hours,
                    'program_ids_snapshot' => $programIds,
                    'team_ids_snapshot' => $agreementTeamIdsByUser->get($userId, []),
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
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
        $includeAdditional = (bool) $deliverable->include_additional_time;
        $fingerprint = sha1(implode('|', [
            $deliverable->id,
            $metric,
            $basis,
            $deliverable->user_grouping_mode,
            $includeAdditional ? '1' : '0',
            (string) $deliverable->target_quantity,
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
            return $this->buildContactContributionRows($matchingHistory, $agreementId, $deliverable->id, $metric, $includeAdditional, $fingerprint);
        }

        return $this->buildUserContributionRows($matchingHistory, $agreementId, $deliverable, $metric, $includeAdditional, $fingerprint);
    }

    private function buildContactContributionRows(
        $matchingHistory,
        int $agreementId,
        int $deliverableId,
        string $metric,
        bool $includeAdditional,
        string $fingerprint
    ): array {
        $historyRows = $matchingHistory
            ->whereNull('contributor_user_id')
            ->where('contribution_kind', $metric === 'completion' ? 'completion' : 'time')
            ->values();

        if ($metric === 'completion') {
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
                    'credited_units' => (float) ($history->completion_units ?? 0),
                    'credited_hours' => null,
                    'prep_hours' => 0,
                    'follow_up_hours' => 0,
                    'rules_fingerprint' => $fingerprint,
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            })->all();
        }

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
                'prep_hours' => $includeAdditional ? (float) $history->prep_hours : 0,
                'follow_up_hours' => $includeAdditional ? (float) $history->follow_up_hours : 0,
                'rules_fingerprint' => $fingerprint,
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
        bool $includeAdditional,
        string $fingerprint
    ): array {
        $eligibleUsersById = $deliverable->users
            ->keyBy(fn ($user) => (int) $user->id);
        $deliverableTeams = $deliverable->teams->keyBy(fn ($team) => (int) $team->id);

        $matchedHistoryRows = $matchingHistory
            ->whereNotNull('contributor_user_id')
            ->where('contribution_kind', $metric === 'completion' ? 'completion' : 'time')
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

        return $matchedHistoryRows->map(function (array $matchedRow) use ($agreementId, $deliverable, $metric, $includeAdditional, $fingerprint) {
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
                    'credited_units' => (float) ($history->completion_units ?? 0),
                    'credited_hours' => null,
                    'prep_hours' => 0,
                    'follow_up_hours' => 0,
                    'rules_fingerprint' => $fingerprint,
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
                'prep_hours' => $includeAdditional ? (float) $history->prep_hours : 0,
                'follow_up_hours' => $includeAdditional ? (float) $history->follow_up_hours : 0,
                'rules_fingerprint' => $fingerprint,
                'created_at' => now(),
                'updated_at' => now(),
            ];
        })->all();
    }

    private function resolveUserHistoryMatch(
        AgreementActivityHistory $history,
        Collection $eligibleUsersById,
        Collection $deliverableTeamsById
    ): ?string {
        $user = $eligibleUsersById->get((int) $history->contributor_user_id);
        $activityDate = CarbonImmutable::parse($history->activity_date)->startOfDay();

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

        if ($deliverableTeamsById->isEmpty()) {
            return null;
        }

        $historyTeamIds = collect($history->team_ids_snapshot ?? [])
            ->map(fn ($id) => (int) $id)
            ->values();

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

        return null;
    }
}