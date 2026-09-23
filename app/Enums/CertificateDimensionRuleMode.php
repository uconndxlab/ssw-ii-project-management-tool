<?php

namespace App\Enums;

enum CertificateDimensionRuleMode: string
{
    case Coverage = 'coverage';
    case Quota = 'quota';

    /** @return list<string> */
    public static function values(): array
    {
        return array_map(fn (self $case) => $case->value, self::cases());
    }

    public function label(): string
    {
        return match ($this) {
            self::Coverage => 'Must Cover Each',
            self::Quota => 'Minimum Count',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::Coverage => 'At least one passing submission for every selected option, e.g. span phases 2-4.',
            self::Quota => 'At least this many passing submissions at the selected option, e.g. 4 of 6 full reviews.',
        };
    }

    public function requiresMinCount(): bool
    {
        return $this === self::Quota;
    }
}
