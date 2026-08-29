<?php

namespace App\Support\Authorization;

use App\Enums\ProgramScopeMode;
use App\Models\User;
use Illuminate\Validation\Validator;

class ScopeSync
{
    /**
     * Merge submitted program IDs with assignments the actor cannot change.
     *
     * @param  list<int>  $existingProgramIds
     * @param  list<int>  $submittedProgramIds
     * @return array{mode: ProgramScopeMode, programIds: list<int>}
     */
    public static function mergePrograms(
        User $actor,
        ?ProgramScopeMode $existingMode,
        array $existingProgramIds,
        ProgramScopeMode $submittedMode,
        array $submittedProgramIds,
    ): array {
        $access = UserAccess::for($actor);
        $existingMode ??= ProgramScopeMode::None;
        $existingProgramIds = self::normalizeIds($existingProgramIds);
        $submittedProgramIds = self::normalizeIds($submittedProgramIds);

        if ($access->isSystemAdmin()) {
            return [
                'mode' => $submittedMode,
                'programIds' => $submittedMode === ProgramScopeMode::Specific ? $submittedProgramIds : [],
            ];
        }

        if ($existingMode === ProgramScopeMode::All) {
            return ['mode' => ProgramScopeMode::All, 'programIds' => []];
        }

        if ($submittedMode === ProgramScopeMode::All) {
            return [
                'mode' => $existingMode,
                'programIds' => $existingProgramIds,
            ];
        }

        $adminProgramIds = $access->adminProgramIds();
        $frozen = array_values(array_diff($existingProgramIds, $adminProgramIds));
        $inScopeSubmitted = array_values(array_intersect($submittedProgramIds, $adminProgramIds));
        $merged = array_values(array_unique(array_merge($frozen, $inScopeSubmitted)));

        if ($submittedMode === ProgramScopeMode::None && $frozen === []) {
            return ['mode' => ProgramScopeMode::None, 'programIds' => []];
        }

        if ($merged === []) {
            return ['mode' => ProgramScopeMode::None, 'programIds' => []];
        }

        return ['mode' => ProgramScopeMode::Specific, 'programIds' => $merged];
    }

    /**
     * @param  list<int>  $existingProjectIds
     * @param  list<int>  $submittedProjectIds
     * @return list<int>
     */
    public static function mergeProjectIds(
        User $actor,
        array $existingProjectIds,
        array $submittedProjectIds,
    ): array {
        $access = UserAccess::for($actor);
        $existingProjectIds = self::normalizeIds($existingProjectIds);
        $submittedProjectIds = self::normalizeIds($submittedProjectIds);

        if ($access->isSystemAdmin()) {
            return $submittedProjectIds;
        }

        $adminProjectIds = $access->adminProjectIds();
        $frozen = array_values(array_diff($existingProjectIds, $adminProjectIds));
        $inScopeSubmitted = array_values(array_intersect($submittedProjectIds, $adminProjectIds));

        return array_values(array_unique(array_merge($frozen, $inScopeSubmitted)));
    }

    /**
     * Clip a source record's programs to the actor's admin scope for duplication.
     *
     * @param  list<int>  $sourceProgramIds
     * @return array{mode: ProgramScopeMode, programIds: list<int>}
     */
    public static function clipProgramsForDuplicate(
        User $actor,
        ?ProgramScopeMode $sourceMode,
        array $sourceProgramIds,
    ): array {
        $access = UserAccess::for($actor);
        $sourceProgramIds = self::normalizeIds($sourceProgramIds);

        if ($access->isSystemAdmin()) {
            return [
                'mode' => $sourceMode ?? ProgramScopeMode::Specific,
                'programIds' => ($sourceMode === ProgramScopeMode::All || $sourceMode === ProgramScopeMode::None)
                    ? []
                    : $sourceProgramIds,
            ];
        }

        $adminProgramIds = $access->adminProgramIds();

        if ($sourceMode === ProgramScopeMode::All) {
            return [
                'mode' => ProgramScopeMode::Specific,
                'programIds' => $adminProgramIds,
            ];
        }

        $clipped = array_values(array_intersect($sourceProgramIds, $adminProgramIds));

        if ($clipped === []) {
            return ['mode' => ProgramScopeMode::None, 'programIds' => []];
        }

        return ['mode' => ProgramScopeMode::Specific, 'programIds' => $clipped];
    }

    /**
     * @param  list<int>  $sourceProjectIds
     * @return list<int>
     */
    public static function clipProjectIdsForDuplicate(User $actor, array $sourceProjectIds): array
    {
        $access = UserAccess::for($actor);
        $sourceProjectIds = self::normalizeIds($sourceProjectIds);

        if ($access->isSystemAdmin()) {
            return $sourceProjectIds;
        }

        return array_values(array_intersect($sourceProjectIds, $access->adminProjectIds()));
    }

    // protect all behavior for sys admin
    public static function validateSubmittedMode(
        Validator $validator,
        User $actor,
        ?ProgramScopeMode $existingMode,
        ProgramScopeMode $submittedMode,
        string $modeKey = 'program_scope_mode',
    ): void {
        if (UserAccess::for($actor)->isSystemAdmin()) {
            return;
        }

        if ($submittedMode === ProgramScopeMode::All && $existingMode !== ProgramScopeMode::All) {
            $validator->errors()->add($modeKey, 'Only a system administrator can assign All programs.');
        }

        if ($existingMode === ProgramScopeMode::All && $submittedMode !== ProgramScopeMode::All) {
            $validator->errors()->add($modeKey, 'Only a system administrator can remove All programs.');
        }
    }

    /**
     * @param  list<int>  $submittedProgramIds
     */
    public static function validateSubmittedProgramsAreInAdminScope(
        Validator $validator,
        User $actor,
        array $submittedProgramIds,
        array $existingProgramIds = [],
        string $programKey = 'program_ids',
    ): void {
        $access = UserAccess::for($actor);
        if ($access->isSystemAdmin()) {
            return;
        }

        $submittedProgramIds = self::normalizeIds($submittedProgramIds);
        $existingProgramIds = self::normalizeIds($existingProgramIds);
        $adminProgramIds = $access->adminProgramIds();
        $allowed = array_values(array_unique(array_merge($adminProgramIds, $existingProgramIds)));
        $outside = array_values(array_diff($submittedProgramIds, $allowed));

        if ($outside !== []) {
            $validator->errors()->add($programKey, 'You can only assign programs you administer.');
        }
    }

    /**
     * @param  list<int>  $submittedProjectIds
     */
    public static function validateSubmittedProjectsAreInAdminScope(
        Validator $validator,
        User $actor,
        array $submittedProjectIds,
        array $existingProjectIds = [],
        string $projectKey = 'project_ids',
    ): void {
        $access = UserAccess::for($actor);
        if ($access->isSystemAdmin()) {
            return;
        }

        $submittedProjectIds = self::normalizeIds($submittedProjectIds);
        $existingProjectIds = self::normalizeIds($existingProjectIds);
        $adminProjectIds = $access->adminProjectIds();
        $allowed = array_values(array_unique(array_merge($adminProjectIds, $existingProjectIds)));
        $outside = array_values(array_diff($submittedProjectIds, $allowed));

        if ($outside !== []) {
            $validator->errors()->add($projectKey, 'You can only assign projects you administer.');
        }
    }

    public static function applyTo(
        User $actor,
        \Illuminate\Database\Eloquent\Model $entity,
        ProgramScopeMode $submittedMode,
        array $submittedProgramIds,
    ): void {
        $existingMode = $entity->exists ? ($entity->program_scope_mode ?? ProgramScopeMode::None) : ProgramScopeMode::None;
        $existingIds = $entity->exists
            ? $entity->programs()->pluck('programs.id')->all()
            : [];

        $merged = self::mergePrograms(
            $actor,
            $existingMode,
            $existingIds,
            $submittedMode,
            $submittedProgramIds,
        );

        $entity->program_scope_mode = $merged['mode'];
        $entity->save();
        $entity->programs()->sync($merged['programIds']);
    }

    /**
     * @param  iterable<mixed>  $ids
     * @return list<int>
     */
    private static function normalizeIds(iterable $ids): array
    {
        return collect($ids)
            ->filter(fn ($id) => $id !== null && $id !== '')
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();
    }
}
