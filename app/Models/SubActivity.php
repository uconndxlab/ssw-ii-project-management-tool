<?php

namespace App\Models;

use App\Enums\ProgramScopeMode;
use App\Models\Concerns\HasProgramScope;
use App\Models\Concerns\VisibleToUser;
use App\Models\Contracts\HasPrograms;
use Database\Factories\SubActivityFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Index/view: admins only, and a listed program is in your privilege.
 * Edit: you admin a listed program. Delete: every listed program is in your admin scope. No programs: system admin only.
 *
 * @property ProgramScopeMode $program_scope_mode
 */
class SubActivity extends Model implements HasPrograms
{
    /** @use HasFactory<SubActivityFactory> */
    use HasFactory, HasProgramScope, VisibleToUser;

    protected $fillable = [
        'name',
        'helper_text',
        'activity_type_id',
        'active',
        'sort_order',
        'duration_days',
        'duration_hours',
        'program_scope_mode',
    ];

    protected function casts(): array
    {
        return [
            'active' => 'boolean',
            'sort_order' => 'integer',
            'duration_days' => 'decimal:1',
            'duration_hours' => 'decimal:1',
            'activity_type_id' => 'integer',
            'program_scope_mode' => ProgramScopeMode::class,
        ];
    }

    /** @return BelongsTo<ActivityType, $this> */
    public function activityType(): BelongsTo
    {
        return $this->belongsTo(ActivityType::class);
    }

    /** @return HasMany<Activity, $this> */
    public function activities(): HasMany
    {
        return $this->hasMany(Activity::class);
    }

    /** @return BelongsToMany<LoggingField, $this> */
    public function subActivityLoggingFields(): BelongsToMany
    {
        return $this->belongsToMany(LoggingField::class, 'sub_activity_logging_field_assignments', 'sub_activity_id', 'logging_field_id')
            ->withPivot('is_required', 'sort_order')
            ->withTimestamps()
            ->orderBy('sub_activity_logging_field_assignments.sort_order', 'asc')
            ->orderBy('name', 'asc');
    }

    /** @return BelongsToMany<Program, $this> */
    public function programs(): BelongsToMany
    {
        return $this->belongsToMany(Program::class, 'sub_activity_program')->withTimestamps();
    }

    /**
     * Scope to only active sub-activities
     *
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('active', true);
    }
}
