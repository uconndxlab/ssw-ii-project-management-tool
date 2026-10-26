<?php

namespace App\Models;

use Database\Factories\CertificationCandidateFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Global external person pursuing certificates. No login; not a `User`. Email is nullable in
 * storage for preserved legacy rows, but required and unique whenever a form creates/edits a
 * candidate's identity (enforced in request validation, not here).
 */
class CertificationCandidate extends Model
{
    /** @use HasFactory<CertificationCandidateFactory> */
    use HasFactory;

    protected $fillable = [
        'name',
        'email',
        'phone',
        'start_date',
        'notes',
        'active',
    ];

    protected function casts(): array
    {
        return [
            'start_date' => 'date',
            'active' => 'boolean',
        ];
    }

    public function setEmailAttribute(?string $value): void
    {
        $normalized = $value !== null ? trim($value) : null;
        $this->attributes['email'] = $normalized !== null && $normalized !== '' ? strtolower($normalized) : null;
    }

    /** @return HasMany<AgreementCertificationCandidate, $this> */
    public function rosterLinks(): HasMany
    {
        return $this->hasMany(AgreementCertificationCandidate::class);
    }

    /** @return HasMany<CertificateEnrollment, $this> */
    public function enrollments(): HasMany
    {
        return $this->hasMany(CertificateEnrollment::class);
    }

    /**
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('active', true);
    }
}
