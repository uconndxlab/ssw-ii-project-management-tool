<?php

namespace App\Support;

use App\Models\Activity;
use App\Models\ActivityType;
use App\Models\AgreementDeliverable;
use Illuminate\Support\Collection;

class ActivityTypeDuration
{
    public const UNIT_NONE = 'none';
    public const UNIT_HOURS = 'hours';
    public const UNIT_DAYS = 'days';

    /**
     * @param \Illuminate\Support\Collection<int, ActivityType> $activityTypes
     * @param array<int, int|string> $selectedProgramIds
     * @return \Illuminate\Support\Collection<int, ActivityType>
     */
    public static function filterActivityTypesInScope(
        Collection $activityTypes,
        ?int $contactFamilyId,
        ?int $activityTypeId,
        array $selectedProgramIds
    ): Collection {
        $selectedProgramIdSet = collect($selectedProgramIds)
            ->filter(fn ($id) => $id !== null && $id !== '')
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values();

        return $activityTypes->filter(function (ActivityType $type) use ($contactFamilyId, $activityTypeId, $selectedProgramIdSet) {
            if ($activityTypeId && (int) $type->id !== (int) $activityTypeId) {
                return false;
            }

            if ($contactFamilyId && (int) $type->contact_family_id !== (int) $contactFamilyId) {
                return false;
            }

            $typeProgramIds = $type->relationLoaded('programs')
                ? $type->programs->pluck('id')->map(fn ($id) => (int) $id)->values()
                : collect();

            if ($typeProgramIds->isEmpty()) {
                return true;
            }

            if ($selectedProgramIdSet->isEmpty()) {
                return false;
            }

            return $typeProgramIds->intersect($selectedProgramIdSet)->isNotEmpty();
        })->values();
    }

    /**
     * @param \Illuminate\Support\Collection<int, ActivityType> $activityTypesInScope
     */
    public static function selectionSupportsAllottedTime(
        ?int $contactFamilyId,
        ?int $activityTypeId,
        Collection $activityTypesInScope
    ): bool {
        if (!$contactFamilyId) {
            return false;
        }

        if ($activityTypeId) {
            $activityType = $activityTypesInScope->firstWhere('id', (int) $activityTypeId);

            return $activityType && self::fromActivityType($activityType)->hasDuration();
        }

        return $activityTypesInScope->contains(function (ActivityType $type) {
            return self::fromActivityType($type)->hasDuration();
        });
    }

    /**
     * @param \Illuminate\Support\Collection<int, ActivityType> $activityTypesInScope
     * @return array{has_days: bool, has_hours: bool, is_mixed: bool, allowed_units: array<int, string>}
     */
    public static function resolveAllottedUnitsForSelection(
        ?int $contactFamilyId,
        ?int $activityTypeId,
        Collection $activityTypesInScope
    ): array {
        if (!$contactFamilyId) {
            return [
                'has_days' => false,
                'has_hours' => false,
                'is_mixed' => false,
                'allowed_units' => [],
            ];
        }

        if ($activityTypeId) {
            $activityType = $activityTypesInScope->firstWhere('id', (int) $activityTypeId);
            $duration = self::fromActivityType($activityType);
            $hasDays = $duration->unit() === self::UNIT_DAYS;
            $hasHours = $duration->unit() === self::UNIT_HOURS;

            return [
                'has_days' => $hasDays,
                'has_hours' => $hasHours,
                'is_mixed' => false,
                'allowed_units' => array_values(array_filter([
                    $hasDays ? self::UNIT_DAYS : null,
                    $hasHours ? self::UNIT_HOURS : null,
                ])),
            ];
        }

        $hasDays = false;
        $hasHours = false;

        foreach ($activityTypesInScope as $type) {
            $duration = self::fromActivityType($type);
            if ($duration->unit() === self::UNIT_DAYS) {
                $hasDays = true;
            }
            if ($duration->unit() === self::UNIT_HOURS) {
                $hasHours = true;
            }
        }

        return [
            'has_days' => $hasDays,
            'has_hours' => $hasHours,
            'is_mixed' => $hasDays && $hasHours,
            'allowed_units' => array_values(array_filter([
                $hasDays ? self::UNIT_DAYS : null,
                $hasHours ? self::UNIT_HOURS : null,
            ])),
        ];
    }

    /**
     * @param \Illuminate\Support\Collection<int, ActivityType> $activityTypesInScope
     */
    public static function normalizeAllottedTimeUnit(
        ?int $contactFamilyId,
        ?int $activityTypeId,
        Collection $activityTypesInScope,
        ?string $requestedUnit
    ): ?string {
        $units = self::resolveAllottedUnitsForSelection($contactFamilyId, $activityTypeId, $activityTypesInScope);
        $allowed = $units['allowed_units'];

        if (empty($allowed)) {
            return null;
        }

        if (count($allowed) === 1) {
            return $allowed[0];
        }

        if ($requestedUnit && in_array($requestedUnit, $allowed, true)) {
            return $requestedUnit;
        }

        return null;
    }

    public static function resolveAllottedTimeUnitForDeliverable(AgreementDeliverable $deliverable): ?string
    {
        if ($deliverable->metric_type !== 'time' || ($deliverable->time_basis ?? 'observed') !== 'allotted') {
            return null;
        }

        if ($deliverable->allotted_time_unit) {
            return $deliverable->allotted_time_unit;
        }

        $duration = self::fromActivityType($deliverable->activityType);
        if ($duration->unit() === self::UNIT_DAYS) {
            return self::UNIT_DAYS;
        }
        if ($duration->unit() === self::UNIT_HOURS) {
            return self::UNIT_HOURS;
        }

        return null;
    }

    public static function targetUnitLabel(?string $unit): string
    {
        return $unit === self::UNIT_DAYS ? 'Days' : 'Hours';
    }

    public static function fromActivityType(?ActivityType $activityType): self
    {
        if (!$activityType) {
            return new self(self::UNIT_NONE, null);
        }

        if ((float) $activityType->duration_days > 0) {
            return new self(self::UNIT_DAYS, (float) $activityType->duration_days);
        }

        if ((float) $activityType->duration_hours > 0) {
            return new self(self::UNIT_HOURS, (float) $activityType->duration_hours);
        }

        return new self(self::UNIT_NONE, null);
    }

    public static function fromActivity(Activity $activity): self
    {
        if ((float) ($activity->allotted_duration_days ?? 0) > 0) {
            return new self(self::UNIT_DAYS, (float) $activity->allotted_duration_days);
        }

        if ((float) ($activity->allotted_duration_hours ?? 0) > 0) {
            return new self(self::UNIT_HOURS, (float) $activity->allotted_duration_hours);
        }

        return new self(self::UNIT_NONE, null);
    }

    public static function snapshotFromActivityType(ActivityType $activityType): array
    {
        $duration = self::fromActivityType($activityType);

        return [
            'allotted_duration_hours' => $duration->unit() === self::UNIT_HOURS ? $duration->value() : null,
            'allotted_duration_days' => $duration->unit() === self::UNIT_DAYS ? $duration->value() : null,
        ];
    }

    public function __construct(
        private readonly string $unit,
        private readonly ?float $value
    ) {
    }

    public function hasDuration(): bool
    {
        return $this->unit !== self::UNIT_NONE && $this->value !== null && $this->value > 0;
    }

    public function unit(): string
    {
        return $this->unit;
    }

    public function value(): ?float
    {
        return $this->value;
    }

    public function totalForCompletionCount(int $completionCount): array
    {
        if (!$this->hasDuration()) {
            return [
                'allotted_hours' => null,
                'allotted_days' => null,
            ];
        }

        $total = $this->value * $completionCount;

        return [
            'allotted_hours' => $this->unit === self::UNIT_HOURS ? $total : null,
            'allotted_days' => $this->unit === self::UNIT_DAYS ? $total : null,
        ];
    }

    public function formatLabel(): ?string
    {
        if (!$this->hasDuration()) {
            return null;
        }

        $value = rtrim(rtrim(number_format($this->value, 1, '.', ''), '0'), '.');

        return $this->unit === self::UNIT_DAYS
            ? $value . ' ' . ($this->value == 1 ? 'day' : 'days')
            : $value . ' ' . ($this->value == 1 ? 'hour' : 'hours');
    }

    public function formatTotalLabel(int $completionCount): ?string
    {
        if (!$this->hasDuration()) {
            return null;
        }

        $total = $this->value * $completionCount;
        $formatted = rtrim(rtrim(number_format($total, 1, '.', ''), '0'), '.');

        return $this->unit === self::UNIT_DAYS
            ? $formatted . ' ' . ($total == 1 ? 'day' : 'days')
            : $formatted . ' ' . ($total == 1 ? 'hour' : 'hours');
    }
}
