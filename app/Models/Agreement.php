<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Agreement extends Model
{
    protected $fillable = [
        'name',
        'abstract',
        'start_date',
        'end_date',
        'extension_start_date',
        'extension_end_date',
        'certification_candidates',
        'activity_logging_config',
        'time_tracking_mode',
    ];

    protected function casts(): array
    {
        return [
            'start_date' => 'date',
            'end_date' => 'date',
            'extension_start_date' => 'date',
            'extension_end_date' => 'date',
            'activity_logging_config' => 'array',
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

    public function activities(): BelongsToMany
    {
        return $this->belongsToMany(Activity::class, 'activity_agreement')->withTimestamps();
    }

    public function deliverables(): HasMany
    {
        return $this->hasMany(AgreementDeliverable::class);
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(AgreementAttachment::class);
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

        $teamGroups = [];
        foreach ($this->teams as $team) {
            // Only include users not already directly assigned
            $teamOnlyUsers = $team->users->whereNotIn('id', $directUserIds);
            if ($teamOnlyUsers->isNotEmpty()) {
                $teamGroups[$team->name] = $teamOnlyUsers;
            }
        }

        // Add team membership info to direct users
        $directUsersWithTeams = $directUsers->map(function ($user) {
            $userTeams = $this->teams->filter(function ($team) use ($user) {
                return $team->users->contains('id', $user->id);
            });
            
            $user->also_in_teams = $userTeams->pluck('name')->toArray();
            return $user;
        });

        return [
            'direct' => $directUsersWithTeams,
            'teams' => $teamGroups,
        ];
    }
}
