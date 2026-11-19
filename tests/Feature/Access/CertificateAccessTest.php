<?php

use App\Enums\AccessProfile;
use App\Enums\CertificationDuty;
use App\Enums\PrivilegeScopeType;
use App\Enums\ProgramScopeMode;
use App\Models\Certificate;
use App\Models\CertificationCandidate;
use App\Models\CertificationInstrument;
use App\Models\User;
use App\Models\UserCertificationDuty;

require_once __DIR__.'/AccessFixtures.php';

test('active coach and manager duties expose certificates only in their duty programs or All mode', function (CertificationDuty $duty, PrivilegeScopeType $scope, ProgramScopeMode $mode) {
    [$project, $program] = createProjectWithProgram();
    [, $otherProgram] = createProjectWithProgram();
    $actor = User::factory()->create();
    $row = UserCertificationDuty::factory()->create([
        'user_id' => $actor->id, 'duty' => $duty, 'scope_type' => $scope,
        'scope_id' => match ($scope) {
            PrivilegeScopeType::System => null,
            PrivilegeScopeType::Project => $project->id,
            PrivilegeScopeType::Program => $program->id,
        },
    ]);
    $certificate = Certificate::factory()->create(['program_scope_mode' => $mode]);
    if ($mode === ProgramScopeMode::Specific) {
        $certificate->programs()->attach($program);
    }
    // current behavior: list and single check disagree
    assertRecordAccess($actor, $certificate, $mode !== ProgramScopeMode::None, true);
    expect(User::findOrFail($actor->id)->certification()->applyCertificateVisibility(Certificate::query())->whereKey($certificate->id)->exists())->toBe($mode !== ProgramScopeMode::None);
    $other = Certificate::factory()->forProgram($otherProgram)->create();
    // current behavior: list and single check disagree
    assertRecordAccess($actor, $other, $scope === PrivilegeScopeType::System, true);
    assertRecordAccess($actor, CertificationCandidate::factory()->create(), false);
    assertRecordAccess($actor, CertificationInstrument::factory()->forProgram($program)->create(), false);
    $actor->update(['access_profile' => AccessProfile::Input]);
    assertRecordAccess($actor, $certificate, false);
    $actor->update(['access_profile' => AccessProfile::Member]);
    $row->update(['revoked_at' => now()]);
    // current behavior: list and single check disagree
    assertRecordAccess($actor, $certificate, false, true);
})->with(CertificationDuty::cases())->with(PrivilegeScopeType::cases())->with(ProgramScopeMode::cases());

test('supervisors do not inherit their reports certificate duties', function (CertificationDuty $duty) {
    [, $program] = createProjectWithProgram();
    $supervisor = User::factory()->create(['is_supervisor' => true]);
    $report = User::factory()->create(['supervisor_id' => $supervisor->id]);
    UserCertificationDuty::factory()->atProgram($program)->create(['user_id' => $report->id, 'duty' => $duty]);
    $certificate = Certificate::factory()->forProgram($program)->create();
    // current behavior: list and single check disagree
    assertRecordAccess($supervisor, $certificate, false, true);
    assertRecordAccess($report, $certificate, true, true);
})->with(CertificationDuty::cases());

test('certificate duties grant access outside an enhanced viewers privilege scope', function (CertificationDuty $duty, ProgramScopeMode $mode) {
    [, $program] = createProjectWithProgram();
    [, $otherProgram] = createProjectWithProgram();
    $actor = createProgramViewer($otherProgram);
    $row = UserCertificationDuty::factory()->atProgram($program)->create(['user_id' => $actor->id, 'duty' => $duty]);
    $certificate = Certificate::factory()->create(['program_scope_mode' => $mode]);
    if ($mode === ProgramScopeMode::Specific) {
        $certificate->programs()->attach($program);
    }
    // current behavior: list and single check disagree
    assertRecordAccess($actor, $certificate, true, false);
    $row->update(['revoked_at' => now()]);
    assertRecordAccess($actor, $certificate, false);
})->with(CertificationDuty::cases())->with([ProgramScopeMode::All, ProgramScopeMode::Specific]);
