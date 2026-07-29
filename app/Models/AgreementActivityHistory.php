<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AgreementActivityHistory extends Model
{
    protected $fillable = [
        'agreement_id',
        'activity_id',
        'contact_family_id',
        'activity_type_id',
        'contributor_user_id',
        'activity_date',
        'contribution_kind',
        'completion_units',
        'activity_hours',
        'prep_hours',
        'follow_up_hours',
        'allotted_hours',
        'allotted_days',
        'program_ids_snapshot',
        'team_ids_snapshot',
    ];

    protected function casts(): array
    {
        return [
            'activity_date' => 'date',
            'completion_units' => 'decimal:2',
            'activity_hours' => 'decimal:2',
            'prep_hours' => 'decimal:2',
            'follow_up_hours' => 'decimal:2',
            'allotted_hours' => 'decimal:2',
            'allotted_days' => 'decimal:2',
            'program_ids_snapshot' => 'array',
            'team_ids_snapshot' => 'array',
        ];
    }

    public function agreement(): BelongsTo
    {
        return $this->belongsTo(Agreement::class);
    }

    public function activity(): BelongsTo
    {
        return $this->belongsTo(Activity::class);
    }

    public function contactFamily(): BelongsTo
    {
        return $this->belongsTo(ContactFamily::class);
    }

    public function activityType(): BelongsTo
    {
        return $this->belongsTo(ActivityType::class);
    }

    public function contributor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'contributor_user_id');
    }

    public function deliverableContributions(): HasMany
    {
        return $this->hasMany(DeliverableContribution::class, 'agreement_activity_history_id');
    }
}