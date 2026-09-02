<?php

namespace App\Enums;

enum ActivityAction: string
{
    case Create = 'create';
    case Update = 'update';
    case Duplicate = 'duplicate';
    case Delete = 'delete';

    public static function values(): array
    {
        return array_map(fn (self $case) => $case->value, self::cases());
    }

    public function label(): string
    {
        return match ($this) {
            self::Create => 'Created',
            self::Update => 'Updated',
            self::Duplicate => 'Duplicated',
            self::Delete => 'Deleted',
        };
    }

    public function relatedPreposition(): ?string
    {
        return match ($this) {
            self::Create => 'from',
            self::Duplicate => 'to',
            default => null,
        };
    }
}
