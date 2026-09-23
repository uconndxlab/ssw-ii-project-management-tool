<?php

namespace App\Models\Concerns;

use App\Enums\ProgramScopeMode;
use App\Models\Program;
use App\Models\Project;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Collection;

/**
 * @property ProgramScopeMode|string|null $program_scope_mode
 * @property-read EloquentCollection<int, Program> $programs
 */
trait HasProgramScope
{
    abstract public function programs(): BelongsToMany;

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
