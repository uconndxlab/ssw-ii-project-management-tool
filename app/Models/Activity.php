<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany; // kept for certificationCandidates

class Activity extends Model
{
    protected $fillable = [
        'user_id',
        'engagement_date',
        'activity_type_id',
        'logging_field_data',
        'internal_only',
    ];

    protected function casts(): array
    {
        return [
            'engagement_date' => 'date',
            'logging_field_data' => 'array',
            'internal_only' => 'boolean',
        ];
    }

    public function agreements(): BelongsToMany
    {
        return $this->belongsToMany(Agreement::class, 'activity_agreement')->withTimestamps();
    }

    public function organizations(): BelongsToMany
    {
        return $this->belongsToMany(Organization::class, 'activity_organization')->withTimestamps();
    }

    public function states(): BelongsToMany
    {
        return $this->belongsToMany(State::class, 'activity_state')->withTimestamps();
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function activityType(): BelongsTo
    {
        return $this->belongsTo(ActivityType::class);
    }

    /**
     * Convenience accessor to get contact family through activity type
     */
    public function contactFamily()
    {
        return $this->activityType?->contactFamily;
    }

    public function programs(): BelongsToMany
    {
        return $this->belongsToMany(Program::class, 'activity_program')->withTimestamps();
    }

    public function participants(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'activity_user')->withTimestamps();
    }

    public function certificationCandidates(): HasMany
    {
        return $this->hasMany(AgreementCertificationCandidate::class);
    }

    /**
     * Scope: Exclude internal-only activities
     */
    public function scopeExternalOnly($query)
    {
        return $query->where('internal_only', false);
    }

    /**
     * Scope: Include only internal activities
     */
    public function scopeInternalOnly($query)
    {
        return $query->where('internal_only', true);
    }

}
