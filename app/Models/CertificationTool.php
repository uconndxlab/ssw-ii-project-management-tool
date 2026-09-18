<?php

namespace App\Models;

use App\Enums\ProgramScopeMode;
use App\Models\Concerns\HasProgramScope;
use App\Models\Concerns\VisibleToUser;
use Database\Factories\CertificationToolFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

/**
 * A scoring instrument (COMET, SAS, CREST, ...) built via the tool builder. Not an activity type.
 */
class CertificationTool extends Model
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

    public function programs(): BelongsToMany
    {
        return $this->belongsToMany(Program::class, 'certification_tool_program')->withTimestamps();
    }

    public function dimensions(): HasMany
    {
        return $this->hasMany(CertificationToolDimension::class)->orderBy('sort_order');
    }

    public function scoreFields(): HasMany
    {
        return $this->hasMany(CertificationToolScoreField::class)->orderBy('sort_order');
    }

    public function requirements(): HasMany
    {
        return $this->hasMany(CertificateRequirement::class);
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
