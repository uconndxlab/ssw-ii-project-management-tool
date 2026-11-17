<?php

namespace App\Models;

use App\Enums\CertificateTagOutcome;
use Database\Factories\CertificationInstrumentSubmissionFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property CertificateTagOutcome $outcome
 * @property-read CertificationInstrument $instrument
 */
class CertificationInstrumentSubmission extends Model
{
    /** @use HasFactory<CertificationInstrumentSubmissionFactory> */
    use HasFactory;

    protected $fillable = ['activity_certification_candidate_id', 'certification_instrument_id', 'outcome', 'recorded_by_user_id', 'notes'];

    protected function casts(): array
    {
        return ['outcome' => CertificateTagOutcome::class];
    }

    /** @return BelongsTo<ActivityCertificationCandidate, $this> */
    public function tag(): BelongsTo
    {
        return $this->belongsTo(ActivityCertificationCandidate::class, 'activity_certification_candidate_id');
    }

    /** @return BelongsTo<CertificationInstrument, $this> */
    public function instrument(): BelongsTo
    {
        return $this->belongsTo(CertificationInstrument::class, 'certification_instrument_id');
    }

    /** @return BelongsTo<User, $this> */
    public function recordedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by_user_id');
    }

    /** @return HasMany<CertificationInstrumentSubmissionDimensionValue, $this> */
    public function dimensionValues(): HasMany
    {
        return $this->hasMany(CertificationInstrumentSubmissionDimensionValue::class);
    }

    /** @return HasMany<CertificationInstrumentSubmissionScore, $this> */
    public function scores(): HasMany
    {
        return $this->hasMany(CertificationInstrumentSubmissionScore::class);
    }
}
