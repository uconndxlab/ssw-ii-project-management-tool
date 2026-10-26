<?php

namespace App\Models;

use Database\Factories\CertificateAttestationFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CertificateAttestation extends Model
{
    /** @use HasFactory<CertificateAttestationFactory> */
    use HasFactory;

    protected $fillable = ['certificate_enrollment_id', 'certificate_requirement_id', 'cycle', 'satisfied', 'satisfied_at', 'satisfied_by_user_id', 'note'];

    protected function casts(): array
    {
        return ['cycle' => 'integer', 'satisfied' => 'boolean', 'satisfied_at' => 'datetime'];
    }

    /** @return BelongsTo<CertificateEnrollment, $this> */
    public function enrollment(): BelongsTo
    {
        return $this->belongsTo(CertificateEnrollment::class, 'certificate_enrollment_id');
    }

    /** @return BelongsTo<CertificateRequirement, $this> */
    public function requirement(): BelongsTo
    {
        return $this->belongsTo(CertificateRequirement::class, 'certificate_requirement_id');
    }

    /** @return BelongsTo<User, $this> */
    public function satisfiedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'satisfied_by_user_id');
    }
}
