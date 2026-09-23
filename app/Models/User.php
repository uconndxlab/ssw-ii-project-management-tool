<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Enums\AccessProfile;
use App\Enums\ProgramScopeMode;
use App\Models\Concerns\HasProgramScope;
use App\Models\Concerns\VisibleToUser;
use App\Models\Pivots\DeliverableUserPivot;
use App\Support\Authorization\CertificationAccess;
use App\Support\Authorization\UserAccess;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Collection;

/**
 * View others: viewer/admin with overlapping membership, or they report to you.
 * Members cannot browse users. You cannot edit your own permissions.
 *
 * @property AccessProfile $access_profile
 * @property ProgramScopeMode $program_scope_mode
 * @property DeliverableUserPivot|null $pivot
 * @property Collection<int, string>|null $via_agreements
 * @property bool|null $is_principal_investigator
 * @property list<string>|null $also_in_teams
 */
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, HasProgramScope, Notifiable, VisibleToUser;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'access_profile',
        'is_supervisor',
        'active',
        'supervisor_id',
        'program_scope_mode',
        'po_number',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'active' => 'boolean',
            'is_supervisor' => 'boolean',
            'access_profile' => AccessProfile::class,
            'program_scope_mode' => ProgramScopeMode::class,
        ];
    }

    public function access(): UserAccess
    {
        return UserAccess::for($this);
    }

    public function certification(): CertificationAccess
    {
        return CertificationAccess::for($this);
    }

    /** @return HasMany<UserPrivilege, $this> */
    public function privileges(): HasMany
    {
        return $this->hasMany(UserPrivilege::class);
    }

    /** @return HasMany<UserCertificationDuty, $this> */
    public function certificationDuties(): HasMany
    {
        return $this->hasMany(UserCertificationDuty::class);
    }

    public function isActive(): bool
    {
        return (bool) $this->active;
    }

    /**
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('active', true);
    }

    public function isSystemAdmin(): bool
    {
        return $this->access()->isSystemAdmin();
    }

    public function isAdmin(): bool
    {
        return $this->access()->hasAdmin();
    }

    public function isInput(): bool
    {
        return $this->access()->isInput();
    }

    public function isSupervisor(): bool
    {
        return $this->access()->isSupervisor();
    }

    public function accessLabel(): string
    {
        return match ($this->access_profile) {
            AccessProfile::AdminViewer => $this->access()->hasAdmin() ? 'Admin' : 'Viewer',
            AccessProfile::Input => 'Input',
            default => 'User',
        };
    }

    /** @return HasMany<User, $this> */
    public function supervisees(): HasMany
    {
        return $this->hasMany(User::class, 'supervisor_id');
    }

    /**
     * Agreements this user is assigned to.
     *
     * @return BelongsToMany<Agreement, $this>
     */
    public function agreements(): BelongsToMany
    {
        return $this->belongsToMany(Agreement::class, 'agreement_user')->withTimestamps();
    }

    /**
     * @return Builder<Agreement>
     */
    public function accessibleAgreementsQuery(): Builder
    {
        return Agreement::query()->accessibleBy($this);
    }

    public function hasAccessToAgreement(Agreement|int $agreement): bool
    {
        $agreementId = $agreement instanceof Agreement ? (int) $agreement->id : (int) $agreement;

        return $this->accessibleAgreementsQuery()->whereKey($agreementId)->exists();
    }

    /** @return BelongsToMany<Agreement, $this> */
    public function principalInvestigatorAgreements(): BelongsToMany
    {
        return $this->belongsToMany(Agreement::class, 'agreement_principal_investigator')->withTimestamps();
    }

    /**
     * Programs this user is explicitly assigned to.
     *
     * @return BelongsToMany<Program, $this>
     */
    public function programs(): BelongsToMany
    {
        return $this->belongsToMany(Program::class, 'user_program')->withTimestamps();
    }

    /** @return BelongsToMany<Activity, $this> */
    public function activities(): BelongsToMany
    {
        return $this->belongsToMany(Activity::class, 'activity_user')->withTimestamps();
    }

    /**
     * Teams this user belongs to.
     *
     * @return BelongsToMany<Team, $this>
     */
    public function teams(): BelongsToMany
    {
        return $this->belongsToMany(Team::class, 'team_user')->withTimestamps();
    }

    /**
     * Organizations this user is associated with.
     *
     * @return BelongsToMany<Organization, $this>
     */
    public function organizations(): BelongsToMany
    {
        return $this->belongsToMany(Organization::class, 'organization_user')->withTimestamps();
    }

    /**
     * The supervisor of this user.
     *
     * @return BelongsTo<User, $this>
     */
    public function supervisor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'supervisor_id');
    }

    /** @return BelongsToMany<AgreementDeliverable, $this, DeliverableUserPivot> */
    public function deliverables(): BelongsToMany
    {
        return $this->belongsToMany(
            AgreementDeliverable::class,
            'deliverable_user',
            'user_id',
            'agreement_deliverable_id'
        )
            ->withPivot(['assigned_at', 'unassigned_at', 'source_team_id', 'target_quantity'])
            ->using(DeliverableUserPivot::class)
            ->withTimestamps();
    }

    /**
     * Projects, programs, and agreements grouped by direct assignment vs team-only access.
     * Requires teams (with nested programs.projects and agreements) and direct relations loaded.
     *
     * @return array{
     *     direct: array{projects: Collection, programs: Collection, agreements: Collection},
     *     viaTeams: array{projects: Collection, programs: Collection, agreements: Collection},
     *     totals: array{projects: int, programs: int, agreements: int, teams: int},
     *     index: array{projects: Collection, programs: Collection},
     * }
     */
    public function getScopeBySource(): array
    {
        $directProjects = $this->projects;
        $directPrograms = $this->programs;
        $directAgreements = $this->agreements;

        $directProjectIds = $directProjects->pluck('id');
        $directProgramIds = $directPrograms->pluck('id');
        $directAgreementIds = $directAgreements->pluck('id');

        $teamOnlyProjects = collect();
        foreach ($this->teams as $team) {
            foreach ($team->projects as $project) {
                if ($directProjectIds->contains($project->id)) {
                    continue;
                }
                $teamOnlyProjects->put($project->id, $project);
            }
        }
        $teamOnlyProjects = $teamOnlyProjects->sortBy('name')->values();

        $teamOnlyPrograms = collect();
        foreach ($this->teams as $team) {
            foreach ($team->programs as $program) {
                if ($directProgramIds->contains($program->id)) {
                    continue;
                }
                $teamOnlyPrograms->put($program->id, $program);
            }
        }
        $teamOnlyPrograms = $teamOnlyPrograms->sortBy('name')->values();

        $viaTeamAgreements = [];
        foreach ($this->teams as $team) {
            foreach ($team->agreements as $agreement) {
                if ($directAgreementIds->contains($agreement->id)) {
                    continue;
                }
                $agreementId = $agreement->id;
                if (! isset($viaTeamAgreements[$agreementId])) {
                    $viaTeamAgreements[$agreementId] = [
                        'agreement' => $agreement,
                        'teams' => collect(),
                    ];
                }
                $viaTeamAgreements[$agreementId]['teams']->push($team);
            }
        }

        $viaTeamAgreementRows = collect($viaTeamAgreements)
            ->map(function (array $row) {
                $row['teams'] = $row['teams']->unique('id')->sortBy('name')->values();

                return $row;
            })
            ->sortBy(fn (array $row) => $row['agreement']->name)
            ->values();

        $allProjects = $directProjects->merge($teamOnlyProjects)->unique('id');
        $allPrograms = $directPrograms->merge($teamOnlyPrograms)->unique('id');
        $allAgreements = $directAgreements->merge($viaTeamAgreementRows->pluck('agreement'))->unique('id');

        $indexProjects = $directProjects->sortBy('name')->map(fn ($project) => [
            'model' => $project,
            'viaTeam' => false,
            'teamNames' => null,
        ])->concat($teamOnlyProjects->map(fn ($project) => [
            'model' => $project,
            'viaTeam' => true,
            'teamNames' => $this->teamsProvidingRelation('projects', $project->id)->pluck('name')->join(', '),
        ]))->values();

        $indexPrograms = $directPrograms->sortBy('name')->map(fn ($program) => [
            'model' => $program,
            'viaTeam' => false,
            'teamNames' => null,
        ])->concat($teamOnlyPrograms->map(fn ($program) => [
            'model' => $program,
            'viaTeam' => true,
            'teamNames' => $this->teamsProvidingRelation('programs', $program->id)->pluck('name')->join(', '),
        ]))->values();

        return [
            'direct' => [
                'projects' => $directProjects->sortBy('name')->values(),
                'programs' => $directPrograms->sortBy('name')->values(),
                'agreements' => $directAgreements->sortBy('name')->values(),
            ],
            'viaTeams' => [
                'projects' => $teamOnlyProjects,
                'programs' => $teamOnlyPrograms,
                'agreements' => $viaTeamAgreementRows,
            ],
            'totals' => [
                'projects' => $allProjects->count(),
                'programs' => $allPrograms->count(),
                'agreements' => $allAgreements->count(),
                'teams' => $this->teams->count(),
            ],
            'index' => [
                'projects' => $indexProjects,
                'programs' => $indexPrograms,
            ],
        ];
    }

    /**
     * Teams this user belongs to that grant access to the given related entity.
     */
    private function teamsProvidingRelation(string $relation, int $entityId)
    {
        return $this->teams->filter(function ($team) use ($relation, $entityId) {
            return $team->{$relation}->contains('id', $entityId);
        })->sortBy('name')->values();
    }
}
