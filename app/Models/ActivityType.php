<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ActivityType extends Model
{
    protected $fillable = [
        'name',
        'contact_family_id',
        'active',
        'sort_order',
        'duration_days',
        'duration_hours',
    ];

    protected function casts(): array
    {
        return [
            'active' => 'boolean',
            'sort_order' => 'integer',
            'duration_days' => 'integer',
            'duration_hours' => 'integer',
            'contact_family_id' => 'integer',
        ];
    }

    public function contactFamily(): BelongsTo
    {
        return $this->belongsTo(ContactFamily::class);
    }

    public function activities(): HasMany
    {
        return $this->hasMany(Activity::class);
    }

    public function activityTypeLoggingFields(): BelongsToMany
    {
        return $this->belongsToMany(LoggingField::class, 'activity_type_logging_field_assignments', 'activity_type_id', 'logging_field_id')
            ->withPivot('is_required')
            ->withTimestamps()
            ->orderBy('sort_order', 'asc')
            ->orderBy('name', 'asc');
    }

    /**
     * Scope to only active activity types
     */
    public function scopeActive($query)
    {
        return $query->where('active', true);
    }
}