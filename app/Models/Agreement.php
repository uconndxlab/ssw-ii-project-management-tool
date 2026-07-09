<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Builder;

class Agreement extends Model
{
    protected $fillable = [
        'name',
        'project_id',
        'abstract',
        'start_date',
        'end_date',
        'extension_start_date',
        'extension_end_date',
    ];

    protected function casts(): array
    {
        return [
            'start_date' => 'date',
            'end_date' => 'date',
            'extension_start_date' => 'date',
            'extension_end_date' => 'date',
        ];
    }

    public function organizations(): BelongsToMany
    {
        return $this->belongsToMany(Organization::class, 'agreement_organization')->withTimestamps();
    }

    public function states(): BelongsToMany
    {
        return $this->belongsToMany(State::class, 'agreement_state')->withTimestamps();
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'agreement_user')->withTimestamps();
    }

    public function teams(): BelongsToMany
    {
        return $this->belongsToMany(Team::class, 'agreement_team')->withTimestamps();
    }

    public function principalInvestigators(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'agreement_principal_investigator')->withTimestamps();
    }

    public function activities(): BelongsToMany
    {
        return $this->belongsToMany(Activity::class, 'activity_agreement')->withTimestamps();
    }

    public function agreementLoggingFields(): BelongsToMany
    {
        return $this->belongsToMany(LoggingField::class, 'agreement_logging_field_assignments', 'agreement_id', 'logging_field_id')
            ->withPivot('is_required')
            ->withTimestamps()
            ->orderBy('sort_order', 'asc')
            ->orderBy('name', 'asc');
    }

    public function loggingFields(): BelongsToMany
    {
        return $this->agreementLoggingFields();
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(AgreementAttachment::class);
    }

    public function programs(): BelongsToMany
    {
        return $this->belongsToMany(Program::class, 'agreement_program')->withTimestamps();
    }

    public function projects(): BelongsToMany
    {
        return $this->belongsToMany(Project::class, 'agreement_project')->withTimestamps();
    }

    public function deliverables(): HasMany
    {
        return $this->hasMany(AgreementDeliverable::class);
    }

    public function certificationCandidates(): HasMany
    {
        return $this->hasMany(AgreementCertificationCandidate::class)->orderBy('id');
    }

    /**
     * Get all users assigned to this agreement (both directly and via teams).
     */
    public function allUsers()
    {
        $directUsers = $this->users;
        $teamUsers = $this->teams->flatMap(function ($team) {
            return $team->users;
        });

        return $directUsers->merge($teamUsers)->unique('id');
    }

    /**
     * Get users grouped by source (direct assignment vs team membership).
     * Returns array with 'direct' => Collection and 'teams' => ['Team Name' => Collection].
     * Users assigned both directly and via teams appear only in 'direct' with team info.
     */
    public function getUsersBySource(): array
    {
        $directUsers = $this->users;
        $directUserIds = $directUsers->pluck('id');
        $principalInvestigatorIds = $this->relationLoaded('principalInvestigators')
            ? $this->principalInvestigators->pluck('id')
            : $this->principalInvestigators()->pluck('users.id');

        $teamGroups = [];
        foreach ($this->teams as $team) {
            // Only include users not already directly assigned
            $teamOnlyUsers = $team->users
                ->whereNotIn('id', $directUserIds)
                ->map(function ($user) use ($principalInvestigatorIds) {
                    $user->is_principal_investigator = $principalInvestigatorIds->contains($user->id);

                    return $user;
                });

            if ($teamOnlyUsers->isNotEmpty()) {
                $teamGroups[$team->name] = $teamOnlyUsers;
            }
        }

        // Add team membership info to direct users
        $directUsersWithTeams = $directUsers->map(function ($user) use ($principalInvestigatorIds) {
            $userTeams = $this->teams->filter(function ($team) use ($user) {
                return $team->users->contains('id', $user->id);
            });

            $user->also_in_teams = $userTeams->pluck('name')->toArray();
            $user->is_principal_investigator = $principalInvestigatorIds->contains($user->id);

            return $user;
        });

        return [
            'direct' => $directUsersWithTeams,
            'teams' => $teamGroups,
        ];
    }

    public function scopeActive(Builder $query): Builder
    {
        $today = now()->toDateString();

        return $query
            ->where(function (Builder $builder) use ($today) {
                $builder
                    ->whereNull('start_date')
                    ->orWhereDate('start_date', '<=', $today);
            })
            ->where(function (Builder $builder) use ($today) {
                $builder
                    ->whereNull('extension_end_date')
                    ->where(function (Builder $nested) use ($today) {
                        $nested
                            ->whereNull('end_date')
                            ->orWhereDate('end_date', '>=', $today);
                    })
                    ->orWhereDate('extension_end_date', '>=', $today);
            });
    }

    public function scopeCurrent(Builder $query): Builder
    {
        return $this->scopeActive($query);
    }
}
