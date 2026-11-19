<?php

namespace App\Enums;

enum CertificationSectionResult: string
{
    case Passed = 'passed';
    case Failed = 'failed';

    public function label(): string
    {
        return match ($this) {
            self::Passed => 'Pass',
            self::Failed => 'Fail',
        };
    }
}
