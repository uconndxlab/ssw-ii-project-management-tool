<?php

namespace App\Enums;

enum CertificateEnrollmentStatus: string
{
    case NotStarted = 'not_started';
    case InProgress = 'in_progress';
    case RequirementsMet = 'requirements_met';
    case Endorsed = 'endorsed';
    case Awarded = 'awarded';
    case Expired = 'expired';
    case Revoked = 'revoked';

    public static function values(): array
    {
        return array_map(fn (self $case) => $case->value, self::cases());
    }

    public function label(): string
    {
        return match ($this) {
            self::NotStarted => 'Not Started',
            self::InProgress => 'In Progress',
            self::RequirementsMet => 'Requirements Met',
            self::Endorsed => 'Endorsed',
            self::Awarded => 'Awarded',
            self::Expired => 'Expired',
            self::Revoked => 'Revoked',
        };
    }

    public function icon(): ?string
    {
        return match ($this) {
            self::NotStarted => 'circle',
            self::InProgress => 'arrow-right-circle-fill',
            self::RequirementsMet => 'check2-circle',
            self::Endorsed => 'patch-check-fill',
            self::Awarded => 'award-fill',
            self::Expired => 'clock-history',
            self::Revoked => 'x-circle-fill',
        };
    }

    public function tone(): string
    {
        return match ($this) {
            self::NotStarted => 'muted',
            self::InProgress => 'info',
            self::RequirementsMet, self::Endorsed => 'warning',
            self::Awarded => 'success',
            self::Expired => 'secondary',
            self::Revoked => 'danger',
        };
    }

    public function isAwardable(): bool
    {
        return $this === self::Endorsed;
    }
}
