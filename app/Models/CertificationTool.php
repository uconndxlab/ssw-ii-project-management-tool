<?php

namespace App\Models;

use App\Enums\ProgramScopeMode;
use App\Models\Concerns\HasProgramScope;
use App\Models\Concerns\VisibleToUser;
use App\Models\Contracts\HasPrograms;
use Database\Factories\CertificationToolFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

/**
 * A scoring instrument (COMET, SAS, CREST, ...) built via the tool builder. Not an activity type.
 *
 * @property ProgramScopeMode $program_scope_mode
 */
class CertificationTool extends Model implements HasPrograms
{
    /** @use HasFactory<CertificationToolFactory> */
    use HasFactory, HasProgramScope, VisibleToUser;

    protected static function booted(): void
    {
        static::creating(function (self $tool) {
            if (blank($tool->slug)) {
                $tool->slug = Str::slug($tool->name);
            }
        });
    }

    protected $fillable = [
        'name',
        'description',
        'active',
        'sort_order',
        'program_scope_mode',
        'retired_at',
    ];

    protected function casts(): array
    {
        return [
            'active' => 'boolean',
            'sort_order' => 'integer',
            'program_scope_mode' => ProgramScopeMode::class,
            'retired_at' => 'datetime',
        ];
    }

    /** @return BelongsToMany<Program, $this> */
    public function programs(): BelongsToMany
    {
        return $this->belongsToMany(Program::class, 'certification_tool_program')->withTimestamps();
    }

    /** @return HasMany<CertificationToolDimension, $this> */
    public function dimensions(): HasMany
    {
        return $this->hasMany(CertificationToolDimension::class)->orderBy('sort_order');
    }

    /** @return HasMany<CertificationToolScoreField, $this> */
    public function scoreFields(): HasMany
    {
        return $this->hasMany(CertificationToolScoreField::class)->orderBy('sort_order');
    }

    /** @return HasMany<CertificateRequirement, $this> */
    public function requirements(): HasMany
    {
        return $this->hasMany(CertificateRequirement::class);
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
