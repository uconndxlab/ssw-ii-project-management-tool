<?php

namespace App\Support;

use App\Enums\ProgramScopeMode;
use App\Models\Program;
use App\Models\Team;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;

class ProgramParticipantDirectory
{
    /**
     * Unique users for the given programs: direct program assignment plus active team membership.
     *
     * @param  Collection<int, Program>  $programs
     * @return array{
     *     user_ids_by_program_id: array<string, list<string>>,
     *     users: Collection<int, User>
     * }
     */
    public static function build(Collection $programs): array
    {
        $programs = new EloquentCollection($programs->unique('id')->values()->all());

        if ($programs->isEmpty()) {
            return [
                'user_ids_by_program_id' => [],
                'users' => collect(),
            ];
        }

        $programs->loadMissing(['users:id,name,email,role,active']);

        $programIds = $programs->pluck('id')->map(fn ($id) => (int) $id)->all();

        $teams = Team::query()
            ->where('active', true)
            ->where(function ($query) use ($programIds) {
                $query->where('program_scope_mode', ProgramScopeMode::All->value)
                    ->orWhere(function ($specificQuery) use ($programIds) {
                        $specificQuery
                            ->where('program_scope_mode', ProgramScopeMode::Specific->value)
                            ->whereHas('programs', fn ($relation) => $relation->whereIn('programs.id', $programIds));
                    });
            })
            ->with(['users:id,name,email,role,active', 'programs:id'])
            ->get();

        $usersById = $programs
            ->flatMap(fn (Program $program) => $program->users)
            ->concat($teams->flatMap(fn (Team $team) => $team->users))
            ->unique('id')
            ->keyBy(fn (User $user) => (int) $user->id);

        $allScopeTeamUserIds = $teams
            ->filter(fn (Team $team) => $team->program_scope_mode === ProgramScopeMode::All)
            ->flatMap(fn (Team $team) => $team->users->pluck('id'))
            ->map(fn ($id) => (string) $id)
            ->unique()
            ->values()
            ->all();

        $specificTeamUserIdsByProgram = [];
        foreach ($teams as $team) {
            if ($team->program_scope_mode !== ProgramScopeMode::Specific) {
                continue;
            }

            $teamUserIds = $team->users->pluck('id')->map(fn ($id) => (string) $id)->all();

            foreach ($team->programs as $program) {
                $programId = (string) $program->id;
                $specificTeamUserIdsByProgram[$programId] = array_values(array_unique(array_merge(
                    $specificTeamUserIdsByProgram[$programId] ?? [],
                    $teamUserIds
                )));
            }
        }

        $userIdsByProgramId = [];

        foreach ($programs as $program) {
            $programId = (string) $program->id;

            $userIdsByProgramId[$programId] = $program->users
                ->pluck('id')
                ->map(fn ($id) => (string) $id)
                ->concat($allScopeTeamUserIds)
                ->concat($specificTeamUserIdsByProgram[$programId] ?? [])
                ->unique()
                ->values()
                ->all();
        }

        return [
            'user_ids_by_program_id' => $userIdsByProgramId,
            'users' => $usersById->sortBy('name')->values(),
        ];
    }

    /**
     * @param  array<int, int|string>  $programIds
     * @return list<int>
     */
    public static function allowedUserIdsForProgramIds(array $programIds): array
    {
        $programIds = ProjectProgramScope::normalizeIds($programIds);

        if ($programIds === []) {
            return [];
        }

        $directory = self::build(Program::query()->whereKey($programIds)->get(['id']));

        return collect($programIds)
            ->flatMap(fn ($id) => $directory['user_ids_by_program_id'][(string) $id] ?? [])
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();
    }
}
