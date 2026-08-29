<?php

namespace App\Support\Authorization;

use App\Enums\AccessProfile;
use App\Enums\PrivilegeCapability;
use App\Enums\PrivilegeScopeType;
use App\Models\User;
use App\Models\UserPrivilege;

class PrivilegeSync
{
    /**
     * @param  list<array{capability: string, scope_type: string, scope_id: ?int}>  $submitted
     */
    public static function apply(User $actor, User $target, AccessProfile $profile, bool $isSupervisor, array $submitted): void
    {
        $access = UserAccess::for($actor);

        if ($profile === AccessProfile::Input) {
            $target->privileges()->delete();
            $target->forceFill([
                'access_profile' => AccessProfile::Input,
                'is_supervisor' => false,
            ])->save();

            return;
        }

        if ($profile === AccessProfile::Member) {
            $target->privileges()->delete();
            $target->forceFill([
                'access_profile' => AccessProfile::Member,
                'is_supervisor' => $isSupervisor,
            ])->save();

            return;
        }

        $preserved = $target->privileges
            ->filter(fn (UserPrivilege $privilege) => ! $access->canGrant(
                $privilege->capability,
                $privilege->scope_type,
                $privilege->scope_id !== null ? (int) $privilege->scope_id : null,
            ));

        $grantable = collect($submitted)
            ->map(function (array $row) {
                return [
                    'capability' => PrivilegeCapability::from($row['capability']),
                    'scope_type' => PrivilegeScopeType::from($row['scope_type']),
                    'scope_id' => isset($row['scope_id']) && $row['scope_id'] !== '' && $row['scope_id'] !== null
                        ? (int) $row['scope_id']
                        : null,
                ];
            })
            ->filter(fn (array $row) => $access->canGrant($row['capability'], $row['scope_type'], $row['scope_id']))
            ->unique(fn (array $row) => $row['capability']->value.'|'.$row['scope_type']->value.'|'.($row['scope_id'] ?? 'null'))
            ->values();

        $preservedKeys = $preserved->map(
            fn (UserPrivilege $privilege) => $privilege->capability->value.'|'.$privilege->scope_type->value.'|'.($privilege->scope_id ?? 'null')
        );

        $grantable = $grantable->reject(
            fn (array $row) => $preservedKeys->contains($row['capability']->value.'|'.$row['scope_type']->value.'|'.($row['scope_id'] ?? 'null'))
        );

        $target->privileges()->delete();

        foreach ($preserved as $privilege) {
            $target->privileges()->create([
                'capability' => $privilege->capability,
                'scope_type' => $privilege->scope_type,
                'scope_id' => $privilege->scope_id,
            ]);
        }

        foreach ($grantable as $row) {
            $target->privileges()->create([
                'capability' => $row['capability'],
                'scope_type' => $row['scope_type'],
                'scope_id' => $row['scope_id'],
            ]);
        }

        $target->forceFill([
            'access_profile' => AccessProfile::AdminViewer,
            'is_supervisor' => $isSupervisor,
        ])->save();
    }

    /**
     * @return list<array{capability: string, scope_type: string, scope_id: ?int}>
     */
    public static function rowsFromRequest(array $input, User $actor): array
    {
        $coverage = $input['privilege_coverage'] ?? 'specific';

        if ($coverage === 'system') {
            $capability = ! empty($input['privilege_system_admin'])
                ? PrivilegeCapability::Admin->value
                : PrivilegeCapability::View->value;

            return [[
                'capability' => $capability,
                'scope_type' => PrivilegeScopeType::System->value,
                'scope_id' => null,
            ]];
        }

        $rows = [];
        foreach ($input['privilege_entries'] ?? [] as $entry) {
            $scopeType = $entry['scope_type'] ?? null;
            $scopeId = isset($entry['scope_id']) ? (int) $entry['scope_id'] : 0;
            if (! in_array($scopeType, [PrivilegeScopeType::Project->value, PrivilegeScopeType::Program->value], true) || $scopeId < 1) {
                continue;
            }

            $rows[] = [
                'capability' => ! empty($entry['admin']) ? PrivilegeCapability::Admin->value : PrivilegeCapability::View->value,
                'scope_type' => $scopeType,
                'scope_id' => $scopeId,
            ];
        }

        return $rows;
    }
}
