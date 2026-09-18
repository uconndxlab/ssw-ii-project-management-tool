<?php

use App\Enums\CertificationDuty;
use App\Enums\PrivilegeScopeType;
use App\Models\Certificate;
use App\Models\CertificationTool;
use App\Models\Program;
use App\Models\User;
use App\Models\UserCertificationDuty;

test('certification access reflects active coach and manager duties', function () {
    [$project, $program] = createProjectWithProgram();
    $user = User::factory()->create();

    UserCertificationDuty::factory()->coach()->atProgram($program)->create(['user_id' => $user->id]);
    UserCertificationDuty::factory()->manager()->atSystem()->create(['user_id' => $user->id]);

    $access = $user->certification();

    expect($access->isCoach())->toBeTrue();
    expect($access->isManager())->toBeTrue();
    expect($access->hasDuty(CertificationDuty::Coach))->toBeTrue();
    expect($access->coachesProgram($program->id))->toBeTrue();
    expect($access->managesProgram($program->id))->toBeTrue();
});

test('system-scope duty implies all programs', function () {
    $programA = Program::factory()->create();
    $programB = Program::factory()->create();
    $user = User::factory()->create();

    UserCertificationDuty::factory()->coach()->atSystem()->create(['user_id' => $user->id]);

    $ids = $user->certification()->coachProgramIds();

    expect($ids)->toContain($programA->id);
    expect($ids)->toContain($programB->id);
});

test('project-scope duty implies that projects programs', function () {
    [$project, $program] = createProjectWithProgram();
    $otherProgram = Program::factory()->create();
    $user = User::factory()->create();

    UserCertificationDuty::factory()->manager()->atProject($project)->create(['user_id' => $user->id]);

    $ids = $user->certification()->managerProgramIds();

    expect($ids)->toContain($program->id);
    expect($ids)->not->toContain($otherProgram->id);
});

test('revoked duty row is retained but excluded from access checks', function () {
    [$project, $program] = createProjectWithProgram();
    $user = User::factory()->create();
    $revoker = createSystemAdmin();

    $duty = UserCertificationDuty::factory()
        ->coach()
        ->atProgram($program)
        ->create(['user_id' => $user->id]);

    expect($user->certification()->isCoach())->toBeTrue();

    $duty->update([
        'revoked_at' => now(),
        'revoked_by_user_id' => $revoker->id,
    ]);

    expect(UserCertificationDuty::find($duty->id))->not->toBeNull();

    $reloaded = User::query()->findOrFail($user->id);
    expect($reloaded->certification()->isCoach())->toBeFalse();
    expect($reloaded->certification()->coachesProgram($program->id))->toBeFalse();
});

test('canGrantDuty delegates to admin scope and allows self-grant', function () {
    [$project, $program] = createProjectWithProgram();
    $systemAdmin = createSystemAdmin();
    $programAdmin = createProgramAdmin($program);
    $target = User::factory()->create();

    expect($systemAdmin->certification()->canGrantDuty(
        $target,
        CertificationDuty::Coach,
        PrivilegeScopeType::System,
        null,
    ))->toBeTrue();

    expect($programAdmin->certification()->canGrantDuty(
        $programAdmin,
        CertificationDuty::Manager,
        PrivilegeScopeType::Program,
        $program->id,
    ))->toBeTrue();

    expect($programAdmin->certification()->canGrantDuty(
        $target,
        CertificationDuty::Coach,
        PrivilegeScopeType::Program,
        Program::factory()->create()->id,
    ))->toBeFalse();
});

test('duty does not grant implicit catalog visibility', function () {
    [$project, $program] = createProjectWithProgram();
    $coach = User::factory()->create();
    $tool = CertificationTool::factory()->forProgram($program)->create();
    $certificate = Certificate::factory()->forProgram($program)->create();

    $visibleBefore = CertificationTool::query()->visibleTo($coach)->whereKey($tool)->exists();
    $canViewBefore = $coach->can('view', $certificate);

    UserCertificationDuty::factory()->coach()->atProgram($program)->create(['user_id' => $coach->id]);

    $reloaded = User::query()->findOrFail($coach->id);
    $visibleAfter = CertificationTool::query()->visibleTo($reloaded)->whereKey($tool)->exists();

    expect($visibleAfter)->toBe($visibleBefore);
    expect($reloaded->can('view', $certificate))->toBe($canViewBefore);
    expect($reloaded->can('view', $tool))->toBeFalse();
});
