<?php

namespace App\Support;

use App\Enums\DeliverableStatus;
use App\Models\ActivityType;
use App\Models\Agreement;
use App\Models\AgreementDeliverable;
use App\Models\ContactFamily;
use App\Models\DeliverableContribution;
use App\Models\Program;
use App\Models\Team;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class AgreementDeliverableDisplay
{
    /**
     * @return Collection<int, array<string, mixed>>
     */
    public static function buildGroupedProgress(
        Agreement $agreement,
        ?Carbon $from = null,
        ?Carbon $to = null
    ): Collection {
        $teamLookup = $agreement->teams->keyBy(fn (Team $team) => (int) $team->id);
        $agreementTeamIds = $teamLookup->keys();
        $agreementMemberUserIds = self::buildAgreementMemberUserIds($agreement);

        $items = $agreement->deliverables
            ->reject(fn (AgreementDeliverable $deliverable) => $deliverable->retired_at !== null)
            ->map(fn (AgreementDeliverable $deliverable) => self::buildDeliverableProgress(
                $deliverable,
                $teamLookup,
                $agreementTeamIds,
                $agreementMemberUserIds,
                $agreement,
                $from,
                $to
            ))
            ->values();

        return self::groupProgressItems($items);
    }

    /**
     * Deliverable progress grouped and filtered to items the user can contribute to, with user_focus stats.
     *
     * @return Collection<int, array<string, mixed>>
     */
    public static function buildGroupedProgressForUser(Agreement $agreement, User $user): Collection
    {
        $userId = (int) $user->id;

        $items = $agreement->deliverables
            ->reject(fn (AgreementDeliverable $deliverable) => $deliverable->retired_at !== null)
            ->filter(fn (AgreementDeliverable $deliverable) => self::userIsTaggedOrAssigned($agreement, $deliverable, $user))
            ->map(function (AgreementDeliverable $deliverable) use ($agreement, $userId) {
                $teamLookup = $agreement->teams->keyBy(fn (Team $team) => (int) $team->id);
                $agreementTeamIds = $teamLookup->keys();
                $agreementMemberUserIds = self::buildAgreementMemberUserIds($agreement);

                $progress = self::buildDeliverableProgress(
                    $deliverable,
                    $teamLookup,
                    $agreementTeamIds,
                    $agreementMemberUserIds,
                    $agreement
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

        if (! $memberIds->contains($userId)) {
            return false;
        }

        if ($deliverable->contribution_basis === 'contact') {
            return true;
        }

        return self::currentlyAssignedUserIds($deliverable, $teamLookup, $memberIds)->contains($userId);
    }

    public static function userIsTaggedOrAssigned(
        Agreement $agreement,
        AgreementDeliverable $deliverable,
        User $user
    ): bool {
        $teamLookup = $agreement->teams->keyBy(fn (Team $team) => (int) $team->id);
        $memberIds = self::buildAgreementMemberUserIds($agreement);
        $assignedUser = $deliverable->users->firstWhere('id', (int) $user->id);

        if (! $assignedUser) {
            return false;
        }

        return self::isActivelyAssignedUser($assignedUser, $deliverable, $teamLookup, $memberIds);
    }

    /**
     * @return Collection<int, int>
     */
    public static function buildAgreementMemberUserIdsPublic(Agreement $agreement): Collection
    {
        return self::buildAgreementMemberUserIds($agreement);
    }

    /**
     * @param  Collection<int, Team>  $teamLookup
     * @param  Collection<int, int>  $agreementMemberUserIds
     */
    public static function isActivelyAssignedUserPublic(
        User $user,
        AgreementDeliverable $deliverable,
        Collection $teamLookup,
        Collection $agreementMemberUserIds
    ): bool {
        return self::isActivelyAssignedUser($user, $deliverable, $teamLookup, $agreementMemberUserIds);
    }

    /**
     * @param  array<string, mixed>  $progress
     * @return array<string, mixed>
     */
    private static function focusProgressOnUser(array $progress, int $userId): array
    {
        if ($progress['is_individual']) {
            $row = $progress['individual_progress']->first(fn (array $individual) => (int) $individual['user']->id === $userId);
            $target = (float) ($row['target'] ?? $progress['target']);
            $completed = (float) ($row['completed_value'] ?? 0);
            $progress['user_focus'] = [
                'completed' => $completed,
                'target' => $target,
                'has_target' => (bool) ($row['has_target'] ?? $progress['has_target']),
                'percent' => $target > 0 ? min(100, ($completed / $target) * 100) : 0,
                'shared' => false,
            ];

            return $progress;
        }

        if ($progress['is_joint']) {
            $completed = 0.0;
            $recommended = null;
            foreach ($progress['live_assignment_groups'] as $group) {
                foreach ($group['users'] as $row) {
                    if ((int) $row['user_id'] === $userId) {
                        $completed = (float) $row['completed_value'];
                        $recommended = $row['recommended_target'] ?? null;
                        break 2;
                    }
                }
            }

            $progress['user_focus'] = [
                'completed' => $completed,
                'target' => $recommended,
                'has_target' => $recommended !== null && (float) $recommended > 0,
                'percent' => $recommended > 0 ? min(100, ($completed / (float) $recommended) * 100) : null,
                'shared' => true,
            ];

            return $progress;
        }

        if ($progress['is_contact']) {
            $recommended = self::resolveUserPivotTarget($progress['deliverable'], $userId);

            $progress['user_focus'] = [
                'completed' => 0.0,
                'target' => $recommended,
                'has_target' => $recommended !== null && (float) $recommended > 0,
                'percent' => null,
                'shared' => true,
                'is_contact_tag' => true,
            ];

            return $progress;
        }

        $progress['user_focus'] = [
            'completed' => (float) $progress['completed_value'],
            'target' => (float) $progress['target'],
            'has_target' => (bool) $progress['has_target'],
            'percent' => (float) $progress['percent'],
            'shared' => true,
        ];

        return $progress;
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $items
     * @return Collection<int, mixed>
     */
    private static function groupProgressItems(Collection $items): Collection
    {
        return $items
            ->groupBy(fn (array $item) => (int) ($item['deliverable']->contact_family_id ?? 0))
            ->map(function (Collection $familyItems) {
                /** @var Collection<int, array<string, mixed>> $familyItems */
                $first = $familyItems->first();
                if ($first === null) {
                    return [
                        'contact_family' => null,
                        'contact_family_label' => 'Unspecified Activity Family',
                        'activity_groups' => collect(),
                    ];
                }
                $contactFamily = $first['deliverable']->contactFamily;

                return [
                    'contact_family' => $contactFamily,
                    'contact_family_label' => $contactFamily instanceof ContactFamily ? $contactFamily->name : 'Unspecified Activity Family',
                    'activity_groups' => $familyItems
                        ->groupBy(fn (array $item) => (int) ($item['deliverable']->activity_type_id ?? 0))
                        ->map(function (Collection $activityItems) {
                            /** @var Collection<int, array<string, mixed>> $activityItems */
                            $first = $activityItems->first();
                            if ($first === null) {
                                return [
                                    'activity_type' => null,
                                    'activity_type_label' => 'Any activity type',
                                    'program_groups' => collect(),
                                ];
                            }
                            $activityType = $first['deliverable']->activityType;

                            return [
                                'activity_type' => $activityType,
                                'activity_type_label' => $activityType instanceof ActivityType ? $activityType->name : 'Any activity type',
                                'program_groups' => $activityItems
                                    ->groupBy(fn (array $item) => (int) ($item['deliverable']->program_id ?? 0))
                                    ->map(function (Collection $programItems) {
                                        /** @var Collection<int, array<string, mixed>> $programItems */
                                        $first = $programItems->first();
                                        if ($first === null) {
                                            return [
                                                'program' => null,
                                                'program_label' => 'Any selected agreement program',
                                                'deliverables' => collect(),
                                            ];
                                        }
                                        $program = $first['deliverable']->program;

                                        return [
                                            'program' => $program,
                                            'program_label' => $program instanceof Program ? $program->name : 'Any selected agreement program',
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

    /**
     * @param  Collection<int, Team>  $teamLookup
     * @param  Collection<int, int>  $agreementTeamIds
     * @param  Collection<int, int>  $agreementMemberUserIds
     * @return array<string, mixed>
     */
    private static function buildDeliverableProgress(
        AgreementDeliverable $deliverable,
        Collection $teamLookup,
        Collection $agreementTeamIds,
        Collection $agreementMemberUserIds,
        Agreement $agreement,
        ?Carbon $from = null,
        ?Carbon $to = null
    ): array {
        $contributions = $deliverable->contributions
            ->where('cancelled', false)
            ->filter(fn (DeliverableContribution $contribution) => self::contributionIsInWindow($contribution, $from, $to))
            ->values();
        $target = (float) ($deliverable->target_quantity ?? 0);
        $isTime = $deliverable->metric_type === 'time';
        $isAllottedTime = $isTime && ($deliverable->time_basis ?? 'observed') === 'allotted';
        $allottedTimeUnit = ActivityTypeDuration::resolveAllottedTimeUnitForDeliverable($deliverable);
        $isIndividual = DeliverableAssignmentTargets::isIndividual($deliverable);
        $isJoint = DeliverableAssignmentTargets::isJoint($deliverable);
        $isContact = DeliverableAssignmentTargets::isContact($deliverable);

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

        $status = $isIndividual
            ? null
            : self::statusForAssignment($deliverable, $agreement, $completedValue, $contributions, $from, $to);

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
        $rollupCounts = null;

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

            $individualProgress = $assignedUsers->map(function (User $user) use (
                $contributorByUserId,
                $deliverable,
                $agreement,
                $contributions,
                $from,
                $to
            ) {
                $summary = $contributorByUserId->get((int) $user->id);
                $completed = (float) (is_array($summary) ? $summary['completed_value'] : 0);
                $userTarget = (float) (self::resolveUserPivotTarget($deliverable, (int) $user->id) ?? 0);
                $userContributions = $contributions
                    ->where('contributor_user_id', (int) $user->id)
                    ->values();

                return self::memberRow(
                    $user,
                    $summary,
                    $completed,
                    $userTarget,
                    true,
                    self::statusForAssignment($deliverable, $agreement, $completed, $userContributions, $from, $to, $userTarget)
                );
            })->values();

            $rollupCounts = DeliverableAssignmentTargets::countOnTrackStatuses(
                $individualProgress->pluck('status')
            );
            $status = DeliverableAssignmentTargets::rollupIndividualStatus(
                $individualProgress->pluck('status')
            );

            $pastIndividualProgress = self::buildPastAssignees(
                $deliverable,
                $currentlyAssignedUserIds,
                $contributorByUserId,
                $teamLookup,
                0,
                true,
                $agreement,
                $contributions,
                $from,
                $to
            );
        }

        $liveTeams = $deliverable->teams->filter(fn (Team $team) => ! $team->pivot?->unassigned_at)->values();
        $liveUsers = $deliverable->users
            ->filter(fn (User $user) => self::isActivelyAssignedUser(
                $user,
                $deliverable,
                $teamLookup,
                $agreementMemberUserIds
            ))
            ->values();
        $allocationSummary = DeliverableAssignmentTargets::summarizeFromDeliverable(
            $deliverable,
            $liveUsers,
            $liveTeams,
            $teamLookup
        );

        $countedCompleted = $completedValue;
        $sectionedBar = null;
        if ($isIndividual && $target > 0) {
            $personTargets = [];
            $personCompleted = [];
            foreach ($individualProgress as $row) {
                $userId = (int) $row['user']->id;
                if ($row['target'] > 0) {
                    $personTargets[$userId] = (float) $row['target'];
                }
                $personCompleted[$userId] = (float) $row['completed_value'];
            }
            $countedCompleted = DeliverableAssignmentTargets::countedTotalTowardTarget($personTargets, $personCompleted);
            $sectionedBar = self::buildIndividualSectionedBar($individualProgress, $target, $allocationSummary);
        }

        $taggedAssignmentGroups = ($isContact || $isJoint)
            ? self::buildTaggedDisplayGroups($deliverable, $teamLookup, $agreementMemberUserIds, $contributorByUserId)
            : collect();

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

        $displayCompleted = $isIndividual && $target > 0 ? $countedCompleted : $completedValue;
        $overTargetValue = $isIndividual && $target > 0
            ? max(0, round($completedValue - $countedCompleted, 2))
            : ($target > 0 ? max(0, round($completedValue - $target, 2)) : 0);

        $allocationNotices = [];
        if ($overTargetValue > 0) {
            $allocationNotices['excess_logged'] = $overTargetValue;
        }
        if ($isIndividual) {
            $remainder = (float) $allocationSummary['remainder'];
            if ($remainder > 0.009) {
                $allocationNotices['unassigned_remainder'] = round($remainder, 2);
            }
            if (! empty($sectionedBar['is_over_assigned']) && ($sectionedBar['over_assigned_by'] ?? 0) > 0.009) {
                $allocationNotices['over_assigned_by'] = (float) $sectionedBar['over_assigned_by'];
            }
        }

        return [
            'deliverable' => $deliverable,
            'target' => $target,
            'has_target' => $target > 0,
            'completed_value' => $completedValue,
            'counted_completed_value' => $displayCompleted,
            'logged_completed_value' => $completedValue,
            'over_target_value' => $overTargetValue,
            'allocation_notices' => $allocationNotices,
            'show_logged_total' => $isIndividual && $target > 0 && $completedValue > $countedCompleted,
            'percent' => $target > 0 ? min(100, ($displayCompleted / $target) * 100) : 0,
            'status' => $status,
            'rollup_on_track' => $isIndividual ? ($rollupCounts ?? null) : null,
            'unit_label' => $unitLabel,
            'metric_summary' => implode(' · ', $metricParts),
            'is_individual' => $isIndividual,
            'is_joint' => $isJoint,
            'is_contact' => $isContact,
            'live_assignment_groups' => $liveAssignmentGroups,
            'tagged_assignment_groups' => $taggedAssignmentGroups,
            'past_contributions' => $pastContributions,
            'individual_progress' => $individualProgress,
            'past_individual_progress' => $pastIndividualProgress,
            'sectioned_bar' => $sectionedBar,
            'allocation_summary' => $allocationSummary,
            'shows_contributor_breakdown' => $deliverable->contribution_basis === 'user',
            'assignment_groups' => self::buildTableAssignmentGroups(
                $deliverable,
                $teamLookup,
                $agreementMemberUserIds
            ),
            'burn_up' => DeliverableActivityHistogram::buildDeliverableBurnUp(
                $deliverable,
                $agreement,
                $target
            ),
        ];
    }

    private static function resolveUserPivotTarget(AgreementDeliverable $deliverable, int $userId): ?float
    {
        $assignedUser = $deliverable->users->firstWhere('id', $userId);

        return DeliverableAssignmentTargets::normalizeQuantity($assignedUser?->pivot?->target_quantity);
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $individualProgress
     * @param  array<string, mixed>  $allocationSummary
     * @return array<string, mixed>
     */
    private static function buildIndividualSectionedBar(
        Collection $individualProgress,
        float $totalTarget,
        array $allocationSummary
    ): array {
        $sections = [];
        $allocatedTarget = 0.0;

        foreach ($individualProgress as $row) {
            $personTarget = (float) ($row['target'] ?? 0);
            if ($personTarget <= 0) {
                continue;
            }

            $allocatedTarget += $personTarget;
            $completed = (float) $row['completed_value'];
            $rawPercent = $personTarget > 0 ? ($completed / $personTarget) * 100 : 0;
            $sections[] = [
                'type' => 'person',
                'user' => $row['user'],
                'target' => $personTarget,
                'completed' => $completed,
                'fill_percent' => min(100, $rawPercent),
            ];
        }

        $unassigned = max(0, round($totalTarget - $allocatedTarget, 2));
        if ($unassigned > 0) {
            $sections[] = [
                'type' => 'unassigned',
                'target' => $unassigned,
            ];
        }

        $sectionTargetSum = $sections === [] ? 0.0 : array_sum(array_map(
            fn (array $section) => (float) $section['target'],
            $sections
        ));
        $scaleBase = max($totalTarget, $sectionTargetSum, 0.0001);
        $isOverAssigned = $allocationSummary['is_over_assigned'] ?? false;

        foreach ($sections as &$section) {
            $section['width_percent'] = round(((float) $section['target'] / $scaleBase) * 100, 2);
        }
        unset($section);

        return [
            'sections' => $sections,
            'is_over_assigned' => $isOverAssigned,
            'over_assigned_by' => $isOverAssigned ? abs((float) ($allocationSummary['remainder'] ?? 0)) : 0,
        ];
    }

    /**
     * @param  Collection<int, Team>  $teamLookup
     * @param  Collection<int, int>  $agreementMemberUserIds
     * @param  Collection<int, array<string, mixed>>  $contributorByUserId
     * @return Collection<int, array<string, mixed>>
     */
    private static function buildTaggedDisplayGroups(
        AgreementDeliverable $deliverable,
        Collection $teamLookup,
        Collection $agreementMemberUserIds,
        Collection $contributorByUserId
    ): Collection {
        $assignedTeams = $deliverable->teams
            ->filter(fn (Team $team) => ! $team->pivot?->unassigned_at)
            ->values();

        $groups = collect();
        $groupedUserIds = collect();

        foreach ($assignedTeams as $team) {
            $agreementTeam = $teamLookup->get((int) $team->id);
            $memberIds = $agreementTeam?->users?->pluck('id')->map(fn ($id) => (int) $id) ?? collect();
            $rows = $memberIds
                ->map(function (int $userId) use ($deliverable, $contributorByUserId, $team, $agreementTeam, $agreementMemberUserIds, $teamLookup) {
                    $user = $agreementTeam?->users->firstWhere('id', $userId);
                    $assignedUser = $deliverable->users->firstWhere('id', $userId);
                    if (! $user || ! $assignedUser || ! self::isActivelyAssignedUser($assignedUser, $deliverable, $teamLookup, $agreementMemberUserIds)) {
                        return null;
                    }

                    $summary = $contributorByUserId->get($userId);

                    return [
                        'user_id' => $userId,
                        'user' => $user,
                        'team_name' => $team->name,
                        'completed_value' => (float) ($summary['completed_value'] ?? 0),
                        'recommended_target' => self::resolveUserPivotTarget($deliverable, $userId),
                    ];
                })
                ->filter()
                ->sortBy(fn (array $row) => $row['user']->name)
                ->values();

            $groupedUserIds = $groupedUserIds->merge($rows->pluck('user_id'));

            $groups->push([
                'team' => $team,
                'team_recommended_target' => DeliverableAssignmentTargets::normalizeQuantity($team->pivot?->target_quantity),
                'users' => $rows,
            ]);
        }

        $standaloneRows = $deliverable->users
            ->filter(fn (User $user) => self::isActivelyAssignedUser($user, $deliverable, $teamLookup, $agreementMemberUserIds))
            ->reject(fn (User $user) => $groupedUserIds->contains((int) $user->id))
            ->map(function (User $user) use ($contributorByUserId, $deliverable) {
                $summary = $contributorByUserId->get((int) $user->id);

                return [
                    'user_id' => (int) $user->id,
                    'user' => $user,
                    'team_name' => $summary['team_name'] ?? null,
                    'completed_value' => (float) ($summary['completed_value'] ?? 0),
                    'recommended_target' => self::resolveUserPivotTarget($deliverable, (int) $user->id),
                ];
            })
            ->sortBy(fn (array $row) => $row['user']->name)
            ->values();

        if ($standaloneRows->isNotEmpty()) {
            $groups->push([
                'team' => null,
                'team_recommended_target' => null,
                'users' => $standaloneRows,
            ]);
        }

        return $groups;
    }

    /**
     * Grouped assignment structure for the deliverables editor table.
     *
     * @param  Collection<int, Team>  $teamLookup
     * @param  Collection<int, int>  $agreementMemberUserIds
     * @return array<int, array{team: ?Team, team_name: ?string, users: Collection<int, User>}>
     */
    public static function buildTableAssignmentGroups(
        AgreementDeliverable $deliverable,
        Collection $teamLookup,
        Collection $agreementMemberUserIds
    ): array {
        $assignedTeams = $deliverable->teams
            ->filter(fn (Team $team) => ! $team->pivot?->unassigned_at)
            ->values();
        $assignedUsers = $deliverable->users
            ->filter(fn (User $user) => ! $user->pivot?->unassigned_at)
            ->values();

        $groups = [];
        $groupedUserIds = collect();

        foreach ($assignedTeams as $team) {
            $agreementTeam = $teamLookup->get((int) $team->id);
            $members = $agreementTeam !== null ? $agreementTeam->users : collect();

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

    /**
     * @param  Collection<int, Team>  $teamLookup
     * @param  Collection<int, int>  $agreementMemberUserIds
     * @param  Collection<int, int>  $currentlyAssignedUserIds
     * @param  Collection<int, array<string, mixed>>  $contributorByUserId
     * @return Collection<int, array<string, mixed>>
     */
    private static function buildLiveAssignmentGroups(
        AgreementDeliverable $deliverable,
        Collection $teamLookup,
        Collection $agreementMemberUserIds,
        Collection $currentlyAssignedUserIds,
        Collection $contributorByUserId
    ): Collection {
        $assignedTeams = $deliverable->teams
            ->filter(fn (Team $team) => ! $team->pivot?->unassigned_at)
            ->values();
        $assignedUsers = $deliverable->users
            ->filter(fn (User $user) => ! $user->pivot?->unassigned_at)
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
                ->map(function (int $userId) use ($contributorByUserId, $team, $agreementTeam, $deliverable) {
                    $user = $agreementTeam?->users->firstWhere('id', $userId);
                    if (! $user) {
                        return null;
                    }

                    $summary = $contributorByUserId->get($userId);

                    return [
                        'user_id' => $userId,
                        'user' => $user,
                        'team_name' => $team->name,
                        'completed_value' => (float) ($summary['completed_value'] ?? 0),
                        'recommended_target' => self::resolveUserPivotTarget($deliverable, $userId),
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
                    'recommended_target' => self::resolveUserPivotTarget($deliverable, (int) $user->id),
                    'source_assignment_type' => $user->pivot?->source_team_id ? 'team' : 'user',
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

    /**
     * @param  array<string, mixed>|null  $summary
     * @return array{
     *     user: User,
     *     team_name: mixed,
     *     completed_value: float,
     *     target: float,
     *     has_target: bool,
     *     percent: float|int,
     *     is_currently_assigned: bool,
     *     status: DeliverableStatus|null
     * }
     */
    private static function memberRow(
        User $user,
        ?array $summary,
        float $completed,
        float $target,
        bool $isLive = true,
        ?DeliverableStatus $status = null
    ): array {
        return [
            'user' => $user,
            'team_name' => $summary['team_name'] ?? null,
            'completed_value' => $completed,
            'target' => $target,
            'has_target' => $target > 0,
            'percent' => $target > 0 ? min(100, ($completed / $target) * 100) : 0,
            'is_currently_assigned' => $isLive,
            'status' => $status,
        ];
    }

    /**
     * @param  Collection<int, Team>  $teamLookup
     * @param  Collection<int, int>  $agreementMemberUserIds
     */
    private static function isActivelyAssignedUser(
        User $user,
        AgreementDeliverable $deliverable,
        Collection $teamLookup,
        Collection $agreementMemberUserIds
    ): bool {
        if ($user->pivot?->unassigned_at) {
            return false;
        }

        if (! $agreementMemberUserIds->contains((int) $user->id)) {
            return false;
        }

        $sourceTeamId = (int) ($user->pivot->source_team_id ?? 0);
        if ($sourceTeamId === 0) {
            return true;
        }

        $teamAssignedToDeliverable = $deliverable->teams
            ->filter(fn (Team $team) => ! $team->pivot?->unassigned_at)
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->contains($sourceTeamId);

        if (! $teamAssignedToDeliverable) {
            return false;
        }

        return $teamLookup->get($sourceTeamId)?->users->contains('id', $user->id) ?? false;
    }

    /**
     * @return Collection<int, int>
     */
    private static function buildAgreementMemberUserIds(Agreement $agreement): Collection
    {
        /** @var Collection<int, Team> $teams */
        $teams = $agreement->teams;

        return $agreement->users
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->merge(
                $teams->flatMap(
                    fn (Team $team) => $team->users->pluck('id')->map(fn ($id) => (int) $id)
                )
            )
            ->unique()
            ->values();
    }

    /**
     * @param  Collection<int, int>  $currentlyAssignedUserIds
     * @param  Collection<int, array<string, mixed>>  $contributorByUserId
     * @param  Collection<int, Team>  $teamLookup
     * @param  Collection<int, DeliverableContribution>|null  $contributions
     * @return Collection<int, mixed>
     */
    private static function buildPastAssignees(
        AgreementDeliverable $deliverable,
        Collection $currentlyAssignedUserIds,
        Collection $contributorByUserId,
        Collection $teamLookup,
        float $target,
        bool $asIndividualRows,
        ?Agreement $agreement = null,
        ?Collection $contributions = null,
        ?Carbon $from = null,
        ?Carbon $to = null
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
                $asIndividualRows,
                $agreement,
                $contributions,
                $from,
                $to
            ) {
                $assignedUser = $deliverable->users->firstWhere('id', $userId);
                $summary = $contributorByUserId->get($userId);
                $user = is_array($summary) ? ($summary['user'] ?? $assignedUser) : $assignedUser;

                if (! $user) {
                    return null;
                }

                $completed = (float) (is_array($summary) ? $summary['completed_value'] : 0);
                $teamName = is_array($summary)
                    ? ($summary['team_name'] ?? self::resolveFormerAssigneeTeamName($assignedUser, $teamLookup))
                    : self::resolveFormerAssigneeTeamName($assignedUser, $teamLookup);

                if ($asIndividualRows) {
                    $userContributions = $contributions
                        ? $contributions->where('contributor_user_id', $userId)->values()
                        : collect();
                    $userTarget = (float) (self::resolveUserPivotTarget($deliverable, $userId) ?? 0);

                    return self::memberRow(
                        $user,
                        array_merge($summary ?? [], ['team_name' => $teamName]),
                        $completed,
                        $userTarget,
                        false,
                        $agreement && $userTarget > 0
                            ? self::statusForAssignment($deliverable, $agreement, $completed, $userContributions, $from, $to, $userTarget)
                            : null
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
            ->filter(fn (array $row) => (float) $row['completed_value'] > 0)
            ->sortBy(fn (array $row) => $row['user']->name ?? '')
            ->values();
    }

    /**
     * @param  Collection<int, Team>  $teamLookup
     */
    private static function resolveFormerAssigneeTeamName(
        ?User $assignedUser,
        Collection $teamLookup
    ): ?string {
        if (! $assignedUser?->pivot?->source_team_id) {
            return null;
        }

        return $teamLookup->get((int) $assignedUser->pivot->source_team_id)?->name;
    }

    /**
     * @param  Collection<int, Team>  $teamLookup
     * @param  Collection<int, int>  $agreementMemberUserIds
     * @return Collection<int, int>
     */
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

        /** @var Collection<int, int> $activeTeamIds */
        $activeTeamIds = $deliverable->teams
            ->filter(fn (Team $team) => ! $team->pivot?->unassigned_at)
            ->pluck('id')
            ->map(fn ($id) => (int) $id);

        $teamMemberIds = $activeTeamIds
            ->flatMap(function (int $teamId) use ($teamLookup) {
                /** @var Collection<int, mixed> $memberIds */
                $memberIds = $teamLookup->get($teamId)?->users?->pluck('id') ?? collect();

                return $memberIds;
            })
            ->map(fn ($id) => (int) $id)
            ->filter(fn (int $userId) => $agreementMemberUserIds->contains($userId));

        return $directIds
            ->merge($teamMemberIds)
            ->unique()
            ->values();
    }

    /**
     * @param  Collection<int, Team>  $teamLookup
     */
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
            ->filter(fn (Team $team) => ! $team->pivot?->unassigned_at)
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

    /**
     * @param  Collection<int, DeliverableContribution>  $contributions
     * @param  Collection<int, Team>  $teamLookup
     * @param  Collection<int, int>  $agreementTeamIds
     * @return Collection<int, mixed>
     */
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
                /** @var Collection<int, DeliverableContribution> $userContributions */
                $first = $userContributions->first();
                if (! $first instanceof DeliverableContribution) {
                    return null;
                }
                $user = $first->contributor;

                if (! $user) {
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

    /**
     * @param  Collection<int, DeliverableContribution>  $userContributions
     * @param  Collection<int, Team>  $teamLookup
     * @param  Collection<int, int>  $agreementTeamIds
     */
    private static function resolveContributorTeamName(
        Collection $userContributions,
        AgreementDeliverable $deliverable,
        Collection $teamLookup,
        Collection $agreementTeamIds
    ): ?string {
        $userId = (int) $userContributions->first()?->contributor_user_id;
        $assignedUser = $deliverable->users->firstWhere('id', $userId);

        if ($assignedUser?->pivot?->source_team_id) {
            return $teamLookup->get((int) $assignedUser->pivot->source_team_id)?->name;
        }

        foreach ($userContributions as $contribution) {
            $history = $contribution->activityHistory;
            if (! $history || empty($history->team_ids_snapshot)) {
                continue;
            }

            /** @var list<mixed> $snapshotTeamIdsInput */
            $snapshotTeamIdsInput = $history->team_ids_snapshot;
            $snapshotTeamIds = collect($snapshotTeamIdsInput)->map(fn ($id) => (int) $id);
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

    private static function contributionIsInWindow(
        DeliverableContribution $contribution,
        ?Carbon $from,
        ?Carbon $to
    ): bool {
        if ($from === null && $to === null) {
            return true;
        }

        $activityDate = $contribution->activityHistory?->activity_date;
        if (! $activityDate) {
            return false;
        }

        $date = Carbon::parse($activityDate)->startOfDay();

        if ($from && $date->lt($from->copy()->startOfDay())) {
            return false;
        }

        if ($to && $date->gt($to->copy()->startOfDay())) {
            return false;
        }

        return true;
    }

    /**
     * @param  Collection<int, DeliverableContribution>  $contributions
     */
    private static function statusForAssignment(
        AgreementDeliverable $deliverable,
        Agreement $agreement,
        float $completed,
        Collection $contributions,
        ?Carbon $from,
        ?Carbon $to,
        ?float $targetOverride = null
    ): ?DeliverableStatus {
        $target = $targetOverride ?? (float) ($deliverable->target_quantity ?? 0);
        if ($target <= 0) {
            return null;
        }

        $startDate = CarbonDate::parse($agreement->start_date);
        $agreementEnd = CarbonDate::parse($agreement->extension_end_date ?? $agreement->end_date);
        if (! $startDate || ! $agreementEnd) {
            return DeliverableStatus::NotApplicable;
        }

        $today = now()->toDateString();
        if ($startDate->toDateString() > $today) {
            return DeliverableStatus::NotApplicable;
        }

        if ($deliverable->metric_type === 'completion' && $target == 1) {
            if ($completed >= 1) {
                return DeliverableStatus::Complete;
            }

            if ($contributions->contains(fn (DeliverableContribution $contribution) => $contribution->not_yet_complete)) {
                return DeliverableStatus::ProgressMade;
            }

            return DeliverableStatus::NoProgressMade;
        }

        $expected = self::expectedQuantity($deliverable, $agreement, $from, $to, $target);
        if ($completed >= $expected) {
            return DeliverableStatus::OnTrack;
        }

        if ($completed >= 0.5 * $expected) {
            return DeliverableStatus::NeedsAttention;
        }

        return DeliverableStatus::OffTrack;
    }

    private static function expectedQuantity(
        AgreementDeliverable $deliverable,
        Agreement $agreement,
        ?Carbon $from,
        ?Carbon $to,
        ?float $targetOverride = null
    ): float {
        $target = $targetOverride ?? (float) ($deliverable->target_quantity ?? 0);
        $agreementStart = CarbonDate::parse($agreement->start_date)?->copy()->startOfDay();
        $agreementEnd = CarbonDate::parse($agreement->extension_end_date ?? $agreement->end_date)?->copy()->startOfDay();
        if (! $agreementStart || ! $agreementEnd) {
            return 0;
        }
        $durationDays = self::inclusiveDayCount($agreementStart, $agreementEnd);
        if ($durationDays <= 0) {
            return 0;
        }

        $windowStart = $from?->copy()->startOfDay() ?? $agreementStart;
        $windowEnd = $to?->copy()->startOfDay() ?? $agreementEnd;
        $clipStart = $windowStart->greaterThan($agreementStart) ? $windowStart : $agreementStart;
        $clipEnd = $windowEnd->lessThan($agreementEnd) ? $windowEnd : $agreementEnd;
        $today = now()->startOfDay();
        $elapsedEnd = $today->lessThan($clipEnd) ? $today : $clipEnd;

        $overlapDays = $elapsedEnd->lt($clipStart)
            ? 0
            : self::inclusiveDayCount($clipStart, $elapsedEnd);

        $raw = $target * ($overlapDays / $durationDays);
        $decimals = $deliverable->metric_type === 'time' ? 1 : 0;

        return round($raw, $decimals);
    }

    private static function inclusiveDayCount(Carbon $start, Carbon $end): int
    {
        $start = $start->copy()->startOfDay();
        $end = $end->copy()->startOfDay();

        if ($end->lt($start)) {
            return 0;
        }

        return (int) $start->diffInDays($end) + 1;
    }
}
