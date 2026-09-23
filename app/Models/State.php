<?php

namespace App\Models;

use App\Models\Concerns\VisibleToUser;
use Database\Factories\StateFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * View: you can view an agreement in this state.
 * Create/edit/delete: system admin only.
 */
class State extends Model
{
    /** @use HasFactory<StateFactory> */
    use HasFactory, VisibleToUser;

    protected $fillable = [
        'name',
        'code',
        'is_territory',
    ];

    protected $casts = [
        'is_territory' => 'boolean',
    ];

    /** @return BelongsToMany<Organization, $this> */
    public function organizations(): BelongsToMany
    {
        return $this->belongsToMany(Organization::class, 'organization_state')->withTimestamps();
    }

    /** @return BelongsToMany<Agreement, $this> */
    public function agreements(): BelongsToMany
    {
        return $this->belongsToMany(Agreement::class, 'agreement_state')->withTimestamps();
    }

    /** @return BelongsToMany<Activity, $this> */
    public function activities(): BelongsToMany
    {
        return $this->belongsToMany(Activity::class, 'activity_state')->withTimestamps();
    }

    /**
     * Legacy accessor for backwards compatibility during migration
     *
     * @return BelongsToMany<Agreement, $this>
     */
    public function projects(): BelongsToMany
    {
        return $this->agreements();
    }
}
