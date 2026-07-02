<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class ContactFamily extends Model
{
    protected $fillable = [
        'name',
        'active',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    public function activityTypes(): HasMany
    {
        return $this->hasMany(ActivityType::class)->orderBy('sort_order')->orderBy('name');
    }

    public function contactFamilyLoggingFields(): BelongsToMany
    {
        return $this->belongsToMany(LoggingField::class, 'contact_family_logging_field_assignments', 'contact_family_id', 'logging_field_id')
            ->withPivot('is_required')
            ->withTimestamps()
            ->orderBy('sort_order', 'asc')
            ->orderBy('name', 'asc');
    }

    public function loggingFields(): BelongsToMany
    {
        return $this->contactFamilyLoggingFields();
    }

    /**
     * Scope to only active contact families
     */
    public function scopeActive($query)
    {
        return $query->where('active', true);
    }
}
