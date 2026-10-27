<?php

namespace App\Enums;

enum CertificateAction: string
{
    case Endorsed = 'endorsed';
    case EndorsementWithdrawn = 'endorsement_withdrawn';
    case EndorsementReturned = 'endorsement_returned';
    case Awarded = 'awarded';
    case RenewalOpened = 'renewal_opened';
    case Revoked = 'revoked';
    case CoachChanged = 'coach_changed';

    public function label(): string
    {
        return match ($this) {
            self::Endorsed => 'Endorsed for award',
            self::EndorsementWithdrawn => 'Withdrew endorsement',
            self::EndorsementReturned => 'Returned endorsement to coach',
            self::Awarded => 'Awarded',
            self::RenewalOpened => 'Opened renewal',
            self::Revoked => 'Revoked award',
            self::CoachChanged => 'Changed supervising coach',
        };
    }
}
