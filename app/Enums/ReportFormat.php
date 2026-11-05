<?php

namespace App\Enums;

enum ReportFormat: string
{
    case Csv = 'csv';

    public function label(): string
    {
        return match ($this) {
            self::Csv => 'CSV',
        };
    }
}
