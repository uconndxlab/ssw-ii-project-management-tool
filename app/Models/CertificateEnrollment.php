<?php

namespace App\Models;

use App\Enums\CertificateEnrollmentStatus;
use App\Enums\CertificateRequirementPhase;
use Database\Factories\CertificateEnrollmentFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One (candidate, certificate) assignment, global regardless of how many agreements the candidate
 * appears on. Sponsorship is provenance/display only, never a progress-scoping boundary (spine
 * decision 2). Requirements-met/endorsement/award/renewal fields are deliberately absent until
 * Docs 3-4 need them.
 *
 * @property CertificateEnrollmentStatus $status
 * @property CertificateRequirementPhase $phase
 */
class CertificateEnrollment extends Model
{
    /** @use HasFactory<CertificateEnrollmentFactory> */
    use HasFactory;

    protected $fillable = [
        'certification_candidate_id',
        'certificate_id',
        'sponsoring_agreement_id',
        'supervising_coach_user_id',
        'status',
        'phase',
        'cycle',
        'enrolled_at',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'status' => CertificateEnrollmentStatus::class,
            'phase' => CertificateRequirementPhase::class,
            'cycle' => 'integer',
            'enrolled_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<CertificationCandidate, $this> */
    public function candidate(): BelongsTo
    {
        return $this->belongsTo(CertificationCandidate::class, 'certification_candidate_id');
    }

    /** @return BelongsTo<Certificate, $this> */
    public function certificate(): BelongsTo
    {
        return $this->belongsTo(Certificate::class);
    }

    /** @return BelongsTo<Agreement, $this> */
    public function sponsoringAgreement(): BelongsTo
    {
        return $this->belongsTo(Agreement::class, 'sponsoring_agreement_id');
    }

    /** @return BelongsTo<User, $this> */
    public function supervisingCoach(): BelongsTo
    {
        return $this->belongsTo(User::class, 'supervising_coach_user_id');
    }

    public function isUnstarted(): bool
    {
        return $this->status === CertificateEnrollmentStatus::NotStarted
            && $this->phase === CertificateRequirementPhase::Initial
            && $this->cycle === 1;
    }
}
