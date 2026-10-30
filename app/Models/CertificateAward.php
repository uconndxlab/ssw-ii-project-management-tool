<?php

namespace App\Models;

use App\Enums\CertificateRequirementPhase;
use Database\Factories\CertificateAwardFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * One cycle's credential. The row is created by the coach's endorsement, which freezes the
 * requirement snapshot; it becomes an issued award once a manager sets awarded_at.
 *
 * @property int $id
 * @property int $certificate_enrollment_id
 * @property int $cycle
 * @property CertificateRequirementPhase $phase
 * @property Carbon $endorsed_at
 * @property int|null $endorsed_by_user_id
 * @property string|null $endorsement_note
 * @property array<string, mixed> $requirements_snapshot
 * @property Carbon|null $awarded_at
 * @property int|null $awarded_by_user_id
 * @property string|null $award_note
 * @property Carbon|null $expires_at
 * @property Carbon|null $revoked_at
 * @property int|null $revoked_by_user_id
 * @property string|null $revoke_reason
 * @property-read CertificateEnrollment|null $enrollment
 * @property-read User|null $endorsedBy
 * @property-read User|null $awardedBy
 * @property-read User|null $revokedBy
 */
class CertificateAward extends Model
{
    /** @use HasFactory<CertificateAwardFactory> */
    use HasFactory;

    protected $fillable = [
        'certificate_enrollment_id', 'cycle', 'phase', 'endorsed_at', 'endorsed_by_user_id', 'endorsement_note',
        'requirements_snapshot', 'awarded_at', 'awarded_by_user_id', 'award_note', 'expires_at',
        'revoked_at', 'revoked_by_user_id', 'revoke_reason',
    ];

    protected function casts(): array
    {
        return [
            'cycle' => 'integer', 'phase' => CertificateRequirementPhase::class, 'requirements_snapshot' => 'array',
            'endorsed_at' => 'datetime', 'awarded_at' => 'datetime', 'expires_at' => 'date', 'revoked_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<CertificateEnrollment, $this> */
    public function enrollment(): BelongsTo
    {
        return $this->belongsTo(CertificateEnrollment::class, 'certificate_enrollment_id');
    }

    /** @return BelongsTo<User, $this> */
    public function endorsedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'endorsed_by_user_id');
    }

    /** @return BelongsTo<User, $this> */
    public function awardedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'awarded_by_user_id');
    }

    /** @return BelongsTo<User, $this> */
    public function revokedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'revoked_by_user_id');
    }

    /** @param Builder<CertificateAward> $query */
    public function scopeIssued(Builder $query): void
    {
        $query->whereNotNull('awarded_at');
    }

    /** Issued, not revoked and not past its expiry date. */
    public function isLive(): bool
    {
        return $this->awarded_at !== null && $this->revoked_at === null
            && ($this->expires_at === null || $this->expires_at->isAfter(today()));
    }

    public function isExpired(): bool
    {
        return $this->awarded_at !== null && $this->revoked_at === null && ! $this->isLive();
    }
}
