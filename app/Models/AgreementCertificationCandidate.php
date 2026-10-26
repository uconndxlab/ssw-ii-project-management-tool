<?php

namespace App\Models;

use Database\Factories\AgreementCertificationCandidateFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A roster link: one agreement's placement of one global {@see CertificationCandidate}. Carries
 * only agreement-specific context (program, notes, display order). Removing this link detaches the
 * candidate from this agreement only; it never deletes the candidate or their enrollments.
 */
class AgreementCertificationCandidate extends Model
{
    /** @use HasFactory<AgreementCertificationCandidateFactory> */
    use HasFactory;

    protected $fillable = [
        'agreement_id',
        'certification_candidate_id',
        'program_id',
        'notes',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'sort_order' => 'integer',
        ];
    }

    /** @return BelongsTo<Agreement, $this> */
    public function agreement(): BelongsTo
    {
        return $this->belongsTo(Agreement::class);
    }

    /** @return BelongsTo<CertificationCandidate, $this> */
    public function candidate(): BelongsTo
    {
        return $this->belongsTo(CertificationCandidate::class, 'certification_candidate_id');
    }

    /** @return BelongsTo<Program, $this> */
    public function program(): BelongsTo
    {
        return $this->belongsTo(Program::class);
    }
}
