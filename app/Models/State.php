<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class State extends Model
{
    protected $fillable = [
        'name',
        'code',
        'is_territory',
    ];

    protected $casts = [
        'is_territory' => 'boolean',
    ];

    public function organizations(): HasMany
    {
        return $this->hasMany(Organization::class);
    }

    public function agreements(): BelongsToMany
    {
        return $this->belongsToMany(Agreement::class, 'agreement_state')->withTimestamps();
    }

    public function activities(): BelongsToMany
    {
        return $this->belongsToMany(Activity::class, 'activity_state')->withTimestamps();
    }

    /**
     * Legacy accessor for backwards compatibility during migration
     */
    public function projects(): BelongsToMany
    {
        return $this->agreements();
    }
}
