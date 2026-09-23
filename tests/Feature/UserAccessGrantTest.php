<?php

use App\Enums\PrivilegeCapability;
use App\Enums\PrivilegeScopeType;
use App\Enums\ProgramScopeMode;
use App\Models\Program;
use App\Models\Project;
use App\Models\User;
use App\Models\UserPrivilege;
use App\Support\Authorization\UserAccess;

test('system admin can grant system project and program privileges', function () {
    $access = UserAccess::for(createSystemAdmin());

    expect($access->canGrant(PrivilegeCapability::Admin, PrivilegeScopeType::System, null))->toBeTrue()
        ->and($access->canGrant(PrivilegeCapability::View, PrivilegeScopeType::Project, 1))->toBeTrue()
        ->and($access->canGrant(PrivilegeCapability::Admin, PrivilegeScopeType::Program, 1))->toBeTrue();
});

test('program admin can grant only programs they administer', function () {
    $program = Program::factory()->create();
    $otherProgram = Program::factory()->create();
    $access = UserAccess::for(createProgramAdmin($program));

    expect($access->canGrant(PrivilegeCapability::View, PrivilegeScopeType::Program, $program->id))->toBeTrue()
        ->and($access->canGrant(PrivilegeCapability::Admin, PrivilegeScopeType::Program, $otherProgram->id))->toBeFalse()
        ->and($access->canGrant(PrivilegeCapability::Admin, PrivilegeScopeType::System, null))->toBeFalse();
});

test('project admin can grant their project and implied programs', function () {
    [$project, $program] = createProjectWithProgram();
    $user = User::factory()->adminViewer()->create();
    UserPrivilege::factory()->projectAdmin($project)->create(['user_id' => $user->id]);
    $access = UserAccess::for($user);

    expect($access->canGrant(PrivilegeCapability::View, PrivilegeScopeType::Project, $project->id))->toBeTrue()
        ->and($access->canGrant(PrivilegeCapability::View, PrivilegeScopeType::Program, $program->id))->toBeTrue()
        ->and($access->canGrant(PrivilegeCapability::Admin, PrivilegeScopeType::System, null))->toBeFalse();
});

test('scoped record is fully within admin when every listed project is administered', function () {
    $project = Project::factory()->create();
    $user = User::factory()->adminViewer()->create();
    UserPrivilege::factory()->projectAdmin($project)->create(['user_id' => $user->id]);
    $access = UserAccess::for($user);

    expect($access->scopedRecordFullyWithinAdmin(ProgramScopeMode::Specific, [], [$project->id]))->toBeTrue()
        ->and($access->scopedRecordFullyWithinAdmin(ProgramScopeMode::Specific, [], []))->toBeFalse();
});
