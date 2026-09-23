<?php

namespace App\Models;

use App\Enums\ProgramScopeMode;
use App\Models\Concerns\HasProgramScope;
use App\Models\Concerns\VisibleToUser;
use App\Models\Pivots\DeliverableTeamPivot;
use Database\Factories\TeamFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * View: you are on the team, or a listed program is in your privilege.
 * Edit: you admin a listed program. Delete: every listed program is in your admin scope. No programs: system admin only.
 *
 * @property ProgramScopeMode $program_scope_mode
 * @property DeliverableTeamPivot|null $pivot
 */
class Team extends Model
{
    /** @use HasFactory<TeamFactory> */
    use HasFactory, HasProgramScope, VisibleToUser;

    protected $fillable = [
        'name',
        'active',
        'program_scope_mode',
    ];

    protected function casts(): array
    {
        return [
            'active' => 'boolean',
            'program_scope_mode' => ProgramScopeMode::class,
        ];
    }

    /** @return BelongsToMany<User, $this> */
    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'team_user')->withTimestamps();
    }

    /** @return BelongsToMany<Agreement, $this> */
    public function agreements(): BelongsToMany
    {
        return $this->belongsToMany(Agreement::class, 'agreement_team')->withTimestamps();
    }

    /** @return BelongsToMany<Program, $this> */
    public function programs(): BelongsToMany
    {
        return $this->belongsToMany(Program::class, 'team_program')->withTimestamps();
    }

    /** @return BelongsToMany<AgreementDeliverable, $this, DeliverableTeamPivot> */
    public function deliverables(): BelongsToMany
    {
        return $this->belongsToMany(
            AgreementDeliverable::class,
            'deliverable_team',
            'team_id',
            'agreement_deliverable_id'
        )
            ->withPivot(['assigned_at', 'unassigned_at', 'target_quantity'])
            ->using(DeliverableTeamPivot::class)
            ->withTimestamps();
    }
}
