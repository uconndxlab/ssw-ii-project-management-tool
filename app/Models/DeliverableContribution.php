<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DeliverableContribution extends Model
{
    protected $table = 'deliverable_contributions';

    protected $fillable = [
        'agreement_activity_history_id',
        'agreement_deliverable_id',
        'agreement_id',
        'activity_id',
        'contributor_user_id',
        'contribution_kind',
        'source_assignment_type',
        'counted_attribution_basis',
        'credited_units',
        'credited_hours',
        'credited_allotted_hours',
        'credited_allotted_days',
        'prep_hours',
        'follow_up_hours',
        'rules_fingerprint',
        'cancelled',
        'not_yet_complete',
    ];

    protected function casts(): array
    {
        return [
            'credited_units' => 'decimal:2',
            'credited_hours' => 'decimal:2',
            'credited_allotted_hours' => 'decimal:2',
            'credited_allotted_days' => 'decimal:2',
            'prep_hours' => 'decimal:2',
            'follow_up_hours' => 'decimal:2',
            'cancelled' => 'boolean',
            'not_yet_complete' => 'boolean',
        ];
    }

    public function activityHistory(): BelongsTo
    {
        return $this->belongsTo(AgreementActivityHistory::class, 'agreement_activity_history_id');
    }

    public function deliverable(): BelongsTo
    {
        return $this->belongsTo(AgreementDeliverable::class, 'agreement_deliverable_id');
    }

    public function agreement(): BelongsTo
    {
        return $this->belongsTo(Agreement::class);
    }

    public function activity(): BelongsTo
    {
        return $this->belongsTo(Activity::class);
    }

    public function contributor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'contributor_user_id');
    }
}
