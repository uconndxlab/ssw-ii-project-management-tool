<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Organization extends Model
{
    protected $fillable = [
        'name',
        'state_id',
    ];

    public function state(): BelongsTo
    {
        return $this->belongsTo(State::class);
    }

    public function agreements(): BelongsToMany
    {
        return $this->belongsToMany(Agreement::class, 'agreement_organization')->withTimestamps();
    }

    public function activities(): BelongsToMany
    {
        return $this->belongsToMany(Activity::class, 'activity_organization')->withTimestamps();
    }

    /**
     * Legacy accessor for backwards compatibility during migration
     */
    public function projects(): BelongsToMany
    {
        return $this->agreements();
    }
}
