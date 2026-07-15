<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AgreementDeliverable extends Model
{
    protected $fillable = [
        'agreement_id',
        'activity_type_id',
        'contact_family_id',
        'program_id',
        'metric_type',
        'contribution_basis',
        'user_grouping_mode',
        'include_additional_time',
        'target_quantity',
        'suggested_due_date',
        'sort_order',
        'notes',
        'retired_at',
    ];

    protected function casts(): array
    {
        return [
            'suggested_due_date' => 'date',
            'sort_order' => 'integer',
            'include_additional_time' => 'boolean',
            'target_quantity' => 'decimal:2',
            'retired_at' => 'datetime',
        ];
    }

    public function agreement(): BelongsTo
    {
        return $this->belongsTo(Agreement::class);
    }

    public function activityType(): BelongsTo
    {
        return $this->belongsTo(ActivityType::class);
    }

    public function contactFamily(): BelongsTo
    {
        return $this->belongsTo(ContactFamily::class);
    }

    public function program(): BelongsTo
    {
        return $this->belongsTo(Program::class);
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'deliverable_user')
            ->withPivot(['assigned_at', 'unassigned_at', 'source_team_id'])
            ->withTimestamps();
    }

    public function teams(): BelongsToMany
    {
        return $this->belongsToMany(Team::class, 'deliverable_team')
            ->withPivot(['assigned_at', 'unassigned_at'])
            ->withTimestamps();
    }

    public function contributions(): HasMany
    {
        return $this->hasMany(DeliverableContribution::class, 'agreement_deliverable_id');
    }
}
