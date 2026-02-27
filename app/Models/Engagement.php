<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Engagement extends Model
{
    public const ACTIVITY_TYPES = [
        'technical_assistance',
        'coaching',
        'training',
        'consultation',
        'workshop',
    ];

    public const DELIVERABLE_BUCKETS = [
        'strategic_planning',
        'capacity_building',
        'program_development',
        'evaluation',
        'community_engagement',
        'other',
    ];

    protected $fillable = [
        'project_id',
        'user_id',
        'engagement_date',
        'activity_type',
        'deliverable_bucket',
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

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function programs(): BelongsToMany
    {
        return $this->belongsToMany(Program::class)->withTimestamps();
    }

    public function participants(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'engagement_user')->withTimestamps();
    }
}
