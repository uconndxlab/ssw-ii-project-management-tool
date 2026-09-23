<?php

namespace App\Support;

use App\Models\Agreement;
use App\Models\AgreementActivityHistory;
use App\Models\AgreementDeliverable;
use App\Models\DeliverableContribution;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class DeliverableActivityHistogram
{
    /**
     * @return array{
     *     granularity: string,
     *     buckets: list<array{label: string, start: string, end: string, count: int}>
     * }
     */
    public static function buildAgreementBuckets(Agreement $agreement): array
    {
        $contributions = $agreement->deliverables
            ->flatMap(fn (AgreementDeliverable $deliverable) => $deliverable->contributions)
            ->filter(fn (DeliverableContribution $contribution) => ! $contribution->cancelled)
            ->filter(fn (DeliverableContribution $contribution) => $contribution->activityHistory instanceof AgreementActivityHistory);

        $dates = $contributions
            ->map(function (DeliverableContribution $contribution) {
                $history = $contribution->activityHistory;
                if (! $history instanceof AgreementActivityHistory) {
                    return null;
                }

                return CarbonDate::parse($history->activity_date)?->startOfDay();
            })
            ->filter()
            ->values();

        $spanStart = CarbonDate::parse($agreement->start_date)?->copy()->startOfDay();
        $spanEnd = CarbonDate::parse($agreement->extension_end_date ?? $agreement->end_date)?->copy()->startOfDay();

        if ($dates->isNotEmpty()) {
            $minDate = $dates->min();
            $maxDate = $dates->max();

            if ($minDate instanceof Carbon && (! $spanStart || $minDate->lt($spanStart))) {
                $spanStart = $minDate->copy();
            }

            if ($maxDate instanceof Carbon && (! $spanEnd || $maxDate->gt($spanEnd))) {
                $spanEnd = $maxDate->copy();
            }
        }

        if (! $spanStart || ! $spanEnd) {
            return [
                'granularity' => 'weekly',
                'buckets' => [],
            ];
        }

        $granularity = self::resolveGranularity($spanStart, $spanEnd);
        $bucketRanges = self::buildBucketRanges($spanStart, $spanEnd, $granularity);
        $countsByStart = $dates
            ->groupBy(fn (Carbon $date) => self::bucketKey($date, $granularity))
            ->map(fn (Collection $group) => $group->count());

        $buckets = [];
        foreach ($bucketRanges as $range) {
            $buckets[] = [
                'label' => $range['label'],
                'start' => $range['start']->toDateString(),
                'end' => $range['end']->toDateString(),
                'count' => (int) ($countsByStart->get($range['key']) ?? 0),
            ];
        }

        return [
            'granularity' => $granularity,
            'buckets' => $buckets,
        ];
    }

    /**
     * @return array{
     *     granularity: string,
     *     target: float,
     *     buckets: list<array{label: string, start: string, end: string, credited: float, cumulative: float, pace: float}>
     * }
     */
    public static function buildDeliverableBurnUp(
        AgreementDeliverable $deliverable,
        Agreement $agreement,
        float $target
    ): array {
        $contributions = $deliverable->contributions
            ->filter(fn (DeliverableContribution $contribution) => ! $contribution->cancelled)
            ->filter(fn (DeliverableContribution $contribution) => $contribution->activityHistory instanceof AgreementActivityHistory);

        $spanStart = CarbonDate::parse($agreement->start_date)?->copy()->startOfDay();
        $spanEnd = CarbonDate::parse($agreement->extension_end_date ?? $agreement->end_date)?->copy()->startOfDay();

        $contributionDates = $contributions
            ->map(function (DeliverableContribution $contribution) {
                $history = $contribution->activityHistory;
                if (! $history instanceof AgreementActivityHistory) {
                    return null;
                }

                return CarbonDate::parse($history->activity_date)?->startOfDay();
            })
            ->filter()
            ->values();

        if ($contributionDates->isNotEmpty()) {
            $minDate = $contributionDates->min();
            $maxDate = $contributionDates->max();

            if ($minDate instanceof Carbon && (! $spanStart || $minDate->lt($spanStart))) {
                $spanStart = $minDate->copy();
            }

            if ($maxDate instanceof Carbon && (! $spanEnd || $maxDate->gt($spanEnd))) {
                $spanEnd = $maxDate->copy();
            }
        }

        if (! $spanStart || ! $spanEnd) {
            return [
                'granularity' => 'weekly',
                'target' => $target,
                'buckets' => [],
            ];
        }

        $granularity = self::resolveGranularity($spanStart, $spanEnd);
        $bucketRanges = self::buildBucketRanges($spanStart, $spanEnd, $granularity);
        $creditedByStart = $contributions
            ->map(function (DeliverableContribution $contribution) use ($granularity) {
                $history = $contribution->activityHistory;
                if (! $history instanceof AgreementActivityHistory) {
                    return null;
                }

                $activityDate = CarbonDate::parse($history->activity_date);
                if ($activityDate === null) {
                    return null;
                }

                return [
                    'key' => self::bucketKey($activityDate->startOfDay(), $granularity),
                    'contribution' => $contribution,
                ];
            })
            ->filter()
            ->groupBy('key')
            ->map(fn (Collection $group) => round($group->sum(fn (array $row) => self::creditedValue($deliverable, $row['contribution'])), 2));

        $totalDays = max(1, $spanStart->diffInDays($spanEnd) + 1);
        $cumulative = 0.0;
        $buckets = [];

        foreach ($bucketRanges as $index => $range) {
            $credited = (float) ($creditedByStart->get($range['key']) ?? 0);
            $cumulative = round($cumulative + $credited, 2);
            $elapsedDays = max(0, $spanStart->diffInDays($range['end']) + 1);
            $pace = $target > 0 ? round(($elapsedDays / $totalDays) * $target, 2) : 0.0;

            $buckets[] = [
                'label' => $range['label'],
                'start' => $range['start']->toDateString(),
                'end' => $range['end']->toDateString(),
                'credited' => $credited,
                'cumulative' => $cumulative,
                'pace' => $pace,
            ];
        }

        return [
            'granularity' => $granularity,
            'target' => $target,
            'buckets' => $buckets,
        ];
    }

    private static function creditedValue(AgreementDeliverable $deliverable, DeliverableContribution $contribution): float
    {
        if ($deliverable->metric_type === 'time') {
            $isAllotted = ($deliverable->time_basis ?? 'observed') === 'allotted';
            if ($isAllotted) {
                $unit = ActivityTypeDuration::resolveAllottedTimeUnitForDeliverable($deliverable);

                return $unit === ActivityTypeDuration::UNIT_DAYS
                    ? (float) $contribution->credited_allotted_days
                    : (float) $contribution->credited_allotted_hours;
            }

            return (float) $contribution->credited_hours;
        }

        return (float) $contribution->credited_units;
    }

    private static function resolveGranularity(Carbon $start, Carbon $end): string
    {
        $days = $start->diffInDays($end) + 1;

        if ($days <= 90) {
            return 'daily';
        }

        if ($days <= 730) {
            return 'weekly';
        }

        return 'monthly';
    }

    /**
     * @return list<array{key: string, label: string, start: Carbon, end: Carbon}>
     */
    private static function buildBucketRanges(Carbon $start, Carbon $end, string $granularity): array
    {
        $ranges = [];
        $cursor = $start->copy()->startOfDay();

        while ($cursor->lte($end)) {
            $bucketStart = $cursor->copy();
            $bucketEnd = match ($granularity) {
                'daily' => $cursor->copy()->endOfDay(),
                'weekly' => $cursor->copy()->endOfWeek(),
                default => $cursor->copy()->endOfMonth(),
            };

            if ($bucketEnd->gt($end)) {
                $bucketEnd = $end->copy()->endOfDay();
            }

            $ranges[] = [
                'key' => self::bucketKey($bucketStart, $granularity),
                'label' => self::bucketLabel($bucketStart, $bucketEnd, $granularity),
                'start' => $bucketStart->copy()->startOfDay(),
                'end' => $bucketEnd->copy()->startOfDay(),
            ];

            $cursor = match ($granularity) {
                'daily' => $cursor->addDay()->startOfDay(),
                'weekly' => $cursor->addWeek()->startOfWeek(),
                default => $cursor->addMonthNoOverflow()->startOfMonth(),
            };
        }

        return $ranges;
    }

    private static function bucketKey(Carbon $date, string $granularity): string
    {
        return match ($granularity) {
            'daily' => $date->toDateString(),
            'weekly' => $date->copy()->startOfWeek()->toDateString(),
            default => $date->copy()->startOfMonth()->format('Y-m'),
        };
    }

    private static function bucketLabel(Carbon $start, Carbon $end, string $granularity): string
    {
        return match ($granularity) {
            'daily' => $start->format('M j'),
            'weekly' => $start->format('M j').'–'.$end->format('M j'),
            default => $start->format('M Y'),
        };
    }
}
