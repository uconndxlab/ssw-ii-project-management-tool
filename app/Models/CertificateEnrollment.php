<?php

namespace App\Models;

use App\Enums\CertificateEnrollmentStatus;
use App\Enums\CertificateRequirementPhase;
use App\Enums\ProgramScopeMode;
use Database\Factories\CertificateEnrollmentFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * One (candidate, certificate) assignment, global regardless of how many agreements the candidate
 * appears on. Sponsorship is provenance/display only, never a progress-scoping boundary (spine
 * decision 2). Requirements met is computed live; endorsements and awards live in certificate_awards.
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

    /** @return HasMany<CertificateAttestation, $this> */
    public function attestations(): HasMany
    {
        return $this->hasMany(CertificateAttestation::class);
    }

    /** @return HasMany<CertificateAward, $this> */
    public function awards(): HasMany
    {
        return $this->hasMany(CertificateAward::class)->orderByDesc('cycle');
    }

    /** @return HasMany<CertificateActionLog, $this> */
    public function actionLogs(): HasMany
    {
        return $this->hasMany(CertificateActionLog::class)->latest('created_at')->latest('id');
    }

    /**
     * One status for every surface: the credential first, then the current cycle. Requirements met
     * is live progress and is shown on the progress page, never stored here.
     *
     * @return array{label: string, class: string}
     */
    public function lifecycleBadge(): array
    {
        $latest = $this->awards->whereNotNull('awarded_at')->sortByDesc('cycle')->first();

        return match (true) {
            $this->status === CertificateEnrollmentStatus::Revoked => ['label' => 'Revoked', 'class' => 'app-status app-status--danger'],
            $latest?->isLive() === true => ['label' => $latest->expires_at ? 'Certified · until '.$latest->expires_at->format('M j, Y') : 'Certified · no expiry', 'class' => 'app-status app-status--success'],
            $latest?->isExpired() === true => ['label' => 'Expired '.$latest->expires_at?->format('M j, Y'), 'class' => 'app-status app-status--neutral'],
            $this->awards->contains(fn (CertificateAward $award) => $award->cycle === $this->cycle && $award->awarded_at === null) => ['label' => 'Endorsed', 'class' => 'app-status app-status--warning'],
            $this->isUnstarted() => ['label' => 'Assigned', 'class' => 'app-status app-status--neutral'],
            default => ['label' => 'In progress', 'class' => 'app-status app-status--neutral'],
        };
    }

    public function isUnstarted(): bool
    {
        return $this->status === CertificateEnrollmentStatus::NotStarted
            && $this->phase === CertificateRequirementPhase::Initial
            && $this->cycle === 1
            && ! $this->attestations()->exists()
            && ! ActivityCertificationCandidate::query()
                ->where('certification_candidate_id', $this->certification_candidate_id)
                ->whereHas('activity.programs', function ($query) {
                    $certificate = $this->certificate;
                    if ($certificate?->program_scope_mode !== ProgramScopeMode::All) {
                        $query->whereIn('programs.id', $certificate?->programs->modelKeys() ?? []);
                    }
                })->exists();
    }
}
