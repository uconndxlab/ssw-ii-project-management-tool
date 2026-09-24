<?php

use App\Enums\AccessProfile;
use App\Enums\PrivilegeCapability;
use App\Enums\PrivilegeScopeType;
use App\Models\Program;
use App\Models\User;
use App\Support\Authorization\PrivilegeSync;

test('privilege sync stores integer and null scope ids from submitted rows', function () {
    $actor = createSystemAdmin();
    $target = User::factory()->adminViewer()->create();
    $program = Program::factory()->create();

    PrivilegeSync::apply($actor, $target, AccessProfile::AdminViewer, false, [
        [
            'capability' => PrivilegeCapability::View->value,
            'scope_type' => PrivilegeScopeType::Program->value,
            'scope_id' => $program->id,
        ],
        [
            'capability' => PrivilegeCapability::Admin->value,
            'scope_type' => PrivilegeScopeType::System->value,
            'scope_id' => null,
        ],
    ]);

    $privileges = $target->fresh()->privileges;

    expect($privileges)->toHaveCount(2)
        ->and($privileges->contains(
            fn ($privilege) => $privilege->scope_type === PrivilegeScopeType::Program
                && (int) $privilege->scope_id === $program->id
        ))->toBeTrue()
        ->and($privileges->contains(
            fn ($privilege) => $privilege->scope_type === PrivilegeScopeType::System
                && $privilege->scope_id === null
        ))->toBeTrue();
});
