<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Activity extends Model
{
    protected $fillable = [
        'user_id',
        'engagement_date',
        'activity_type_id',
        'event_hours',
        'prep_hours',
        'followup_hours',
        'participant_count',
        'external_attendees',
        'summary',
        'follow_up',
        'strengths',
        'recommendations',
        'internal_only',
        'time_tracking_mode',
    ];

    protected function casts(): array
    {
        return [
            'engagement_date' => 'date',
            'event_hours' => 'decimal:2',
            'prep_hours' => 'decimal:2',
            'followup_hours' => 'decimal:2',
            'participant_count' => 'integer',
            'internal_only' => 'boolean',
        ];
    }

    /**
     * Get total hours (computed accessor).
     */
    public function getTotalHoursAttribute(): float
    {
        return $this->event_hours 
            + ($this->prep_hours ?? 0) 
            + ($this->followup_hours ?? 0);
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

    public function participantTimes(): HasMany
    {
        return $this->hasMany(ActivityParticipantTime::class);
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

    /**
     * Get total hours based on time tracking mode.
     * For engagement mode: sum event_hours, prep_hours, followup_hours
     * For participant mode: sum from activity_participant_times
     */
    public function getTotalHoursByModeAttribute(): float
    {
        if ($this->time_tracking_mode === 'participant') {
            return $this->participantTimes->sum('hours') ?? 0;
        }

        // Default to engagement mode
        return $this->event_hours 
            + ($this->prep_hours ?? 0) 
            + ($this->followup_hours ?? 0);
    }
}
