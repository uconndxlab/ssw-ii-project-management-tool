<?php

namespace App\Support;

use App\Models\Agreement;
use App\Models\AgreementDeliverable;
use App\Models\DeliverableContribution;
use App\Models\Team;
use App\Models\User;
use App\Support\ActivityTypeDuration;
use Illuminate\Support\Collection;

class AgreementDeliverableDisplay
{
    public static function buildGroupedProgress(Agreement $agreement): Collection
    {
        $teamLookup = $agreement->teams->keyBy(fn (Team $team) => (int) $team->id);
        $agreementTeamIds = $teamLookup->keys();
        $agreementMemberUserIds = self::buildAgreementMemberUserIds($agreement);

        $items = $agreement->deliverables
            ->reject(fn (AgreementDeliverable $deliverable) => $deliverable->retired_at)
            ->map(fn (AgreementDeliverable $deliverable) => self::buildDeliverableProgress(
                $deliverable,
                $teamLookup,
                $agreementTeamIds,
                $agreementMemberUserIds
            ))
            ->values();

        return self::groupProgressItems($items);
    }

    /**
     * Deliverable progress grouped and filtered to items the user can contribute to, with user_focus stats.
     */
    public static function buildGroupedProgressForUser(Agreement $agreement, User $user): Collection
    {
        $userId = (int) $user->id;

        $items = $agreement->deliverables
            ->reject(fn (AgreementDeliverable $deliverable) => $deliverable->retired_at)
            ->filter(fn (AgreementDeliverable $deliverable) => self::userCanContributeToDeliverable($agreement, $deliverable, $user))
            ->map(function (AgreementDeliverable $deliverable) use ($agreement, $userId) {
                $teamLookup = $agreement->teams->keyBy(fn (Team $team) => (int) $team->id);
                $agreementTeamIds = $teamLookup->keys();
                $agreementMemberUserIds = self::buildAgreementMemberUserIds($agreement);

                $progress = self::buildDeliverableProgress(
                    $deliverable,
                    $teamLookup,
                    $agreementTeamIds,
                    $agreementMemberUserIds
                );

                return self::focusProgressOnUser($progress, $userId);
            })
            ->values();

        return self::groupProgressItems($items);
    }

    public static function userCanContributeToDeliverable(
        Agreement $agreement,
        AgreementDeliverable $deliverable,
        User $user
    ): bool {
        $teamLookup = $agreement->teams->keyBy(fn (Team $team) => (int) $team->id);
        $memberIds = self::buildAgreementMemberUserIds($agreement);
        $userId = (int) $user->id;

        if (!$memberIds->contains($userId)) {
            return false;
        }

        if ($deliverable->contribution_basis === 'contact') {
            return true;
        }

        return self::currentlyAssignedUserIds($deliverable, $teamLookup, $memberIds)->contains($userId);
    }

    private static function focusProgressOnUser(array $progress, int $userId): array
    {
        if ($progress['is_individual']) {
            $row = $progress['individual_progress']->first(fn (array $individual) => (int) $individual['user']->id === $userId);
            $target = (float) ($row['target'] ?? $progress['target']);
            $completed = (float) ($row['completed_value'] ?? 0);
            $progress['user_focus'] = [
                'completed' => $completed,
                'target' => $target,
                'percent' => $target > 0 ? min(100, ($completed / $target) * 100) : 0,
                'shared' => false,
            ];

            return $progress;
        }

        if ($progress['is_joint']) {
            $completed = 0.0;
            foreach ($progress['live_assignment_groups'] as $group) {
                foreach ($group['users'] as $row) {
                    if ((int) $row['user_id'] === $userId) {
                        $completed = (float) $row['completed_value'];
                        break 2;
                    }
                }
            }

            $progress['user_focus'] = [
                'completed' => $completed,
                'target' => null,
                'percent' => null,
                'shared' => true,
            ];

            return $progress;
        }

        $progress['user_focus'] = [
            'completed' => (float) $progress['completed_value'],
            'target' => (float) $progress['target'],
            'percent' => (float) $progress['percent'],
            'shared' => true,
        ];

        return $progress;
    }

    private static function groupProgressItems(Collection $items): Collection
    {
        return $items
            ->groupBy(fn (array $item) => (int) ($item['deliverable']->contact_family_id ?? 0))
            ->map(function (Collection $familyItems) {
                $contactFamily = $familyItems->first()['deliverable']->contactFamily;

                return [
                    'contact_family' => $contactFamily,
                    'contact_family_label' => $contactFamily?->name ?? 'Unspecified Contact Family',
                    'activity_groups' => $familyItems
                        ->groupBy(fn (array $item) => (int) ($item['deliverable']->activity_type_id ?? 0))
                        ->map(function (Collection $activityItems) {
                            $activityType = $activityItems->first()['deliverable']->activityType;

                            return [
                                'activity_type' => $activityType,
                                'activity_type_label' => $activityType?->name ?? 'Any activity type',
                                'program_groups' => $activityItems
                                    ->groupBy(fn (array $item) => (int) ($item['deliverable']->program_id ?? 0))
                                    ->map(function (Collection $programItems) {
                                        $program = $programItems->first()['deliverable']->program;

                                        return [
                                            'program' => $program,
                                            'program_label' => $program?->name ?? 'Any selected agreement program',
                                            'deliverables' => $programItems
                                                ->sortBy(fn (array $item) => [
                                                    $item['deliverable']->sort_order ?? 0,
                                                    $item['deliverable']->id ?? 0,
                                                ])
                                                ->values(),
                                        ];
                                    })
                                    ->sortBy(fn (array $group) => $group['program_label'])
                                    ->values(),
                            ];
                        })
                        ->sortBy(fn (array $group) => $group['activity_type_label'])
                        ->values(),
                ];
            })
            ->sortBy(fn (array $group) => $group['contact_family_label'])
            ->values();
    }

    private static function buildDeliverableProgress(
        AgreementDeliverable $deliverable,
        Collection $teamLookup,
        Collection $agreementTeamIds,
        Collection $agreementMemberUserIds
    ): array {
        $contributions = $deliverable->contributions
            ->where('cancelled', false)
            ->values();
        $target = (float) ($deliverable->target_quantity ?? 0);
        $isTime = $deliverable->metric_type === 'time';
        $isAllottedTime = $isTime && ($deliverable->time_basis ?? 'observed') === 'allotted';
        $allottedTimeUnit = ActivityTypeDuration::resolveAllottedTimeUnitForDeliverable($deliverable);
        $isIndividual = $deliverable->contribution_basis === 'user'
            && $deliverable->user_grouping_mode === 'individual';
        $isJoint = $deliverable->contribution_basis === 'user'
            && $deliverable->user_grouping_mode === 'joint';

        $completedValue = $isTime
            ? ($isAllottedTime
                ? ($allottedTimeUnit === ActivityTypeDuration::UNIT_DAYS
                    ? (float) $contributions->sum('credited_allotted_days')
                    : (float) $contributions->sum('credited_allotted_hours'))
                : (float) $contributions->sum('credited_hours'))
            : (float) $contributions->sum('credited_units');

        $currentlyAssignedUserIds = self::currentlyAssignedUserIds(
            $deliverable,
            $teamLookup,
            $agreementMemberUserIds
        );
        $contributorSummaries = self::buildContributorSummaries(
            $contributions,
            $deliverable,
            $teamLookup,
            $agreementTeamIds,
            $isTime,
            $isAllottedTime,
            $allottedTimeUnit
        );
        $contributorByUserId = $contributorSummaries->keyBy('user_id');

        $pastContributions = self::buildPastAssignees(
            $deliverable,
            $currentlyAssignedUserIds,
            $contributorByUserId,
            $teamLookup,
            $target,
            false
        );

        $liveAssignmentGroups = collect();
        $individualProgress = collect();
        $pastIndividualProgress = collect();

        if ($isJoint) {
            $liveAssignmentGroups = self::buildLiveAssignmentGroups(
                $deliverable,
                $teamLookup,
                $agreementMemberUserIds,
                $currentlyAssignedUserIds,
                $contributorByUserId
            );
        } elseif ($isIndividual) {
            $assignedUsers = $deliverable->users
                ->filter(fn (User $user) => self::isActivelyAssignedUser(
                    $user,
                    $deliverable,
                    $teamLookup,
                    $agreementMemberUserIds
                ))
                ->values();

            $individualProgress = $assignedUsers->map(function (User $user) use ($contributorByUserId, $target) {
                $summary = $contributorByUserId->get((int) $user->id);
                $completed = (float) ($summary['completed_value'] ?? 0);

                return self::memberRow($user, $summary, $completed, $target);
            })->values();

            $pastIndividualProgress = self::buildPastAssignees(
                $deliverable,
                $currentlyAssignedUserIds,
                $contributorByUserId,
                $teamLookup,
                $target,
                true
            );
        }

        $metricParts = [];
        if ($deliverable->metric_type === 'time') {
            $metricParts[] = $isAllottedTime ? 'Allotted time' : 'Time';
        } elseif ($deliverable->metric_type) {
            $metricParts[] = ucfirst($deliverable->metric_type);
        }
        if ($deliverable->contribution_basis) {
            $metricParts[] = $deliverable->contribution_basis === 'contact' ? 'By contact' : 'By user';
        }
        if ($deliverable->user_grouping_mode) {
            $metricParts[] = ucfirst($deliverable->user_grouping_mode);
        }
        if ($deliverable->include_additional_time) {
            $metricParts[] = 'Includes prep/follow up';
        }

        $unitLabel = 'Completions';
        if ($isTime) {
            if ($isAllottedTime && $allottedTimeUnit === ActivityTypeDuration::UNIT_DAYS) {
                $unitLabel = 'Days';
            } else {
                $unitLabel = 'Hours';
            }
        }

        return [
            'deliverable' => $deliverable,
            'target' => $target,
            'completed_value' => $completedValue,
            'percent' => $target > 0 ? min(100, ($completedValue / $target) * 100) : 0,
            'unit_label' => $unitLabel,
            'metric_summary' => implode(' · ', $metricParts),
            'is_individual' => $isIndividual,
            'is_joint' => $isJoint,
            'live_assignment_groups' => $liveAssignmentGroups,
            'past_contributions' => $pastContributions,
            'individual_progress' => $individualProgress,
            'past_individual_progress' => $pastIndividualProgress,
            'shows_contributor_breakdown' => $deliverable->contribution_basis === 'user',
            'assignment_groups' => self::buildTableAssignmentGroups(
                $deliverable,
                $teamLookup,
                $agreementMemberUserIds
            ),
        ];
    }

    /**
     * Grouped assignment structure for the deliverables editor table.
     *
     * @return array<int, array{team: ?Team, team_name: ?string, users: Collection<int, User>}>
     */
    public static function buildTableAssignmentGroups(
        AgreementDeliverable $deliverable,
        Collection $teamLookup,
        Collection $agreementMemberUserIds
    ): array {
        $assignedTeams = $deliverable->teams
            ->filter(fn (Team $team) => !$team->pivot->unassigned_at)
            ->values();
        $assignedUsers = $deliverable->users
            ->filter(fn (User $user) => !$user->pivot->unassigned_at)
            ->values();

        $groups = [];
        $groupedUserIds = collect();

        foreach ($assignedTeams as $team) {
            $agreementTeam = $teamLookup->get((int) $team->id);
            $members = $agreementTeam?->users ?? collect();

            if ($deliverable->user_grouping_mode === 'joint') {
                $teamUsers = $members->sortBy('name')->values();
            } else {
                $teamUsers = $assignedUsers
                    ->filter(fn (User $user) => (int) ($user->pivot->source_team_id ?? 0) === (int) $team->id
                        || $members->contains('id', $user->id))
                    ->sortBy('name')
                    ->values();
            }

            $groupedUserIds = $groupedUserIds->merge($teamUsers->pluck('id'));

            $groups[] = [
                'team' => $team,
                'team_name' => $team->name,
                'users' => $teamUsers,
            ];
        }

        $standaloneUsers = $assignedUsers
            ->filter(fn (User $user) => self::isActivelyAssignedUser(
                $user,
                $deliverable,
                $teamLookup,
                $agreementMemberUserIds
            ))
            ->reject(fn (User $user) => $groupedUserIds->contains($user->id))
            ->sortBy('name')
            ->values();

        if ($standaloneUsers->isNotEmpty()) {
            $groups[] = [
                'team' => null,
                'team_name' => null,
                'users' => $standaloneUsers,
            ];
        }

        return $groups;
    }

    private static function buildLiveAssignmentGroups(
        AgreementDeliverable $deliverable,
        Collection $teamLookup,
        Collection $agreementMemberUserIds,
        Collection $currentlyAssignedUserIds,
        Collection $contributorByUserId
    ): Collection {
        $assignedTeams = $deliverable->teams
            ->filter(fn (Team $team) => !$team->pivot->unassigned_at)
            ->values();
        $assignedUsers = $deliverable->users
            ->filter(fn (User $user) => !$user->pivot->unassigned_at)
            ->values();

        $groups = collect();
        $groupedUserIds = collect();

        foreach ($assignedTeams as $team) {
            $agreementTeam = $teamLookup->get((int) $team->id);
            $memberIds = $agreementTeam?->users
                ->pluck('id')
                ->map(fn ($id) => (int) $id) ?? collect();

            $rows = $memberIds
                ->filter(fn (int $userId) => $currentlyAssignedUserIds->contains($userId))
                ->map(function (int $userId) use ($contributorByUserId, $teamLookup, $team, $agreementTeam) {
                    $user = $agreementTeam?->users->firstWhere('id', $userId);
                    if (!$user) {
                        return null;
                    }

                    $summary = $contributorByUserId->get($userId);

                    return [
                        'user_id' => $userId,
                        'user' => $user,
                        'team_name' => $team->name,
                        'completed_value' => (float) ($summary['completed_value'] ?? 0),
                        'source_assignment_type' => 'team',
                    ];
                })
                ->filter()
                ->sortBy(fn (array $row) => $row['user']->name)
                ->values();

            $groupedUserIds = $groupedUserIds->merge($rows->pluck('user_id'));

            $groups->push([
                'team' => $team,
                'users' => $rows,
            ]);
        }

        $standaloneRows = $assignedUsers
            ->filter(fn (User $user) => self::isActivelyAssignedUser(
                $user,
                $deliverable,
                $teamLookup,
                $agreementMemberUserIds
            ))
            ->reject(fn (User $user) => $groupedUserIds->contains((int) $user->id))
            ->map(function (User $user) use ($contributorByUserId, $deliverable, $teamLookup) {
                $summary = $contributorByUserId->get((int) $user->id);

                return [
                    'user_id' => (int) $user->id,
                    'user' => $user,
                    'team_name' => $summary['team_name'] ?? self::resolveDisplayTeamNameForAssignedUser($user, $deliverable, $teamLookup),
                    'completed_value' => (float) ($summary['completed_value'] ?? 0),
                    'source_assignment_type' => $user->pivot->source_team_id ? 'team' : 'user',
                ];
            })
            ->sortBy(fn (array $row) => $row['user']->name)
            ->values();

        if ($standaloneRows->isNotEmpty()) {
            $groups->push([
                'team' => null,
                'users' => $standaloneRows,
            ]);
        }

        return $groups;
    }

    private static function memberRow(
        User $user,
        ?array $summary,
        float $completed,
        float $target,
        bool $isLive = true
    ): array {
        return [
            'user' => $user,
            'team_name' => $summary['team_name'] ?? null,
            'completed_value' => $completed,
            'target' => $target,
            'percent' => $target > 0 ? min(100, ($completed / $target) * 100) : 0,
            'is_currently_assigned' => $isLive,
        ];
    }

    private static function isActivelyAssignedUser(
        User $user,
        AgreementDeliverable $deliverable,
        Collection $teamLookup,
        Collection $agreementMemberUserIds
    ): bool {
        if ($user->pivot->unassigned_at) {
            return false;
        }

        if (!$agreementMemberUserIds->contains((int) $user->id)) {
            return false;
        }

        $sourceTeamId = (int) ($user->pivot->source_team_id ?? 0);
        if ($sourceTeamId === 0) {
            return true;
        }

        $teamAssignedToDeliverable = $deliverable->teams
            ->filter(fn (Team $team) => !$team->pivot->unassigned_at)
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->contains($sourceTeamId);

        if (!$teamAssignedToDeliverable) {
            return false;
        }

        return $teamLookup->get($sourceTeamId)?->users->contains('id', $user->id) ?? false;
    }

    private static function buildAgreementMemberUserIds(Agreement $agreement): Collection
    {
        return $agreement->users
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->merge(
                $agreement->teams->flatMap(
                    fn (Team $team) => $team->users->pluck('id')->map(fn ($id) => (int) $id)
                )
            )
            ->unique()
            ->values();
    }

    private static function buildPastAssignees(
        AgreementDeliverable $deliverable,
        Collection $currentlyAssignedUserIds,
        Collection $contributorByUserId,
        Collection $teamLookup,
        float $target,
        bool $asIndividualRows
    ): Collection {
        $candidateUserIds = $deliverable->users
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->merge($contributorByUserId->keys()->map(fn ($id) => (int) $id))
            ->unique()
            ->reject(fn (int $userId) => $currentlyAssignedUserIds->contains($userId))
            ->values();

        return $candidateUserIds
            ->map(function (int $userId) use (
                $deliverable,
                $contributorByUserId,
                $teamLookup,
                $target,
                $asIndividualRows
            ) {
                $assignedUser = $deliverable->users->firstWhere('id', $userId);
                $summary = $contributorByUserId->get($userId);
                $user = $summary['user'] ?? $assignedUser;

                if (!$user) {
                    return null;
                }

                $completed = (float) ($summary['completed_value'] ?? 0);
                $teamName = $summary['team_name']
                    ?? self::resolveFormerAssigneeTeamName($assignedUser, $teamLookup);

                if ($asIndividualRows) {
                    return self::memberRow(
                        $user,
                        array_merge($summary ?? [], ['team_name' => $teamName]),
                        $completed,
                        $target,
                        false
                    );
                }

                return [
                    'user_id' => $userId,
                    'user' => $user,
                    'team_name' => $teamName,
                    'completed_value' => $completed,
                    'source_assignment_type' => $assignedUser?->pivot?->source_team_id ? 'team' : 'user',
                ];
            })
            ->filter()
            ->filter(fn (array $row) => (float) ($row['completed_value'] ?? 0) > 0)
            ->sortBy(fn (array $row) => $row['user']->name ?? '')
            ->values();
    }

    private static function resolveFormerAssigneeTeamName(
        ?User $assignedUser,
        Collection $teamLookup
    ): ?string {
        if (!$assignedUser?->pivot?->source_team_id) {
            return null;
        }

        return $teamLookup->get((int) $assignedUser->pivot->source_team_id)?->name;
    }

    private static function currentlyAssignedUserIds(
        AgreementDeliverable $deliverable,
        Collection $teamLookup,
        Collection $agreementMemberUserIds
    ): Collection {
        $directIds = $deliverable->users
            ->filter(fn (User $user) => self::isActivelyAssignedUser(
                $user,
                $deliverable,
                $teamLookup,
                $agreementMemberUserIds
            ))
            ->pluck('id')
            ->map(fn ($id) => (int) $id);

        if ($deliverable->user_grouping_mode === 'individual') {
            return $directIds->unique()->values();
        }

        $activeTeamIds = $deliverable->teams
            ->filter(fn (Team $team) => !$team->pivot->unassigned_at)
            ->pluck('id')
            ->map(fn ($id) => (int) $id);

        $teamMemberIds = $activeTeamIds
            ->flatMap(fn (int $teamId) => $teamLookup->get($teamId)?->users?->pluck('id') ?? collect())
            ->map(fn ($id) => (int) $id)
            ->filter(fn (int $userId) => $agreementMemberUserIds->contains($userId));

        return $directIds
            ->merge($teamMemberIds)
            ->unique()
            ->values();
    }

    private static function resolveDisplayTeamNameForAssignedUser(
        User $user,
        AgreementDeliverable $deliverable,
        Collection $teamLookup
    ): ?string {
        $assignedUser = $deliverable->users->firstWhere('id', $user->id);
        if ($assignedUser?->pivot?->source_team_id) {
            return $teamLookup->get((int) $assignedUser->pivot->source_team_id)?->name;
        }

        $activeDeliverableTeamIds = $deliverable->teams
            ->filter(fn (Team $team) => !$team->pivot->unassigned_at)
            ->pluck('id')
            ->map(fn ($id) => (int) $id);

        if ($activeDeliverableTeamIds->isEmpty()) {
            return null;
        }

        return $teamLookup
            ->only($activeDeliverableTeamIds->all())
            ->first(fn (Team $team) => $team->users->contains('id', $user->id))
            ?->name;
    }

    private static function buildContributorSummaries(
        Collection $contributions,
        AgreementDeliverable $deliverable,
        Collection $teamLookup,
        Collection $agreementTeamIds,
        bool $isTime,
        bool $isAllottedTime = false,
        ?string $allottedTimeUnit = null
    ): Collection {
        return $contributions
            ->whereNotNull('contributor_user_id')
            ->groupBy('contributor_user_id')
            ->map(function (Collection $userContributions) use ($deliverable, $teamLookup, $agreementTeamIds, $isTime, $isAllottedTime, $allottedTimeUnit) {
                /** @var DeliverableContribution $first */
                $first = $userContributions->first();
                $user = $first->contributor;

                if (!$user) {
                    return null;
                }

                $completedValue = $isTime
                    ? ($isAllottedTime
                        ? ($allottedTimeUnit === ActivityTypeDuration::UNIT_DAYS
                            ? (float) $userContributions->sum('credited_allotted_days')
                            : (float) $userContributions->sum('credited_allotted_hours'))
                        : (float) $userContributions->sum('credited_hours'))
                    : (float) $userContributions->sum('credited_units');

                return [
                    'user_id' => (int) $user->id,
                    'user' => $user,
                    'team_name' => self::resolveContributorTeamName(
                        $userContributions,
                        $deliverable,
                        $teamLookup,
                        $agreementTeamIds
                    ),
                    'completed_value' => $completedValue,
                    'source_assignment_type' => $first->source_assignment_type,
                ];
            })
            ->filter()
            ->sortBy(fn (array $summary) => $summary['user']->name)
            ->values();
    }

    private static function resolveContributorTeamName(
        Collection $userContributions,
        AgreementDeliverable $deliverable,
        Collection $teamLookup,
        Collection $agreementTeamIds
    ): ?string {
        $userId = (int) $userContributions->first()->contributor_user_id;
        $assignedUser = $deliverable->users->firstWhere('id', $userId);

        if ($assignedUser?->pivot?->source_team_id) {
            return $teamLookup->get((int) $assignedUser->pivot->source_team_id)?->name;
        }

        foreach ($userContributions as $contribution) {
            $history = $contribution->activityHistory;
            if (!$history || empty($history->team_ids_snapshot)) {
                continue;
            }

            $snapshotTeamIds = collect($history->team_ids_snapshot)->map(fn ($id) => (int) $id);
            $matchingTeamId = $snapshotTeamIds
                ->intersect($agreementTeamIds)
                ->first()
                ?? $snapshotTeamIds->first();

            if ($matchingTeamId && $teamLookup->has((int) $matchingTeamId)) {
                return $teamLookup->get((int) $matchingTeamId)?->name;
            }
        }

        return null;
    }
}
