<?php

use App\Enums\AccessProfile;
use App\Enums\PrivilegeCapability;
use App\Enums\PrivilegeScopeType;
use App\Enums\ProgramScopeMode;
use App\Models\ActivityType;
use App\Models\Agreement;
use App\Models\Certificate;
use App\Models\CertificationInstrument;
use App\Models\ContactFamily;
use App\Models\LoggingField;
use App\Models\Organization;
use App\Models\SubActivity;
use App\Models\Team;
use App\Models\User;
use App\Models\UserPrivilege;

require_once __DIR__.'/AccessFixtures.php';

test('All and No programs do not imply assigned program privilege overlap', function (string $class, ProgramScopeMode $mode) {
    [, $program] = createProjectWithProgram();
    $record = $class::factory()->create(['program_scope_mode' => $mode]);
    assertRecordAccess(createSystemAdmin(), $record, true);
    assertRecordAccess(createProgramAdmin($program), $record, false);
    assertRecordAccess(createProgramViewer($program), $record, false);
    // current behavior: list and single check disagree
    assertRecordAccess(accessActor(PrivilegeCapability::View, PrivilegeScopeType::System, null), $record,
        ! in_array($class, [ContactFamily::class, LoggingField::class, ActivityType::class, SubActivity::class], true), true);
})->with([Team::class, Agreement::class, Organization::class, ContactFamily::class, LoggingField::class, ActivityType::class, SubActivity::class, CertificationInstrument::class, Certificate::class])
    ->with([ProgramScopeMode::All, ProgramScopeMode::None, ProgramScopeMode::Specific]);

test('program and team membership grant no single-record access to setup catalogs', function (string $class, bool $viaTeam) {
    [, $program] = createProjectWithProgram();
    $actor = User::factory()->create();
    if ($viaTeam) {
        $team = Team::factory()->create();
        $team->programs()->attach($program);
        $team->users()->attach($actor);
    } else {
        $actor->programs()->attach($program);
    }
    $record = accessRecordInProgram($class, $program);
    // current behavior: list and single check disagree for certificates.
    assertRecordAccess($actor, $record, false, $class === Certificate::class);
    $actor->update(['access_profile' => AccessProfile::Input]);
    assertRecordAccess($actor, $record, false);
})->with([ContactFamily::class, LoggingField::class, ActivityType::class, SubActivity::class, CertificationInstrument::class, Certificate::class])->with([false, true]);

test('an admin privilege elsewhere is enough to open a setup record in a view-only program', function (string $class) {
    [, $program] = createProjectWithProgram();
    [, $otherProgram] = createProjectWithProgram();
    $record = accessRecordInProgram($class, $program);
    $actor = createProgramViewer($program);
    UserPrivilege::factory()->programAdmin($otherProgram)->create(['user_id' => $actor->id]);
    assertRecordAccess($actor, $record, true);
})->with([ContactFamily::class, LoggingField::class, ActivityType::class, SubActivity::class]);

test('assigned pivots still grant privilege overlap even when the record scope says All or None', function (string $class, ProgramScopeMode $mode) {
    [, $program] = createProjectWithProgram();
    $record = accessRecordInProgram($class, $program);
    $record->update(['program_scope_mode' => $mode]);
    assertRecordAccess(createProgramAdmin($program), $record, true);
})->with([Team::class, Agreement::class, Organization::class, ContactFamily::class, LoggingField::class, ActivityType::class, SubActivity::class, CertificationInstrument::class, Certificate::class])->with([ProgramScopeMode::All, ProgramScopeMode::None]);
