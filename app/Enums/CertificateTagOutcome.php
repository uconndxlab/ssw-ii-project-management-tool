<?php

namespace App\Enums;

enum CertificateTagOutcome: string
{
    case Passed = 'passed';
    case NotPassed = 'not_passed';
    case NotScored = 'not_scored';

    /** @return list<string> */
    public static function values(): array
    {
        return array_map(fn (self $case) => $case->value, self::cases());
    }

    public function label(): string
    {
        return match ($this) {
            self::Passed => 'Passed',
            self::NotPassed => 'Did Not Pass',
            self::NotScored => 'Not Scored',
        };
    }

    public function tone(): string
    {
        return match ($this) {
            self::Passed => 'success',
            self::NotPassed => 'danger',
            self::NotScored => 'muted',
        };
    }
}
