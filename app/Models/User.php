<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Models\Concerns\HasProgramScope;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, HasProgramScope, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'role',
        'active',
        'supervisor_id',
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
        ];
    }

    public function isActive(): bool
    {
        return (bool) $this->active;
    }

    /**
     * @param  Builder<User>  $query
     * @return Builder<User>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('active', true);
    }

    /**
     * Check if user is an admin.
     */
    public function isAdmin(): bool
    {
        return $this->role === 'admin';
    }

    /**
     * Check if user is staff.
     */
    public function isStaff(): bool
    {
        return $this->role === 'staff';
    }

    /**
     * Check if user is a consultant.
     */
    public function isConsultant(): bool
    {
        return $this->role === 'consultant';
    }

    /**
     * Agreements this user is assigned to.
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

    public function principalInvestigatorAgreements(): BelongsToMany
    {
        return $this->belongsToMany(Agreement::class, 'agreement_principal_investigator')->withTimestamps();
    }

    /**
     * Programs this user is explicitly assigned to.
     */
    public function programs(): BelongsToMany
    {
        return $this->belongsToMany(Program::class, 'user_program')->withTimestamps();
    }

    public function activities(): BelongsToMany
    {
        return $this->belongsToMany(Activity::class, 'activity_user')->withTimestamps();
    }

    /**
     * Teams this user belongs to.
     */
    public function teams(): BelongsToMany
    {
        return $this->belongsToMany(Team::class, 'team_user')->withTimestamps();
    }

    /**
     * Organizations this user is associated with.
     */
    public function organizations(): BelongsToMany
    {
        return $this->belongsToMany(Organization::class, 'organization_user')->withTimestamps();
    }

    /**
     * The supervisor of this user.
     */
    public function supervisor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'supervisor_id');
    }

    public function deliverables(): BelongsToMany
    {
        return $this->belongsToMany(
            AgreementDeliverable::class,
            'deliverable_user',
            'user_id',
            'agreement_deliverable_id'
        )
            ->withPivot(['assigned_at', 'unassigned_at', 'source_team_id'])
            ->withTimestamps();
    }

    /**
     * Projects, programs, and agreements grouped by direct assignment vs team-only access.
     * Requires teams (with nested programs.projects and agreements) and direct relations loaded.
     *
     * @return array{
     *     direct: array{projects: \Illuminate\Support\Collection, programs: \Illuminate\Support\Collection, agreements: \Illuminate\Support\Collection},
     *     viaTeams: array{projects: \Illuminate\Support\Collection, programs: \Illuminate\Support\Collection, agreements: \Illuminate\Support\Collection},
     *     totals: array{projects: int, programs: int, agreements: int, teams: int},
     *     index: array{projects: \Illuminate\Support\Collection, programs: \Illuminate\Support\Collection},
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
