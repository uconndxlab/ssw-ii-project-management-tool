<?php

namespace App\Enums;

enum CertificateGroupSatisfyMode: string
{
    case All = 'all';
    case Any = 'any';
    case NOf = 'n_of';

    public static function values(): array
    {
        return array_map(fn (self $case) => $case->value, self::cases());
    }

    public function label(): string
    {
        return match ($this) {
            self::All => 'All Requirements',
            self::Any => 'Any One Requirement',
            self::NOf => 'A Set Number',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::All => 'Every requirement in this group must be met.',
            self::Any => 'Meeting any single requirement satisfies the group.',
            self::NOf => 'Meet a specified number of the requirements in this group, e.g. 3 of 5.',
        };
    }

    public function requiresCount(): bool
    {
        return $this === self::NOf;
    }
}
