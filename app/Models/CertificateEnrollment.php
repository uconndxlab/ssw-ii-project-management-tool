<?php

namespace App\Models;

use App\Enums\CertificateEnrollmentStatus;
use App\Enums\CertificateRequirementPhase;
use App\Enums\ProgramScopeMode;
use Database\Factories\CertificateEnrollmentFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

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

    private ?bool $primedUnstarted = null;

    /** @return HasOne<CertificateAward, $this> */
    public function latestIssuedAward(): HasOne
    {
        return $this->hasOne(CertificateAward::class)->ofMany(['cycle' => 'max', 'id' => 'max'], fn (Builder $query) => $query->whereNotNull('awarded_at'));
    }

    /** @param Builder<static> $query */
    public function scopeUnstarted(Builder $query): void
    {
        $query->where('status', CertificateEnrollmentStatus::NotStarted)
            ->where('phase', CertificateRequirementPhase::Initial)->where('cycle', 1)
            ->whereDoesntHave('attestations')
            ->whereNotExists(function ($tags) {
                $tags->selectRaw('1')->from('activity_certification_candidates')
                    ->join('activity_program', 'activity_program.activity_id', '=', 'activity_certification_candidates.activity_id')
                    ->whereColumn('activity_certification_candidates.certification_candidate_id', 'certificate_enrollments.certification_candidate_id')
                    ->whereExists(function ($certificates) {
                        $certificates->selectRaw('1')->from('certificates')
                            ->whereColumn('certificates.id', 'certificate_enrollments.certificate_id')
                            ->where(function ($scope) {
                                $scope->where('program_scope_mode', ProgramScopeMode::All->value)
                                    ->orWhereExists(function ($programs) {
                                        $programs->selectRaw('1')->from('certificate_program')
                                            ->whereColumn('certificate_program.certificate_id', 'certificates.id')
                                            ->whereColumn('certificate_program.program_id', 'activity_program.program_id');
                                    });
                            });
                    });
            });
    }

    /** @param Builder<static> $query */
    public function scopeLifecycleStatus(Builder $query, string $status): void
    {
        if ($status === 'revoked') {
            $query->where('status', CertificateEnrollmentStatus::Revoked);

            return;
        }

        $query->where('status', '!=', CertificateEnrollmentStatus::Revoked);
        $credential = fn (Builder $award) => $award->whereNull('revoked_at');
        if (in_array($status, ['certified', 'expired'], true)) {
            $query->whereHas('latestIssuedAward', function (Builder $award) use ($status, $credential) {
                $credential($award);
                if ($status === 'expired') {
                    $award->whereDate('expires_at', '<=', today());
                } else {
                    $award->where(fn (Builder $dates) => $dates->whereNull('expires_at')->orWhereDate('expires_at', '>', today()));
                }
            });

            return;
        }

        $query->whereDoesntHave('latestIssuedAward', $credential);
        $pending = fn (Builder $award) => $award->whereColumn('certificate_awards.cycle', 'certificate_enrollments.cycle')->whereNull('awarded_at');
        if ($status === 'endorsed') {
            $query->whereHas('awards', $pending);
        } elseif (in_array($status, ['assigned', 'in_progress'], true)) {
            $query->whereDoesntHave('awards', $pending);
            $unstarted = static::query()->unstarted()->select('certificate_enrollments.id');
            $status === 'assigned' ? $query->whereIn('certificate_enrollments.id', $unstarted) : $query->whereNotIn('certificate_enrollments.id', $unstarted);
        } else {
            $query->whereRaw('1 = 0');
        }
    }

    public function primeUnstarted(bool $unstarted): void
    {
        $this->primedUnstarted = $unstarted;
    }

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
        if ($this->primedUnstarted !== null) {
            return $this->primedUnstarted;
        }

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
