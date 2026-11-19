<?php

use App\Enums\AccessProfile;
use App\Enums\PrivilegeCapability;
use App\Enums\PrivilegeScopeType;
use App\Models\Certificate;
use App\Models\CertificationCandidate;
use App\Models\User;
use Tests\TestCase;

require_once __DIR__.'/AccessFixtures.php';

test('view privileges expose only records in their scope and require the enhanced profile', function (string $class, string $route, bool $requiresAdmin, PrivilegeCapability $capability, PrivilegeScopeType $scope) {
    [$project, $program] = createProjectWithProgram();
    $record = accessRecordInProgram($class, $program);
    $scopeId = match ($scope) {
        PrivilegeScopeType::System => null,
        PrivilegeScopeType::Project => $project->id,
        PrivilegeScopeType::Program => $program->id,
    };
    $actor = accessActor($capability, $scope, $scopeId);
    // current behavior: list and single check disagree
    // Setup records require an admin privilege for single-record view, even for system viewers.
    assertRecordAccess($actor, $record, ! $requiresAdmin || $capability === PrivilegeCapability::Admin, true);

    foreach ([AccessProfile::Member, AccessProfile::Input] as $profile) {
        // current behavior: certificate lists retain their empty-group visibility for members.
        assertRecordAccess(accessActor($capability, $scope, $scopeId, $profile), $record, false, $class === Certificate::class && $profile === AccessProfile::Member);
    }
    // current behavior: list and single check disagree for certificates without privileges.
    assertRecordAccess(User::factory()->adminViewer()->create(), $record, false, $class === Certificate::class);

    if ($scope !== PrivilegeScopeType::System) {
        [$otherProject, $otherProgram] = createProjectWithProgram();
        $wrongId = $scope === PrivilegeScopeType::Project ? $otherProject->id : $otherProgram->id;
        assertRecordAccess(accessActor($capability, $scope, $wrongId), $record, false);
    }
})->with(accessRecordTypes())->with(PrivilegeCapability::cases())->with(PrivilegeScopeType::cases());

test('record endpoints allow a system admin and forbid an unrelated member', function (string $class, string $route) {
    [, $program] = createProjectWithProgram();
    $record = accessRecordInProgram($class, $program);
    /** @var TestCase $this */
    $this->actingAs(createSystemAdmin())->get(route($route, $record))->assertOk();
    $this->actingAs(User::factory()->create())->get(route($route, $record))->assertForbidden();
})->with(array_map(fn (array $type): array => [$type[0], $type[1]], accessRecordTypes()));

test('system visibility handles records with no program or membership links', function (string $class, string $route, bool $requiresAdmin) {
    $record = $class::factory()->create();
    assertRecordAccess(createSystemAdmin(), $record, true);
    $viewer = accessActor(PrivilegeCapability::View, PrivilegeScopeType::System, null);
    // current behavior: list and single check disagree
    assertRecordAccess($viewer, $record, ! $requiresAdmin && $class !== CertificationCandidate::class, $class !== CertificationCandidate::class);
})->with(accessRecordTypes());
