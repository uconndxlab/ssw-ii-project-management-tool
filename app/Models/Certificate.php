<?php

namespace App\Models;

use App\Enums\CertificateRequirementPhase;
use App\Enums\ProgramScopeMode;
use App\Models\Concerns\HasProgramScope;
use App\Models\Concerns\VisibleToUser;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Catalog entry pursued by candidates. Requirements are computed against live activity work, never materialized.
 */
class Certificate extends Model
{
    use HasProgramScope, VisibleToUser;

    protected $fillable = [
        'name',
        'description',
        'active',
        'sort_order',
        'program_scope_mode',
        'validity_months',
        'default_window_months',
        'prerequisite_mode',
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
            'retired_at' => 'datetime',
        ];
    }

    public function programs(): BelongsToMany
    {
        return $this->belongsToMany(Program::class, 'certificate_program')->withTimestamps();
    }

    public function prerequisites(): BelongsToMany
    {
        return $this->belongsToMany(Certificate::class, 'certificate_prerequisites', 'certificate_id', 'required_certificate_id')->withTimestamps();
    }

    public function requirementGroups(): HasMany
    {
        return $this->hasMany(CertificateRequirementGroup::class)->orderBy('sort_order');
    }

    public function requirements(): HasMany
    {
        return $this->hasMany(CertificateRequirement::class)->orderBy('sort_order');
    }

    public function requirementsForPhase(CertificateRequirementPhase $phase): HasMany
    {
        return $this->requirements()->where('phase', $phase->value);
    }

    public function roles(): HasMany
    {
        return $this->hasMany(CertificationRole::class)->orderBy('sort_order');
    }

    public function scopeActive($query)
    {
        return $query->where('active', true);
    }

    public function scopeNotRetired($query)
    {
        return $query->whereNull('retired_at');
    }
}
