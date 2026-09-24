<?php

namespace App\Models;

use App\Enums\ProgramScopeMode;
use App\Models\Concerns\HasProgramScope;
use App\Models\Concerns\VisibleToUser;
use App\Models\Contracts\HasPrograms;
use Database\Factories\ContactFamilyFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Index/view: admins only, and a listed program is in your privilege.
 * Edit: you admin a listed program. Delete: every listed program is in your admin scope. No programs: system admin only.
 *
 * @property ProgramScopeMode $program_scope_mode
 */
class ContactFamily extends Model implements HasPrograms
{
    /** @use HasFactory<ContactFamilyFactory> */
    use HasFactory, HasProgramScope, VisibleToUser;

    protected $fillable = [
        'name',
        'helper_text',
        'active',
        'track_additional_time',
        'sort_order',
        'program_scope_mode',
    ];

    protected function casts(): array
    {
        return [
            'active' => 'boolean',
            'track_additional_time' => 'boolean',
            'sort_order' => 'integer',
            'program_scope_mode' => ProgramScopeMode::class,
        ];
    }

    /** @return HasMany<ActivityType, $this> */
    public function activityTypes(): HasMany
    {
        return $this->hasMany(ActivityType::class)->orderBy('sort_order')->orderBy('name');
    }

    /** @return BelongsToMany<LoggingField, $this> */
    public function contactFamilyLoggingFields(): BelongsToMany
    {
        return $this->belongsToMany(LoggingField::class, 'contact_family_logging_field_assignments', 'contact_family_id', 'logging_field_id')
            ->withPivot('is_required', 'sort_order')
            ->withTimestamps()
            ->orderBy('contact_family_logging_field_assignments.sort_order', 'asc')
            ->orderBy('name', 'asc');
    }

    /** @return BelongsToMany<LoggingField, $this> */
    public function loggingFields(): BelongsToMany
    {
        return $this->contactFamilyLoggingFields();
    }

    /** @return BelongsToMany<Program, $this> */
    public function programs(): BelongsToMany
    {
        return $this->belongsToMany(Program::class, 'contact_family_program')->withTimestamps();
    }

    /**
     * Scope to only active contact families
     *
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('active', true);
    }
}
