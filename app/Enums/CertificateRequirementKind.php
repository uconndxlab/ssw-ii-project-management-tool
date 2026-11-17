<?php

namespace App\Enums;

enum CertificateRequirementKind: string
{
    case ActivityCount = 'activity_count';
    case ToolSubmission = 'tool_submission';
    case TrainingSections = 'training_sections';
    case Attestation = 'attestation';

    /** @return list<string> */
    public static function values(): array
    {
        return array_map(fn (self $case) => $case->value, self::cases());
    }

    public function label(): string
    {
        return match ($this) {
            self::ActivityCount => 'Activity work',
            self::ToolSubmission => 'Tool submissions',
            self::TrainingSections => 'Training sections',
            self::Attestation => 'Coach confirmation',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::ActivityCount => 'Counts activity logs the candidate was tagged on.',
            self::ToolSubmission => 'Counts coach-recorded submissions of a certification tool.',
            self::TrainingSections => 'Counts the sections of a training completed across coach-recorded submissions.',
            self::Attestation => 'A coach checks this off by hand; nothing is tracked automatically.',
        };
    }

    /** Tool submissions and training sections both count submissions of one instrument. */
    public function usesInstrument(): bool
    {
        return $this === self::ToolSubmission || $this === self::TrainingSections;
    }

    public function usesClassification(): bool
    {
        return $this !== self::Attestation;
    }
}
