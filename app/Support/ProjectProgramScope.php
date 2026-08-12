<?php

namespace App\Support;

use App\Enums\ProgramScopeMode;
use App\Models\Program;
use App\Models\Project;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

class ProjectProgramScope
{
    public static function defaultModeForModel(string $modelClass): ProgramScopeMode
    {
        return match ($modelClass) {
            \App\Models\LoggingField::class,
            \App\Models\ContactFamily::class,
            \App\Models\ActivityType::class => ProgramScopeMode::All,
            default => ProgramScopeMode::Specific,
        };
    }

    public static function normalizeMode(ProgramScopeMode|string|null $mode, string $modelClass): ProgramScopeMode
    {
        if ($mode instanceof ProgramScopeMode) {
            return $mode;
        }

        if (is_string($mode) && $mode !== '') {
            return ProgramScopeMode::from($mode);
        }

        return self::defaultModeForModel($modelClass);
    }

    public static function activeProjectsWithPrograms()
    {
        return Project::query()
            ->where('active', true)
            ->with([
                'programs' => fn ($query) => $query
                    ->where('active', true)
                    ->orderBy('name'),
            ])
            ->orderBy('name')
            ->get();
    }

    public static function normalizeIds(array $ids): array
    {
        return collect($ids)
            ->filter(fn ($id) => $id !== null && $id !== '')
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();
    }

    public static function validateSelection($validator, array $projectIds, array $programIds, string $projectKey = 'project_ids', string $programKey = 'program_ids'): void
    {
        if (!empty($programIds) && empty($projectIds)) {
            $validator->errors()->add($projectKey, 'Select at least one project before assigning programs.');

            return;
        }

        if (empty($programIds)) {
            return;
        }

        $projectIds = self::normalizeIds($projectIds);
        $programIds = self::normalizeIds($programIds);

        $validProgramIds = Program::query()
            ->whereKey($programIds)
            ->whereHas('projects', fn ($query) => $query->whereIn('projects.id', $projectIds))
            ->pluck('id')
            ->map(fn ($id) => (int) $id);

        $invalidPrograms = collect($programIds)
            ->map(fn ($id) => (int) $id)
            ->diff($validProgramIds);

        if ($invalidPrograms->isNotEmpty()) {
            $validator->errors()->add($programKey, 'Each selected program must belong to one of the selected projects.');
        }
    }

    /**
     * Program IDs implied by project/program scope when the form leaves programs empty (all programs in scope).
     */
    public static function effectiveProgramIds(array $projectIds, array $programIds): array
    {
        $projectIds = self::normalizeIds($projectIds);
        $programIds = self::normalizeIds($programIds);

        if ($programIds !== []) {
            return $programIds;
        }

        if ($projectIds === []) {
            return [];
        }

        return Program::query()
            ->where('active', true)
            ->whereHas('projects', fn ($relation) => $relation->whereIn('projects.id', $projectIds))
            ->pluck('id')
            ->all();
    }

    public static function projectIdsForPrograms(array $programIds): array
    {
        $programIds = self::normalizeIds($programIds);

        if ($programIds === []) {
            return [];
        }

        return Project::query()
            ->whereHas('programs', fn ($relation) => $relation->whereIn('programs.id', $programIds))
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();
    }

    public static function matchesSelectedPrograms(Collection $scopedProgramIds, array $selectedProgramIds, bool $allowGlobal): bool
    {
        if ($scopedProgramIds->isEmpty()) {
            return $allowGlobal;
        }

        if (empty($selectedProgramIds)) {
            return false;
        }

        return $scopedProgramIds
            ->map(fn ($id) => (int) $id)
            ->intersect(collect($selectedProgramIds)->map(fn ($id) => (int) $id))
            ->isNotEmpty();
    }

    public static function modeAwareProgramIds(ProgramScopeMode|string|null $mode, string $modelClass, array $projectIds, array $programIds): array
    {
        $normalizedMode = self::normalizeMode($mode, $modelClass);

        return match ($normalizedMode) {
            ProgramScopeMode::All, ProgramScopeMode::None => [],
            ProgramScopeMode::Specific => self::normalizeIds($programIds),
        };
    }

    public static function modeAllowsAnyPrograms(ProgramScopeMode|string|null $mode, string $modelClass): bool
    {
        return self::normalizeMode($mode, $modelClass) !== ProgramScopeMode::None;
    }

    public static function validateModeSelection(
        $validator,
        ProgramScopeMode|string|null $mode,
        string $modelClass,
        array $projectIds,
        array $programIds,
        string $projectKey = 'project_ids',
        string $programKey = 'program_ids'
    ): void {
        $normalizedMode = self::normalizeMode($mode, $modelClass);

        if ($normalizedMode !== ProgramScopeMode::Specific) {
            return;
        }

        if (empty($programIds)) {
            $validator->errors()->add($programKey, 'Select at least one program when Specific scope is selected.');

            return;
        }

        self::validateSelection($validator, $projectIds, $programIds, $projectKey, $programKey);
    }

    public static function validateScopedAssignments(
        $validator,
        array $selectedProgramIds,
        array $selectedEntityIds,
        string $modelClass,
        string $errorKey,
        string $message,
        bool $allowGlobal = true
    ): void {
        if (empty($selectedEntityIds)) {
            return;
        }

        /** @var Collection<int, Model> $entities */
        $entities = $modelClass::query()
            ->whereKey($selectedEntityIds)
            ->with('programs:id')
            ->get();

        $invalidEntityIds = $entities
            ->filter(fn (Model $entity) => !self::matchesSelectedPrograms(
                $entity->programs->pluck('id'),
                $selectedProgramIds,
                $allowGlobal
            ))
            ->pluck('id');

        if ($invalidEntityIds->isNotEmpty()) {
            $validator->errors()->add($errorKey, $message);
        }
    }

    /**
     * Programs that would have no projects if the given project were deleted.
     *
     * @return Collection<int, Program>
     */
    public static function programsOrphanedByDeletingProject(Project $project): Collection
    {
        return Program::query()
            ->whereHas('projects', fn ($query) => $query->whereKey($project->id))
            ->whereDoesntHave('projects', fn ($query) => $query->where('projects.id', '!=', $project->id))
            ->orderBy('name')
            ->get();
    }

    /**
     * View data for the project/program scope picker Blade component.
     *
     * @param  Collection<int, Project>  $projects
     * @return array{
     *     scopeProjects: Collection,
     *     selectedProjectIds: list<string>,
     *     selectedProgramIds: list<string>,
     *     programOptions: list<array<string, mixed>>,
     *     programProjectIdsMap: array<string, list<string>>,
     *     projectProgramMap: array<string, list<string>>,
     *     projectNamesMap: array<string, string>,
     *     projectPickerId: string,
     *     programPickerId: string,
     * }
     */
    public static function scopePickerViewData(
        Collection $projects,
        array $selectedProjectIds,
        array $selectedProgramIds,
        string $scopeId,
        string $programBadgeClass = 'bg-primary-subtle text-primary-emphasis border',
    ): array {
        $scopeProjects = $projects instanceof Collection ? $projects : collect($projects);

        $selectedProjectIds = collect($selectedProjectIds)
            ->map(fn ($id) => (string) $id)
            ->values()
            ->all();

        $selectedProgramIds = collect($selectedProgramIds)
            ->map(fn ($id) => (string) $id)
            ->values()
            ->all();

        $programsById = [];

        foreach ($scopeProjects as $project) {
            foreach ($project->programs as $program) {
                $programKey = (string) $program->id;

                if (!isset($programsById[$programKey])) {
                    $programsById[$programKey] = [
                        'id' => $program->id,
                        'name' => $program->name,
                        'projectIds' => [],
                    ];
                }

                $programsById[$programKey]['projectIds'][] = (string) $project->id;
            }
        }

        if ($selectedProjectIds === [] && $selectedProgramIds !== []) {
            $selectedProgramIdSet = collect($selectedProgramIds);
            $selectedProjectIds = collect($programsById)
                ->filter(fn ($program, $programId) => $selectedProgramIdSet->contains((string) $programId))
                ->flatMap(fn ($program) => $program['projectIds'])
                ->unique()
                ->values()
                ->all();
        }

        $programOptions = collect($programsById)
            ->sortBy(fn ($program) => $program['name'])
            ->values()
            ->map(function ($program) use ($programBadgeClass, $selectedProjectIds, $scopeProjects) {
                $contextProjectIds = collect($program['projectIds'])
                    ->intersect($selectedProjectIds)
                    ->values();

                $contextNames = $contextProjectIds
                    ->map(fn ($projectId) => optional($scopeProjects->firstWhere('id', (int) $projectId))->name)
                    ->filter()
                    ->values()
                    ->all();

                return [
                    'id' => $program['id'],
                    'name' => $program['name'],
                    'contextLabels' => $contextNames,
                    'contextBadgeClass' => $programBadgeClass,
                ];
            })
            ->all();

        $programProjectIdsMap = collect($programsById)
            ->mapWithKeys(fn ($program, $programId) => [
                $programId => collect($program['projectIds'])->unique()->values()->all(),
            ])
            ->all();

        $projectProgramMap = $scopeProjects->mapWithKeys(function ($project) {
            return [
                (string) $project->id => $project->programs
                    ->pluck('id')
                    ->map(fn ($id) => (string) $id)
                    ->values()
                    ->all(),
            ];
        })->all();

        $projectNamesMap = $scopeProjects->mapWithKeys(fn ($project) => [
            (string) $project->id => $project->name,
        ])->all();

        return [
            'scopeProjects' => $scopeProjects,
            'selectedProjectIds' => $selectedProjectIds,
            'selectedProgramIds' => $selectedProgramIds,
            'programOptions' => $programOptions,
            'programProjectIdsMap' => $programProjectIdsMap,
            'projectProgramMap' => $projectProgramMap,
            'projectNamesMap' => $projectNamesMap,
            'projectPickerId' => $scopeId.'-projects',
            'programPickerId' => $scopeId.'-programs',
        ];
    }
}
