<?php

namespace App\Support;

use App\Models\Activity;
use App\Models\ActivityType;

class ActivityTypeDuration
{
    public const UNIT_NONE = 'none';
    public const UNIT_HOURS = 'hours';
    public const UNIT_DAYS = 'days';

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
