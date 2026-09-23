<?php

namespace App\Models;

use App\Enums\CertificateRequirementPhase;
use App\Enums\ProgramScopeMode;
use App\Models\Concerns\HasProgramScope;
use App\Models\Concerns\VisibleToUser;
use Database\Factories\CertificateFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Catalog entry pursued by candidates. Requirements are computed against live activity work, never materialized.
 *
 * @property ProgramScopeMode $program_scope_mode
 * @property bool $renewal_matches_initial
 */
class Certificate extends Model
{
    /** @use HasFactory<CertificateFactory> */
    use HasFactory, HasProgramScope, VisibleToUser;

    protected $fillable = [
        'name',
        'description',
        'active',
        'sort_order',
        'program_scope_mode',
        'validity_months',
        'default_window_months',
        'prerequisite_mode',
        'renewal_matches_initial',
        'retired_at',
    ];

    protected function casts(): array
    {
        return [
            'active' => 'boolean',
            'sort_order' => 'integer',
            'program_scope_mode' => ProgramScopeMode::class,
            'validity_months' => 'integer',
            'default_window_months' => 'integer',
            'renewal_matches_initial' => 'boolean',
            'retired_at' => 'datetime',
        ];
    }

    /** @return BelongsToMany<Program, $this> */
    public function programs(): BelongsToMany
    {
        return $this->belongsToMany(Program::class, 'certificate_program')->withTimestamps();
    }

    /** @return BelongsToMany<Certificate, $this> */
    public function prerequisites(): BelongsToMany
    {
        return $this->belongsToMany(Certificate::class, 'certificate_prerequisites', 'certificate_id', 'required_certificate_id')->withTimestamps();
    }

    /** @return HasMany<CertificateRequirementGroup, $this> */
    public function requirementGroups(): HasMany
    {
        return $this->hasMany(CertificateRequirementGroup::class)->orderBy('sort_order');
    }

    /** @return HasMany<CertificateRequirement, $this> */
    public function requirements(): HasMany
    {
        return $this->hasMany(CertificateRequirement::class)->orderBy('sort_order');
    }

    /** @return HasMany<CertificateRequirement, $this> */
    public function requirementsForPhase(CertificateRequirementPhase $phase): HasMany
    {
        return $this->requirements()->where('phase', $phase->value);
    }

    /** @return HasMany<CertificationRole, $this> */
    public function roles(): HasMany
    {
        return $this->hasMany(CertificationRole::class)->orderBy('sort_order');
    }

    /**
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('active', true);
    }

    /**
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeNotRetired(Builder $query): Builder
    {
        return $query->whereNull('retired_at');
    }
}
