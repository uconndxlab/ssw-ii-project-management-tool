<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

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

    /**
     * Scope to only active contact families
     */
    public function scopeActive($query)
    {
        return $query->where('active', true);
    }
}
