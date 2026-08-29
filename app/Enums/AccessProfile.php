<?php

namespace App\Enums;

enum AccessProfile: string
{
    case AdminViewer = 'admin_viewer';
    case Member = 'member';
    case Input = 'input';

    public static function values(): array
    {
        return array_map(fn (self $case) => $case->value, self::cases());
    }

    public function label(): string
    {
        return match ($this) {
            self::AdminViewer => 'Admin / Enhanced Viewer',
            self::Member => 'User',
            self::Input => 'Input User',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::AdminViewer => 'View or edit records in an assigned project, program, or system-wide scope.',
            self::Member => 'View assigned projects, programs, teams, agreements, and related records. Log and edit activities you are on.',
            self::Input => 'Home, profile, and activity logging only.',
        };
    }
}
