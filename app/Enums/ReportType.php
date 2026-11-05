<?php

namespace App\Enums;

enum ReportType: string
{
    case BasicActivityExport = 'basic_activity_export';

    public function label(): string
    {
        return match ($this) {
            self::BasicActivityExport => 'Basic activity export',
        };
    }
}
