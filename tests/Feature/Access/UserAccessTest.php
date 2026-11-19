<?php

use App\Enums\AccessProfile;
use App\Enums\PrivilegeCapability;
use App\Enums\PrivilegeScopeType;
use App\Enums\ProgramScopeMode;
use App\Models\Program;
use App\Models\Team;
use App\Models\User;
use Tests\TestCase;

require_once __DIR__.'/AccessFixtures.php';

test('viewers see users through their program or team membership including All scope', function (PrivilegeCapability $capability, PrivilegeScopeType $scope, bool $viaTeam, ProgramScopeMode $mode) {
    [$project, $program] = createProjectWithProgram();
    [, $otherProgram] = createProjectWithProgram();
    $actor = accessActor($capability, $scope, $scope === PrivilegeScopeType::Project ? $project->id : $program->id);
    $target = User::factory()->create();
    $membership = $target;
    if ($viaTeam) {
        $membership = Team::factory()->create();
        $membership->users()->attach($target);
    }
    $membership->update(['program_scope_mode' => $mode]);
    if ($mode === ProgramScopeMode::Specific) {
        $membership->programs()->attach($program);
    }
    assertRecordAccess($actor, $target, $mode !== ProgramScopeMode::None);
    assertRecordAccess(User::factory()->create(), $target, false);
    assertRecordAccess(User::factory()->create(['access_profile' => AccessProfile::Input]), $target, false);
    assertRecordAccess(createProgramViewer($otherProgram), $target, $mode === ProgramScopeMode::All);
})->with(PrivilegeCapability::cases())->with([PrivilegeScopeType::Project, PrivilegeScopeType::Program])->with([false, true])->with(ProgramScopeMode::cases());

test('direct supervisors can open reports even though the user index hides them without view privileges', function (AccessProfile $profile, bool $supervisorFlag) {
    $actor = User::factory()->create(['access_profile' => $profile, 'is_supervisor' => $supervisorFlag]);
    $report = User::factory()->create(['supervisor_id' => $actor->id]);
    // current behavior: list and single check disagree
    assertRecordAccess($actor, $report, $profile !== AccessProfile::Input && $supervisorFlag, false);
    assertRecordAccess($actor, User::factory()->create(), false);
    $grandchild = User::factory()->create(['supervisor_id' => $report->id]);
    assertRecordAccess($actor, $grandchild, false);
    // Self access is not a special case for the users.show policy (the separate profile route handles it).
    assertRecordAccess($actor, $actor, false);
})->with(AccessProfile::cases())->with([false, true]);

test('a viewer supervisor can open an out-of-scope report hidden from the index', function () {
    $actor = createProgramViewer(Program::factory()->create());
    $actor->update(['is_supervisor' => true]);
    $report = User::factory()->create(['supervisor_id' => $actor->id]);
    // current behavior: list and single check disagree
    assertRecordAccess($actor, $report, true, false);
    /** @var TestCase $this */
    $this->actingAs(User::findOrFail($actor->id))->get(route('users.show', $report))->assertOk();
});
