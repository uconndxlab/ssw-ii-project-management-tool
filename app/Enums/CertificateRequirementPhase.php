<?php

namespace App\Enums;

enum CertificateRequirementPhase: string
{
    case Initial = 'initial';
    case Renewal = 'renewal';

    public static function values(): array
    {
        return array_map(fn (self $case) => $case->value, self::cases());
    }

    public function label(): string
    {
        return match ($this) {
            self::Initial => 'Initial Certification',
            self::Renewal => 'Recertification',
        };
    }
}
