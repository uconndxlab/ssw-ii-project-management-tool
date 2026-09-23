<?php

namespace App\Models;

use App\Models\Pivots\DeliverableTeamPivot;
use App\Models\Pivots\DeliverableUserPivot;
use Database\Factories\AgreementDeliverableFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property DeliverableUserPivot|null $pivot
 */
class AgreementDeliverable extends Model
{
    /** @use HasFactory<AgreementDeliverableFactory> */
    use HasFactory;

    protected $fillable = [
        'agreement_id',
        'activity_type_id',
        'contact_family_id',
        'program_id',
        'metric_type',
        'time_basis',
        'allotted_time_unit',
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

    /** @return BelongsTo<Agreement, $this> */
    public function agreement(): BelongsTo
    {
        return $this->belongsTo(Agreement::class);
    }

    /** @return BelongsTo<ActivityType, $this> */
    public function activityType(): BelongsTo
    {
        return $this->belongsTo(ActivityType::class);
    }

    /** @return BelongsTo<ContactFamily, $this> */
    public function contactFamily(): BelongsTo
    {
        return $this->belongsTo(ContactFamily::class);
    }

    /** @return BelongsTo<Program, $this> */
    public function program(): BelongsTo
    {
        return $this->belongsTo(Program::class);
    }

    /** @return BelongsToMany<User, $this, DeliverableUserPivot> */
    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'deliverable_user')
            ->withPivot(['assigned_at', 'unassigned_at', 'source_team_id', 'target_quantity'])
            ->using(DeliverableUserPivot::class)
            ->withTimestamps();
    }

    /** @return BelongsToMany<Team, $this, DeliverableTeamPivot> */
    public function teams(): BelongsToMany
    {
        return $this->belongsToMany(Team::class, 'deliverable_team')
            ->withPivot(['assigned_at', 'unassigned_at', 'target_quantity'])
            ->using(DeliverableTeamPivot::class)
            ->withTimestamps();
    }

    /** @return HasMany<DeliverableContribution, $this> */
    public function contributions(): HasMany
    {
        return $this->hasMany(DeliverableContribution::class, 'agreement_deliverable_id');
    }
}
