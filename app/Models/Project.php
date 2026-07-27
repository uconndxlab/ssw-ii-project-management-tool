<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Collection;

class Project extends Model
{
    use HasFactory;

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

        $programs->each(function ($program) use ($relation) {
            if (! $program->relationLoaded($relation)) {
                $program->load($relation);
            }
        });

        return $programs
            ->flatMap(fn ($program) => $program->{$relation})
            ->unique('id')
            ->sortBy('name')
            ->values();
    }
}
