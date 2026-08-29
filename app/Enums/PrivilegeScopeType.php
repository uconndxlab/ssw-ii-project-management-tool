<?php

namespace App\Enums;

enum PrivilegeScopeType: string
{
    case System = 'system';
    case Project = 'project';
    case Program = 'program';

    public static function values(): array
    {
        return array_map(fn (self $case) => $case->value, self::cases());
    }
}
