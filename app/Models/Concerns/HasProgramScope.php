<?php

namespace App\Models\Concerns;

use Illuminate\Support\Collection;

trait HasProgramScope
{
    /**
     * Projects are display/filter context inferred from the persisted programs.
     *
     * @return Collection<int, \App\Models\Project>
     */
    public function getProjectsAttribute(): Collection
    {
        $programs = $this->relationLoaded('programs')
            ? $this->programs
            : $this->programs()->with('projects')->get();

        $programs->each(function ($program) {
            if (! $program->relationLoaded('projects')) {
                $program->load('projects');
            }
        });

        return $programs
            ->flatMap(fn ($program) => $program->projects)
            ->unique('id')
            ->sortBy('name')
            ->values();
    }
}
