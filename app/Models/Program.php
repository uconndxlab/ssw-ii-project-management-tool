<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;

class Program extends Model
{
    protected $fillable = [
        'name',
        'description',
        'active',
    ];

    protected function casts(): array
    {
        return [
            'active' => 'boolean',
        ];
    }

    public function activities(): BelongsToMany
    {
        return $this->belongsToMany(Activity::class, 'activity_program')->withTimestamps();
    }

    public function agreements(): BelongsToMany
    {
        return $this->belongsToMany(Agreement::class, 'agreement_program')->withTimestamps();
    }

    public function agreementCertificationCandidates(): HasMany
    {
        return $this->hasMany(AgreementCertificationCandidate::class);
    }

    public function projects(): BelongsToMany
    {
        return $this->belongsToMany(Project::class, 'program_project')->withTimestamps();
    }

    public function organizations(): BelongsToMany
    {
        return $this->belongsToMany(Organization::class, 'organization_program')->withTimestamps();
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'user_program')->withTimestamps();
    }

    /**
     * Active agreements linked via agreement_program, plus any linked to activities on this program.
     */
    public function agreementsForDisplay(): Collection
    {
        $pivotIds = $this->agreements()
            ->where('agreements.active', true)
            ->pluck('agreements.id');

        $activityLinkedIds = Agreement::query()
            ->where('active', true)
            ->whereHas('activities', function ($query) {
                $query->whereHas('programs', fn ($programQuery) => $programQuery->where('programs.id', $this->id));
            })
            ->pluck('id');

        return Agreement::query()
            ->whereIn('id', $pivotIds->merge($activityLinkedIds)->unique())
            ->with('states')
            ->orderBy('name')
            ->get();
    }
}
