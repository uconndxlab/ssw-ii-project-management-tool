<?php

namespace App\Models;

use App\Models\Concerns\VisibleToUser;
use Database\Factories\ProjectFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Collection;

/**
 * View: membership on a child program or privilege on this project.
 * Create/edit/delete: system admin only.
 */
class Project extends Model
{
    /** @use HasFactory<ProjectFactory> */
    use HasFactory, VisibleToUser;

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

    /** @return BelongsToMany<Program, $this> */
    public function programs(): BelongsToMany
    {
        return $this->belongsToMany(Program::class, 'program_project')->withTimestamps();
    }

    public function getActivitiesAttribute(): Collection
    {
        return $this->collectProgramRelation('activities');
    }

    public function getOrganizationsAttribute(): Collection
    {
        return $this->collectProgramRelation('organizations');
    }

    public function getUsersAttribute(): Collection
    {
        return $this->collectProgramRelation('users');
    }

    private function collectProgramRelation(string $relation): Collection
    {
        $programs = $this->relationLoaded('programs')
            ? $this->programs
            : $this->programs()->with($relation)->get();

        $programs->each(function (Program $program) use ($relation) {
            if (! $program->relationLoaded($relation)) {
                $program->load($relation);
            }
        });

        return $programs
            ->flatMap(function (Program $program) use ($relation) {
                return match ($relation) {
                    'activities' => $program->activities,
                    'organizations' => $program->organizations,
                    'users' => $program->users,
                    default => collect(),
                };
            })
            ->unique('id')
            ->sortBy('name')
            ->values();
    }
}
