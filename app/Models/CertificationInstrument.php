<?php

namespace App\Models;

use App\Enums\CertificationInstrumentDimensionType;
use App\Enums\CertificationInstrumentType;
use App\Enums\ProgramScopeMode;
use App\Models\Concerns\HasProgramScope;
use App\Models\Concerns\VisibleToUser;
use App\Models\Contracts\HasPrograms;
use Database\Factories\CertificationInstrumentFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

/**
 * A tool (COMET, SAS, CREST, ...) or a training (sections such as Wraparound 101) built in the
 * instrument builder. Not an activity type.
 *
 * @property CertificationInstrumentType $type
 * @property ProgramScopeMode $program_scope_mode
 */
class CertificationInstrument extends Model implements HasPrograms
{
    /** @use HasFactory<CertificationInstrumentFactory> */
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
        'type',
        'description',
        'active',
        'sort_order',
        'program_scope_mode',
        'retired_at',
    ];

    protected function casts(): array
    {
        return [
            'type' => CertificationInstrumentType::class,
            'active' => 'boolean',
            'sort_order' => 'integer',
            'program_scope_mode' => ProgramScopeMode::class,
            'retired_at' => 'datetime',
        ];
    }

    /** @return BelongsToMany<Program, $this> */
    public function programs(): BelongsToMany
    {
        return $this->belongsToMany(Program::class, 'certification_instrument_program')->withTimestamps();
    }

    /** @return HasMany<CertificationInstrumentDimension, $this> */
    public function dimensions(): HasMany
    {
        return $this->hasMany(CertificationInstrumentDimension::class)->orderBy('sort_order');
    }

    public function isTraining(): bool
    {
        return $this->type === CertificationInstrumentType::Training;
    }

    /** A training's one sections list; null for tools. Uses loaded dimensions when present. */
    public function sectionsDimension(): ?CertificationInstrumentDimension
    {
        return $this->dimensions->first(fn (CertificationInstrumentDimension $dimension) => $dimension->type === CertificationInstrumentDimensionType::Sections);
    }

    /** @return HasMany<CertificationInstrumentScoreField, $this> */
    public function scoreFields(): HasMany
    {
        return $this->hasMany(CertificationInstrumentScoreField::class)->orderBy('sort_order');
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
