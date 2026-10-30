<?php

namespace App\Support\Authorization;

use App\Enums\CertificationDuty;
use App\Enums\PrivilegeScopeType;
use App\Models\User;
use App\Models\UserCertificationDuty;

/**
 * Smallest grant/revoke control for coach/manager duties (spine decision 4: duties live outside
 * the privilege hierarchy). Mirrors {@see PrivilegeSync}'s preserve/grant shape: rows outside the
 * actor's own admin scope are left untouched rather than revoked, and revoked rows are retained
 * (never deleted) as the audit trail.
 */
class CertificationDutySync
{
    /**
     * @param  array{coach_system?: bool, coach_program_ids?: list<int>, manager_system?: bool, manager_program_ids?: list<int>}  $submitted
     */
    public static function apply(User $actor, User $target, array $submitted): void
    {
        if (array_key_exists('coach_system', $submitted) || array_key_exists('coach_program_ids', $submitted)) {
            self::applyForDuty($actor, $target, CertificationDuty::Coach, $submitted['coach_system'] ?? false, $submitted['coach_program_ids'] ?? []);
        }

        if (array_key_exists('manager_system', $submitted) || array_key_exists('manager_program_ids', $submitted)) {
            self::applyForDuty($actor, $target, CertificationDuty::Manager, $submitted['manager_system'] ?? false, $submitted['manager_program_ids'] ?? []);
        }
    }

    /**
     * @param  list<int>  $programIds
     */
    private static function applyForDuty(User $actor, User $target, CertificationDuty $duty, bool $system, array $programIds): void
    {
        $access = CertificationAccess::for($actor);

        $desiredKeys = [];
        if ($system && $access->canGrantDuty($target, $duty, PrivilegeScopeType::System, null)) {
            $desiredKeys['system'] = true;
        }
        foreach ($programIds as $programId) {
            if ($access->canGrantDuty($target, $duty, PrivilegeScopeType::Program, $programId)) {
                $desiredKeys['program:'.$programId] = true;
            }
        }

        $existing = $target->certificationDuties()
            ->where('duty', $duty)
            ->whereNull('revoked_at')
            ->get()
            ->filter(fn (UserCertificationDuty $row) => in_array($row->scope_type, [PrivilegeScopeType::System, PrivilegeScopeType::Program], true));

        foreach ($existing as $row) {
            $key = $row->scope_type === PrivilegeScopeType::System ? 'system' : 'program:'.$row->scope_id;

            if (isset($desiredKeys[$key])) {
                unset($desiredKeys[$key]);

                continue;
            }

            if ($access->canRevokeDuty($row)) {
                $row->update(['revoked_at' => now(), 'revoked_by_user_id' => $actor->id]);
            }
        }

        foreach (array_keys($desiredKeys) as $key) {
            [$scopeType, $scopeId] = $key === 'system'
                ? [PrivilegeScopeType::System, null]
                : [PrivilegeScopeType::Program, (int) str_replace('program:', '', $key)];

            $target->certificationDuties()->create([
                'duty' => $duty,
                'scope_type' => $scopeType,
                'scope_id' => $scopeId,
                'granted_by_user_id' => $actor->id,
            ]);
        }
    }
}
