<?php

namespace App\Models;

use App\Enums\ProgramScopeMode;
use App\Models\Concerns\HasProgramScope;
use App\Models\Concerns\VisibleToUser;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * Index/view: admins only, and a listed program is in your privilege.
 * Edit: you admin a listed program. Delete: every listed program is in your admin scope. No programs: system admin only.
 */
class ContactFamily extends Model
{
    use HasProgramScope, VisibleToUser;

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

    public function activityTypes(): HasMany
    {
        return $this->hasMany(ActivityType::class)->orderBy('sort_order')->orderBy('name');
    }

    public function contactFamilyLoggingFields(): BelongsToMany
    {
        return $this->belongsToMany(LoggingField::class, 'contact_family_logging_field_assignments', 'contact_family_id', 'logging_field_id')
            ->withPivot('is_required', 'sort_order')
            ->withTimestamps()
            ->orderBy('contact_family_logging_field_assignments.sort_order', 'asc')
            ->orderBy('name', 'asc');
    }

    public function loggingFields(): BelongsToMany
    {
        return $this->contactFamilyLoggingFields();
    }

    public function programs(): BelongsToMany
    {
        return $this->belongsToMany(Program::class, 'contact_family_program')->withTimestamps();
    }

    /**
     * Scope to only active contact families
     */
    public function scopeActive($query)
    {
        return $query->where('active', true);
    }
}
