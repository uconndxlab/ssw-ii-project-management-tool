<?php

namespace App\Support;

use App\Enums\DeliverableStatus;
use App\Models\Agreement;
use App\Models\Organization;
use App\Models\Team;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Collection;

final class AgreementOverview
{
    public const ATTENTION_LIMIT = 4;

    public const MONTHLY_LIMIT = 24;

    public function __construct(private Agreement $agreement, private User $actor) {}

    /** @return array{label: string, tone: string} */
    public static function statusChip(DeliverableStatus $status): array
    {
        return [
            'label' => ucfirst(str_replace('_', ' ', $status->value)),
            'tone' => match ($status) {
                DeliverableStatus::OffTrack => 'danger',
                DeliverableStatus::NeedsAttention => 'warning',
                DeliverableStatus::ProgressMade => 'progress',
                DeliverableStatus::OnTrack, DeliverableStatus::Complete => 'success',
                DeliverableStatus::NoProgressMade, DeliverableStatus::NotApplicable => 'neutral',
            },
        ];
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $deliverableGroups
     * @return array{total: int, buckets: list<array{key: string, label: string, tone: string, count: int}>, blocks: list<array{tone: string, count: int}>, attention: list<array{deliverable_id: int, bucket: string, title: string, meta: string, status_label: string, status_tone: string}>, note: ?string}
     */
    public function deliverableSummary(Collection $deliverableGroups): array
    {
        $items = $deliverableGroups
            ->flatMap(fn (array $family): Collection => $family['activity_groups'])
            ->flatMap(fn (array $activity): Collection => $activity['program_groups'])
            ->flatMap(fn (array $program): Collection => $program['deliverables']);
        $definitions = [];
        foreach (['off_track', 'needs_attention', 'no_assignees', 'no_progress_made', 'progress_made', 'on_track', 'complete', 'no_target', 'not_applicable'] as $key) {
            $status = DeliverableStatus::tryFrom($key);
            $definitions[$key] = $status ? self::statusChip($status) : [
                'label' => $key === 'no_assignees' ? 'No assignees' : 'No target',
                'tone' => $key === 'no_assignees' ? 'warning' : 'neutral',
            ];
        }
        $grouped = $items->groupBy(function (array $item): string {
            if ($item['status'] instanceof DeliverableStatus) {
                return $item['status']->value;
            }
            if (! $item['has_target']) {
                return 'no_target';
            }

            // Only individual deliverables with nobody scored reach here (rollupIndividualStatus() returns null).
            return 'no_assignees';
        });
        $buckets = [];
        $toneCounts = array_fill_keys(['danger', 'warning', 'progress', 'success', 'neutral'], 0);
        foreach ($definitions as $key => $chip) {
            $count = $grouped->get($key, collect())->count();
            if ($count > 0) {
                $buckets[] = ['key' => $key, ...$chip, 'count' => $count];
                $toneCounts[$chip['tone']] += $count;
            }
        }
        $blocks = [];
        foreach ($toneCounts as $tone => $count) {
            if ($count > 0) {
                $blocks[] = ['tone' => $tone, 'count' => $count];
            }
        }
        $queues = [];
        foreach (['off_track', 'needs_attention', 'no_assignees'] as $key) {
            $queues[$key] = $grouped->get($key, collect())->sortBy(fn (array $item) => [
                $key === 'no_assignees' ? -($item['allocation_notices']['unassigned_remainder'] ?? 0) / $item['target'] : $item['percent'],
                mb_strtolower($this->attentionTitle($item)),
                $item['deliverable']->id,
            ])->values()->all();
        }
        $selected = array_fill_keys(array_keys($queues), []);
        $count = 0;
        do {
            $added = false;
            foreach ($queues as $key => &$queue) {
                if ($count < self::ATTENTION_LIMIT && $queue !== []) {
                    $selected[$key][] = array_shift($queue);
                    $count++;
                    $added = true;
                }
            }
            unset($queue);
        } while ($added && $count < self::ATTENTION_LIMIT);
        $attention = [];
        foreach ($selected as $key => $rows) {
            foreach ($rows as $item) {
                $unit = mb_strtolower($item['unit_label']);
                $quantity = $key === 'no_assignees'
                    ? DeliverableAssignmentTargets::formatQuantity($item['allocation_notices']['unassigned_remainder'] ?? 0)." {$unit} unassigned"
                    : DeliverableAssignmentTargets::formatQuantity($item['counted_completed_value']).' of '.DeliverableAssignmentTargets::formatQuantity($item['target'])." {$unit}";
                $attention[] = [
                    'deliverable_id' => (int) $item['deliverable']->id,
                    'bucket' => $key,
                    'title' => $this->attentionTitle($item),
                    'meta' => implode(' · ', array_filter([$item['deliverable']->contactFamily?->name, $item['metric_summary'], $quantity])),
                    'status_label' => $definitions[$key]['label'],
                    'status_tone' => $definitions[$key]['tone'],
                ];
            }
        }
        $note = null;
        if ($items->isNotEmpty() && $grouped->get('not_applicable', collect())->count() === $items->count()) {
            $start = CarbonDate::parse($this->agreement->start_date);
            $end = CarbonDate::parse($this->agreement->extension_end_date ?? $this->agreement->end_date);
            $note = ! $start || ! $end ? 'Statuses need start and end dates.' : 'Statuses start on '.$start->format('M j, Y').'.';
        }

        return ['total' => $items->count(), 'buckets' => $buckets, 'blocks' => $blocks, 'attention' => $attention, 'note' => $note];
    }

    /** @param array<string, mixed> $item */
    private function attentionTitle(array $item): string
    {
        $deliverable = $item['deliverable'];

        return $deliverable->activity_type_id !== null
            ? $deliverable->activityType->name
            : 'Any '.($deliverable->contact_family_id !== null ? $deliverable->contactFamily->name : 'Unspecified Activity Family').' activity';
    }

    /**
     * today_offset is where today falls across the whole range (0–1), or null when today is outside it.
     *
     * @return array{granularity: 'month'|'quarter', total: int, max: int, today_offset: ?float, buckets: list<array{key: string, label: string, short_label: string, count: int}>}
     */
    public function activityTimeline(): array
    {
        $dates = $this->agreement->activities()->pluck('engagement_date')->map(fn ($date) => Carbon::parse($date));
        $start = CarbonDate::parse($this->agreement->start_date);
        $end = CarbonDate::parse($this->agreement->extension_end_date ?? $this->agreement->end_date);
        if (! $start || ! $end) {
            $start = $dates->min();
            $end = $dates->max();
        }
        if (! $start || ! $end) {
            return ['granularity' => 'month', 'total' => 0, 'max' => 0, 'today_offset' => null, 'buckets' => []];
        }
        $start = $start->copy()->min($dates->min() ?? $start)->startOfMonth();
        $end = $end->copy()->max($dates->max() ?? $end)->startOfMonth();
        $quarterly = $start->diffInMonths($end) + 1 > self::MONTHLY_LIMIT;
        $counts = $dates->countBy(fn (Carbon $date) => $quarterly ? $date->year.'-Q'.$date->quarter : $date->format('Y-m'));
        $cursor = $quarterly ? $start->copy()->startOfQuarter() : $start->copy();
        $rangeStart = $cursor->copy();
        $multiYear = $start->year !== $end->year;
        $today = today();
        $buckets = [];
        while ($cursor <= $end) {
            $key = $quarterly ? $cursor->year.'-Q'.$cursor->quarter : $cursor->format('Y-m');
            $next = $cursor->copy()->addMonths($quarterly ? 3 : 1);
            $buckets[] = [
                'key' => $key,
                'label' => $quarterly ? 'Q'.$cursor->quarter.' '.$cursor->year : $cursor->format('F Y'),
                'short_label' => ($quarterly ? 'Q'.$cursor->quarter : $cursor->format('M'))
                    .($multiYear && ($buckets === [] || $cursor->month === 1) ? ' ’'.$cursor->format('y') : ''),
                'count' => $counts->get($key, 0),
            ];
            $cursor = $next;
        }

        $todayOffset = $today >= $rangeStart && $today < $cursor
            ? $rangeStart->diffInDays($today) / $rangeStart->diffInDays($cursor)
            : null;

        return ['granularity' => $quarterly ? 'quarter' : 'month', 'total' => $dates->count(), 'max' => max(array_column($buckets, 'count') ?: [0]), 'today_offset' => $todayOffset, 'buckets' => $buckets];
    }

    public function restrictedActivityCount(): int
    {
        return max(0, $this->agreement->activities()->count() - $this->agreement->activities()->visibleTo($this->actor)->count());
    }

    /** @return Collection<int, array{user: User, teams: list<string>, is_pi: bool, href: ?string}> */
    public function staff(): Collection
    {
        $piIds = $this->agreement->principalInvestigators->modelKeys();

        return $this->agreement->users->toBase()
            ->concat($this->agreement->teams->flatMap(fn (Team $team) => $team->users))
            ->unique('id')
            ->map(fn (User $user) => [
                'user' => $user,
                'teams' => array_values($this->agreement->teams->filter(fn (Team $team) => $team->users->contains('id', $user->id))->map(fn (Team $team): string => $team->name)->sort(SORT_NATURAL | SORT_FLAG_CASE)->all()),
                'is_pi' => in_array($user->id, $piIds, true),
                'href' => UserProfileLink::route($user),
            ])
            ->sortBy(fn (array $row) => [! $row['is_pi'], mb_strtolower($row['user']->name)])
            ->values();
    }

    /** @return Collection<int, Organization> */
    public function organizations(): Collection
    {
        return $this->agreement->organizations->sortBy(fn (Organization $organization) => [
            $organization->pivot?->payor_source ? 0 : ($organization->pivot?->recipient ? 1 : 2),
            mb_strtolower($organization->name),
        ])->values();
    }
}
