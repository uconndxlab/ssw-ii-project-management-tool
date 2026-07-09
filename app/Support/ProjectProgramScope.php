<?php

namespace App\Support;

use App\Models\Program;
use App\Models\Project;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

class ProjectProgramScope
{
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

        $programProjectIds = Program::query()
            ->whereKey($programIds)
            ->pluck('project_id', 'id');

        $invalidPrograms = $programProjectIds
            ->filter(fn ($projectId) => !in_array((int) $projectId, $projectIds, true))
            ->keys();

        if ($invalidPrograms->isNotEmpty()) {
            $validator->errors()->add($programKey, 'Each selected program must belong to one of the selected projects.');
        }
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
}
