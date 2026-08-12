<?php

namespace App\Enums;

enum ProgramScopeMode: string
{
    case All = 'all';
    case Specific = 'specific';
    case None = 'none';

    public static function values(): array
    {
        return array_map(fn (self $case) => $case->value, self::cases());
    }

    public function label(): string
    {
        return match ($this) {
            self::All => 'All',
            self::Specific => 'Specific',
            self::None => 'None',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::All => 'Applies to all programs.',
            self::Specific => 'Applies only to selected programs.',
            self::None => 'Does not apply to any programs.',
        };
    }
}
