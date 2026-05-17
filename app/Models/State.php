<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

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

    public function organizations(): BelongsToMany
    {
        return $this->belongsToMany(Organization::class, 'organization_state')->withTimestamps();
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
