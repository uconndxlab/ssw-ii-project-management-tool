<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Activity extends Model
{
    protected $fillable = [
        'agreement_id',
        'user_id',
        'engagement_date',
        'activity_type_id',
        'event_hours',
        'prep_hours',
        'followup_hours',
        'participant_count',
        'summary',
        'follow_up',
        'strengths',
        'recommendations',
    ];

    protected function casts(): array
    {
        return [
            'engagement_date' => 'date',
            'event_hours' => 'decimal:2',
            'prep_hours' => 'decimal:2',
            'followup_hours' => 'decimal:2',
            'participant_count' => 'integer',
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

    public function agreement(): BelongsTo
    {
        return $this->belongsTo(Agreement::class);
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
}
