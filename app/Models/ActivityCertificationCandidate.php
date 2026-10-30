<?php

namespace App\Models;

use Database\Factories\ActivityCertificationCandidateFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property-read Activity $activity
 * @property-read CertificationCandidate $candidate
 */
class ActivityCertificationCandidate extends Model
{
    /** @use HasFactory<ActivityCertificationCandidateFactory> */
    use HasFactory;

    protected $fillable = ['activity_id', 'certification_candidate_id', 'agreement_id', 'certification_role_id', 'completion_units', 'notes'];

    protected function casts(): array
    {
        return ['completion_units' => 'integer'];
    }

    /** @return BelongsTo<Activity, $this> */
    public function activity(): BelongsTo
    {
        return $this->belongsTo(Activity::class);
    }

    /** @return BelongsTo<CertificationCandidate, $this> */
    public function candidate(): BelongsTo
    {
        return $this->belongsTo(CertificationCandidate::class, 'certification_candidate_id');
    }

    /** @return BelongsTo<Agreement, $this> */
    public function agreement(): BelongsTo
    {
        return $this->belongsTo(Agreement::class);
    }

    /** @return BelongsTo<CertificationRole, $this> */
    public function role(): BelongsTo
    {
        return $this->belongsTo(CertificationRole::class, 'certification_role_id');
    }

    /** @return HasMany<CertificationToolSubmission, $this> */
    public function submissions(): HasMany
    {
        return $this->hasMany(CertificationToolSubmission::class);
    }
}
