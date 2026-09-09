<?php

namespace App\Support;

use App\Enums\DeliverableStatus;
use App\Models\AgreementDeliverable;
use App\Models\Team;
use App\Models\User;
use Illuminate\Support\Collection;

class DeliverableAssignmentTargets
{
    public static function normalizeQuantity(mixed $value): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }

        return round((float) $value, 2);
    }

    public static function isIndividual(AgreementDeliverable $deliverable): bool
    {
        return $deliverable->contribution_basis === 'user'
            && $deliverable->user_grouping_mode === 'individual';
    }

    public static function isJoint(AgreementDeliverable $deliverable): bool
    {
        return $deliverable->contribution_basis === 'user'
            && $deliverable->user_grouping_mode === 'joint';
    }

    public static function isContact(AgreementDeliverable $deliverable): bool
    {
        return $deliverable->contribution_basis === 'contact';
    }

    public static function usesRecommendedTargets(AgreementDeliverable $deliverable): bool
    {
        return self::isJoint($deliverable) || self::isContact($deliverable);
    }

    /**
     * @param  array<int|string, mixed>  $userTargets
     * @param  array<int|string, mixed>  $teamTargets
     * @return array{
     *     allocated: float,
     *     remainder: float,
     *     is_balanced: bool,
     *     is_over_assigned: bool,
     *     team_warnings: array<int, array{team_id: int, team_name: string, team_target: ?float, member_sum: float}>
     * }
     */
    public static function summarizeFromRow(
        AgreementDeliverable $deliverable,
        array $userIds,
        array $teamIds,
        array $userTargets,
        array $teamTargets,
        Collection $teamLookup
    ): array {
        $total = (float) ($deliverable->target_quantity ?? 0);
        $liveUserIds = collect($userIds)->map(fn ($id) => (int) $id)->unique()->values();

        $allocated = $liveUserIds
            ->map(fn (int $userId) => self::normalizeQuantity($userTargets[$userId] ?? $userTargets[(string) $userId] ?? null))
            ->filter(fn (?float $value) => $value !== null)
            ->sum();

        $allocated = round((float) $allocated, 2);
        $remainder = round($total - $allocated, 2);

        $teamWarnings = [];
        foreach (collect($teamIds)->map(fn ($id) => (int) $id)->unique() as $teamId) {
            $team = $teamLookup->get($teamId);
            if (! $team) {
                continue;
            }

            $teamTarget = self::normalizeQuantity($teamTargets[$teamId] ?? $teamTargets[(string) $teamId] ?? null);
            $memberIds = $team->users->pluck('id')->map(fn ($id) => (int) $id);
            $memberSum = round($memberIds
                ->intersect($liveUserIds)
                ->map(fn (int $userId) => self::normalizeQuantity($userTargets[$userId] ?? $userTargets[(string) $userId] ?? null))
                ->filter(fn (?float $value) => $value !== null)
                ->sum(), 2);

            if ($teamTarget !== null && abs($teamTarget - $memberSum) > 0.009) {
                $teamWarnings[] = [
                    'team_id' => $teamId,
                    'team_name' => $team->name,
                    'team_target' => $teamTarget,
                    'member_sum' => $memberSum,
                ];
            }
        }

        return [
            'allocated' => $allocated,
            'remainder' => $remainder,
            'is_balanced' => $total <= 0 || abs($remainder) <= 0.009,
            'is_over_assigned' => $remainder < -0.009,
            'team_warnings' => $teamWarnings,
        ];
    }

    /**
     * @param  Collection<int, User>  $liveUsers
     * @param  Collection<int, Team>  $liveTeams
     */
    public static function summarizeFromDeliverable(
        AgreementDeliverable $deliverable,
        Collection $liveUsers,
        Collection $liveTeams,
        Collection $teamLookup
    ): array {
        $userTargets = [];
        foreach ($liveUsers as $user) {
            $userTargets[(int) $user->id] = $user->pivot->target_quantity;
        }

        $teamTargets = [];
        foreach ($liveTeams as $team) {
            $teamTargets[(int) $team->id] = $team->pivot->target_quantity;
        }

        return self::summarizeFromRow(
            $deliverable,
            $liveUsers->pluck('id')->all(),
            $liveTeams->pluck('id')->all(),
            $userTargets,
            $teamTargets,
            $teamLookup
        );
    }

    public static function hasAllocationMismatch(AgreementDeliverable $deliverable, array $summary): bool
    {
        if (self::isIndividual($deliverable)) {
            return ! $summary['is_balanced'];
        }

        if (self::usesRecommendedTargets($deliverable)) {
            return ! $summary['is_balanced'] || ! empty($summary['team_warnings']);
        }

        return false;
    }

    /**
     * @param  Collection<int, DeliverableStatus|null>  $statuses
     */
    public static function rollupIndividualStatus(Collection $statuses): ?DeliverableStatus
    {
        $scored = $statuses->filter()->values();

        if ($scored->isEmpty()) {
            return null;
        }

        $priority = [
            DeliverableStatus::OffTrack->value => 5,
            DeliverableStatus::NeedsAttention->value => 4,
            DeliverableStatus::NoProgressMade->value => 3,
            DeliverableStatus::ProgressMade->value => 2,
            DeliverableStatus::OnTrack->value => 1,
            DeliverableStatus::Complete->value => 0,
            DeliverableStatus::NotApplicable->value => -1,
        ];

        return $scored->sortByDesc(fn (?DeliverableStatus $status) => $priority[$status?->value ?? ''] ?? -2)->first();
    }

    /**
     * @param  Collection<int, DeliverableStatus|null>  $statuses
     */
    public static function countOnTrackStatuses(Collection $statuses): array
    {
        $scored = $statuses->filter()->values();
        $onTrackValues = [
            DeliverableStatus::OnTrack->value,
            DeliverableStatus::Complete->value,
            DeliverableStatus::ProgressMade->value,
        ];

        $onTrack = $scored->filter(fn (?DeliverableStatus $status) => in_array($status?->value, $onTrackValues, true))->count();

        return [
            'on_track' => $onTrack,
            'total' => $scored->count(),
        ];
    }

    /**
     * @param  array<int, float>  $personTargets keyed by user id
     * @param  array<int, float>  $personCompleted keyed by user id
     */
    public static function countedTotalTowardTarget(
        array $personTargets,
        array $personCompleted
    ): float {
        $counted = 0.0;

        foreach ($personCompleted as $userId => $completed) {
            $target = (float) ($personTargets[(int) $userId] ?? 0);
            if ($target <= 0) {
                continue;
            }

            $counted += min((float) $completed, $target);
        }

        return round($counted, 2);
    }

    /**
     * Distribute a total evenly across live assignees.
     *
     * @param  list<int>  $userIds
     * @return array<int, float>
     */
    public static function splitEvenly(float $total, array $userIds, string $metricType): array
    {
        if ($total <= 0 || $userIds === []) {
            return [];
        }

        $count = count($userIds);
        $step = $metricType === 'completion' ? 1.0 : 0.1;
        $totalSteps = (int) round($total / $step);
        $baseSteps = intdiv($totalSteps, $count);
        $remainderSteps = $totalSteps % $count;

        $result = [];
        foreach ($userIds as $index => $userId) {
            $steps = $baseSteps + ($index < $remainderSteps ? 1 : 0);
            $result[(int) $userId] = round($steps * $step, 2);
        }

        return $result;
    }
}
