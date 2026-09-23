<?php

namespace App\Models\Concerns;

use App\Models\Program;
use App\Models\Project;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;

/**
 * @property-read EloquentCollection<int, Program> $programs
 */
trait HasProgramScope
{
    /**
     * Projects are display/filter context inferred from the persisted programs.
     *
     * @return Collection<int, Project>
     */
    public function getProjectsAttribute(): Collection
    {
        $programs = $this->relationLoaded('programs')
            ? $this->programs
            : $this->programs()->with('projects')->get();

        $programs->each(function (Program $program) {
            if (! $program->relationLoaded('projects')) {
                $program->load('projects');
            }
        });

        return $programs
            ->flatMap(fn (Program $program) => $program->projects)
            ->unique('id')
            ->sortBy('name')
            ->values();
    }
}
