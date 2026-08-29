<?php

namespace App\Enums;

enum PrivilegeCapability: string
{
    case Admin = 'admin';
    case View = 'view';

    public static function values(): array
    {
        return array_map(fn (self $case) => $case->value, self::cases());
    }
}
