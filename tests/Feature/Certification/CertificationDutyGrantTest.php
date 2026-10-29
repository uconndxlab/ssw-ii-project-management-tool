<?php

use App\Enums\CertificationDuty;
use App\Enums\PrivilegeScopeType;
use App\Models\Program;
use App\Models\User;
use App\Models\UserCertificationDuty;
use App\Support\Authorization\CertificationAccess;
use App\Support\Authorization\CertificationDutySync;

function adminUserUpdatePayload(User $target, array $overrides = []): array
{
    return array_merge([
        'name' => $target->name,
        'email' => $target->email,
        'access_profile' => 'member',
        'active' => '1',
        'program_scope_mode' => 'all',
    ], $overrides);
}

test('system admin can grant a system-wide coach duty', function () {
    $admin = createSystemAdmin();
    $target = User::factory()->create();

    $this->actingAs($admin)
        ->put(route('admin.users.update', $target), adminUserUpdatePayload($target, [
            'duty_coach_system' => '1',
        ]))
        ->assertSessionHasNoErrors();

    expect($target->fresh()->certification()->isCoach())->toBeTrue();
    expect(UserCertificationDuty::where('user_id', $target->id)->where('scope_type', PrivilegeScopeType::System)->exists())->toBeTrue();
});

test('program admin can grant a coach duty scoped to their own program but not system-wide', function () {
    [$project, $program] = createProjectWithProgram();
    $programAdmin = createProgramAdmin($program);
    $target = User::factory()->create();
    $target->programs()->attach($program->id);

    $this->actingAs($programAdmin)
        ->put(route('admin.users.update', $target), adminUserUpdatePayload($target, [
            'program_scope_mode' => 'specific',
            'project_ids' => [$project->id],
            'program_ids' => [$program->id],
            'duty_coach_system' => '1',
            'duty_coach_program_ids' => [$program->id],
        ]))
        ->assertSessionHasNoErrors();

    expect(UserCertificationDuty::where('user_id', $target->id)->where('scope_type', PrivilegeScopeType::Program)->where('scope_id', $program->id)->exists())->toBeTrue();
    expect(UserCertificationDuty::where('user_id', $target->id)->where('scope_type', PrivilegeScopeType::System)->exists())->toBeFalse();
});

test('CertificationDutySync allows a system admin to grant themselves a duty', function () {
    $admin = createSystemAdmin();

    CertificationDutySync::apply($admin, $admin, ['manager_system' => true]);

    expect($admin->fresh()->certification()->isManager())->toBeTrue();
    expect(UserCertificationDuty::where('user_id', $admin->id)->where('granted_by_user_id', $admin->id)->exists())->toBeTrue();
});

test('revoking a duty retains the row with revoked_at set', function () {
    $admin = createSystemAdmin();
    $target = User::factory()->create();
    UserCertificationDuty::factory()->coach()->atSystem()->create([
        'user_id' => $target->id,
        'granted_by_user_id' => $admin->id,
    ]);

    $this->actingAs($admin)
        ->put(route('admin.users.update', $target), adminUserUpdatePayload($target, [
            'duty_coach_system' => '0',
        ]))
        ->assertSessionHasNoErrors();

    $duty = UserCertificationDuty::where('user_id', $target->id)->where('duty', CertificationDuty::Coach)->first();
    expect($duty)->not->toBeNull();
    expect($duty->revoked_at)->not->toBeNull();
    expect($duty->revoked_by_user_id)->toBe($admin->id);
    expect($target->fresh()->certification()->isCoach())->toBeFalse();
});

test('a program admin cannot revoke a system-scope duty they did not grant', function () {
    $systemAdmin = createSystemAdmin();
    [$project, $program] = createProjectWithProgram();
    $programAdmin = createProgramAdmin($program);
    $target = User::factory()->create();
    $target->programs()->attach($program->id);
    UserCertificationDuty::factory()->coach()->atSystem()->create([
        'user_id' => $target->id,
        'granted_by_user_id' => $systemAdmin->id,
    ]);

    $this->actingAs($programAdmin)
        ->put(route('admin.users.update', $target), adminUserUpdatePayload($target, [
            'program_scope_mode' => 'specific',
            'project_ids' => [$project->id],
            'program_ids' => [$program->id],
        ]))
        ->assertSessionHasNoErrors();

    expect($target->fresh()->certification()->isCoach())->toBeTrue();
});

test('eligible coach user ids include system-scope and matching program-scope duties only', function () {
    [$project, $program] = createProjectWithProgram();
    $otherProgram = Program::factory()->create();
    $systemCoach = User::factory()->create();
    $programCoach = User::factory()->create();
    $otherProgramCoach = User::factory()->create();

    UserCertificationDuty::factory()->coach()->atSystem()->create(['user_id' => $systemCoach->id]);
    UserCertificationDuty::factory()->coach()->atProgram($program)->create(['user_id' => $programCoach->id]);
    UserCertificationDuty::factory()->coach()->atProgram($otherProgram)->create(['user_id' => $otherProgramCoach->id]);

    $ids = CertificationAccess::eligibleCoachUserIds([$program->id]);

    expect($ids)->toContain($systemCoach->id);
    expect($ids)->toContain($programCoach->id);
    expect($ids)->not->toContain($otherProgramCoach->id);
});

test('a system admin can grant themselves a duty from their own user form without changing permissions', function () {
    $admin = createSystemAdmin();

    $this->actingAs($admin)->get(route('admin.users.edit', $admin))
        ->assertOk()
        ->assertSee('You can’t change your own permissions')
        ->assertDontSee('data-access-profile-cards', false);

    $this->actingAs($admin)
        ->put(route('admin.users.update', $admin), adminUserUpdatePayload($admin, [
            'access_profile' => 'input',
            'duty_manager_system' => '1',
        ]))
        ->assertSessionHasNoErrors();

    $admin = $admin->fresh();
    expect($admin->certification()->isManager())->toBeTrue()
        ->and($admin->access()->isSystemAdmin())->toBeTrue()
        ->and($admin->active)->toBeTrue();
});

test('a system admin cannot deactivate their own account', function () {
    $admin = createSystemAdmin();

    $this->actingAs($admin)
        ->put(route('admin.users.update', $admin), adminUserUpdatePayload($admin, ['active' => '0']))
        ->assertSessionHasErrors(['active' => 'You cannot deactivate your own user account.']);

    expect($admin->fresh()->active)->toBeTrue();
});

test('only a system admin may edit their own account and nobody may delete themselves', function () {
    [, $program] = createProjectWithProgram();
    $systemAdmin = createSystemAdmin();
    $programAdmin = createProgramAdmin($program);

    expect($systemAdmin->can('update', $systemAdmin))->toBeTrue()
        ->and($systemAdmin->can('delete', $systemAdmin))->toBeFalse()
        ->and($programAdmin->can('update', $programAdmin))->toBeFalse()
        ->and($programAdmin->can('delete', $programAdmin))->toBeFalse();

    $this->actingAs($programAdmin)->get(route('admin.users.edit', $programAdmin))->assertForbidden();
});
