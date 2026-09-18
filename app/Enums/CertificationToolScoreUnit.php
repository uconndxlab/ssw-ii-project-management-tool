<?php

namespace App\Enums;

enum CertificationToolScoreUnit: string
{
    case Percent = 'percent';
    case Points = 'points';
    case Number = 'number';

    public static function values(): array
    {
        return array_map(fn (self $case) => $case->value, self::cases());
    }

    public function label(): string
    {
        return match ($this) {
            self::Percent => 'Percentage',
            self::Points => 'Points',
            self::Number => 'Number',
        };
    }

    public function suffix(): ?string
    {
        return match ($this) {
            self::Percent => '%',
            self::Points => 'pts',
            self::Number => null,
        };
    }
}
