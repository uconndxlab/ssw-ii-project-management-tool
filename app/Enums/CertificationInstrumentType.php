<?php

namespace App\Enums;

enum CertificationInstrumentType: string
{
    case Tool = 'tool';
    case Training = 'training';

    /** @return list<string> */
    public static function values(): array
    {
        return array_map(fn (self $case) => $case->value, self::cases());
    }

    public function label(): string
    {
        return match ($this) {
            self::Tool => 'Tool',
            self::Training => 'Training',
        };
    }
}
