<?php

namespace App\Support;

use App\Models\Program;
use App\Models\Project;

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
}
