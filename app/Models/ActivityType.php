<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ActivityType extends Model
{
    protected $fillable = [
        'name',
        'contact_family_id',
        'active',
        'sort_order',
        'duration_days',
    ];

    protected function casts(): array
    {
        return [
            'active' => 'boolean',
            'sort_order' => 'integer',
            'duration_days' => 'integer',
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

    /**
     * Scope to only active activity types
     */
    public function scopeActive($query)
    {
        return $query->where('active', true);
    }
}