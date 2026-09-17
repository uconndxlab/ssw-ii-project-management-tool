<?php

namespace App\Enums;

enum CertificationDuty: string
{
    case Coach = 'coach';
    case Manager = 'manager';

    public static function values(): array
    {
        return array_map(fn (self $case) => $case->value, self::cases());
    }

    public function label(): string
    {
        return match ($this) {
            self::Coach => 'National Coach',
            self::Manager => 'Certification Manager',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::Coach => 'Records tool submissions and attestations, and endorses candidates for award.',
            self::Manager => 'Reviews an endorsed candidate and awards or revokes the certificate.',
        };
    }
}
