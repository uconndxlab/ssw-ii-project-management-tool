<?php

namespace App\Models;

use App\Enums\CertificateTagOutcome;
use Database\Factories\CertificationToolSubmissionFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property CertificateTagOutcome $outcome
 * @property-read CertificationTool $tool
 */
class CertificationToolSubmission extends Model
{
    /** @use HasFactory<CertificationToolSubmissionFactory> */
    use HasFactory;

    protected $fillable = ['activity_certification_candidate_id', 'certification_tool_id', 'outcome', 'recorded_by_user_id', 'notes'];

    protected function casts(): array
    {
        return ['outcome' => CertificateTagOutcome::class];
    }

    /** @return BelongsTo<ActivityCertificationCandidate, $this> */
    public function tag(): BelongsTo
    {
        return $this->belongsTo(ActivityCertificationCandidate::class, 'activity_certification_candidate_id');
    }

    /** @return BelongsTo<CertificationTool, $this> */
    public function tool(): BelongsTo
    {
        return $this->belongsTo(CertificationTool::class, 'certification_tool_id');
    }

    /** @return BelongsTo<User, $this> */
    public function recordedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by_user_id');
    }

    /** @return HasMany<CertificationToolSubmissionDimensionValue, $this> */
    public function dimensionValues(): HasMany
    {
        return $this->hasMany(CertificationToolSubmissionDimensionValue::class);
    }

    /** @return HasMany<CertificationToolSubmissionScore, $this> */
    public function scores(): HasMany
    {
        return $this->hasMany(CertificationToolSubmissionScore::class);
    }
}
