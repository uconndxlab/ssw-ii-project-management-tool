<?php

use App\Enums\AccessProfile;
use App\Enums\CertificationDuty;
use App\Enums\PrivilegeCapability;
use App\Enums\PrivilegeScopeType;
use App\Enums\ProgramScopeMode;
use App\Models\Activity;
use App\Models\ActivityReviewRequest;
use App\Models\ActivityType;
use App\Models\Agreement;
use App\Models\Certificate;
use App\Models\CertificateEnrollment;
use App\Models\CertificationInstrument;
use App\Models\ContactFamily;
use App\Models\LoggingField;
use App\Models\Program;
use App\Models\SubActivity;
use App\Models\Team;
use App\Models\User;
use App\Models\UserCertificationDuty;
use App\Notifications\ActivityReviewRequested;
use App\Services\CertificateLifecycleService;
use App\Support\Authorization\CertificationDutySync;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;

require_once __DIR__.'/AccessFixtures.php';

function inputProfilePayload(User $user, array $extra = []): array
{
    return array_merge([
        'name' => $user->name, 'email' => $user->email, 'active' => '1',
        'access_profile' => 'input', 'program_scope_mode' => 'all',
    ], $extra);
}

test('Input duty grants fail validation before changing the user', function (string $duty, bool $system) {
    $admin = createSystemAdmin();
    $user = User::factory()->create(['access_profile' => AccessProfile::Input]);
    $program = Program::factory()->create();
    $this->actingAs($admin)->put(route('admin.users.update', $user), inputProfilePayload($user, [
        'name' => 'Must not save',
        "duty_{$duty}_system" => $system ? '1' : '0',
        "duty_{$duty}_program_ids" => $system ? [] : [$program->id],
    ]))->assertSessionHasErrors(["duty_{$duty}_program_ids" => 'Input users cannot be certification coaches or managers.']);

    expect($user->fresh()->name)->toBe($user->name);
    $this->assertDatabaseMissing('user_certification_duties', ['user_id' => $user->id]);
    $this->get(route('admin.users.edit', $user))->assertOk()->assertViewHas('canGrantCertificationDuties', false)->assertDontSee('name="duty_coach_present"', false)->assertDontSee('name="duty_manager_present"', false);
    expect(fn () => CertificationDutySync::apply($admin, $user, ["{$duty}_system" => $system, "{$duty}_program_ids" => $system ? [] : [$program->id]]))
        ->toThrow(ValidationException::class, 'Input users cannot be certification coaches or managers.');
})->with(['coach', 'manager'])->with(['program' => false, 'system' => true]);

test('profile cannot become Input while any active coach or manager duty remains', function (CertificationDuty $duty, PrivilegeScopeType $scope) {
    [$project, $program] = createProjectWithProgram();
    $user = User::factory()->create();
    $row = UserCertificationDuty::factory()->create([
        'user_id' => $user->id, 'duty' => $duty, 'scope_type' => $scope,
        'scope_id' => match ($scope) {
            PrivilegeScopeType::System => null,
            PrivilegeScopeType::Project => $project->id,
            PrivilegeScopeType::Program => $program->id,
        },
    ]);

    $this->actingAs(createSystemAdmin())->put(route('admin.users.update', $user), inputProfilePayload($user, [
        'duty_coach_present' => '1', 'duty_manager_present' => '1',
    ]))->assertSessionHasErrors(['access_profile' => 'Revoke this user’s active coach and manager duties before changing their profile to Input.']);

    expect($user->fresh()->access_profile)->toBe(AccessProfile::Member);
    expect($row->fresh()->revoked_at)->toBeNull();
    $row->update(['revoked_at' => now()]);
    $this->put(route('admin.users.update', $user), inputProfilePayload($user))->assertSessionHasNoErrors();
    expect($user->fresh()->access_profile)->toBe(AccessProfile::Input);
})->with(CertificationDuty::cases())->with(PrivilegeScopeType::cases());

test('Input users with legacy duties cannot be selected as supervising coaches', function (bool $existing) {
    $f = certificationActivityFixture();
    $input = User::factory()->create(['access_profile' => AccessProfile::Input]);
    UserCertificationDuty::factory()->coach()->atProgram($f['program'])->create(['user_id' => $input->id]);
    $input->programs()->attach($f['program']);
    $f['agreement']->users()->attach($input);
    if ($existing) {
        $f['enrollment']->update(['supervising_coach_user_id' => $input->id]);
    }

    $this->actingAs($f['actor'])->get(route('agreements.edit', $f['agreement']))->assertOk()
        ->assertViewHas('eligibleCoaches', fn ($coaches) => ! $coaches->contains('id', $input->id));
    $this->put(route('agreements.update', $f['agreement']), agreementStorePayload($f['project'], $f['program'], [
        'user_ids' => [$input->id], 'principal_investigator_ids' => [$input->id],
        'certification_candidates' => [[
            'id' => $f['roster']->id, 'certification_candidate_id' => $f['candidate']->id,
            'name' => $f['candidate']->name, 'email' => $f['candidate']->email,
            'assignments' => [['certificate_id' => $f['certificate']->id, 'coach_user_id' => $input->id]],
        ]],
    ]))->assertSessionHasErrors(['certification_candidates.0.assignments.0.coach_user_id' => 'Input users cannot be supervising coaches.']);
    expect($f['enrollment']->fresh()->supervising_coach_user_id)->toBe($existing ? $input->id : null);
    expect(app(CertificateLifecycleService::class)->eligibleCoaches($f['actor'], $f['enrollment'])->modelKeys())->not->toContain($input->id);
    $this->put(route('certificate-enrollments.coach', $f['enrollment']), ['coach_id' => $input->id])
        ->assertSessionHasErrors(['coach_id' => 'Choose a coach with coach duty for this certificate’s programs.']);
    expect($input->fresh()->certification()->coachProgramIds())->toBe([]);
    UserCertificationDuty::factory()->manager()->atSystem()->create(['user_id' => $input->id]);
    expect($input->fresh()->certification()->managerProgramIds())->toBe([]);
})->with(['new selection' => false, 'existing selection' => true]);

test('Input reviewers are rejected even when legacy requests would otherwise retain eligibility', function (string $legacy) {
    Notification::fake([ActivityReviewRequested::class]);
    $f = certificationActivityFixture();
    $input = User::factory()->create(['access_profile' => AccessProfile::Input]);
    UserCertificationDuty::factory()->coach()->atProgram($f['program'])->create(['user_id' => $input->id]);
    $f['agreement']->users()->attach($input);
    $f['enrollment']->update(['supervising_coach_user_id' => $input->id]);
    $this->actingAs($f['actor'])->post(route('activities.store'), $f['payload'])->assertSessionHasNoErrors();
    $activity = Activity::query()->latest('id')->firstOrFail();
    $request = $legacy === 'none' ? null : ActivityReviewRequest::factory()->create([
        'activity_id' => $activity->id, 'coach_user_id' => $input->id, 'cleared_at' => $legacy === 'cleared' ? now() : null,
    ]);

    $this->get(route('activities.edit', $activity))->assertOk()->assertViewHas('certificationForm', function ($form) use ($input) {
        return ! in_array($input->id, array_column($form['agreement_coaches'], 'id'))
            && ! in_array($input->id, array_column($form['reviewers'], 'id'))
            && ! in_array($input->id, array_column($form['candidates'][0]['supervising_coaches'], 'id'));
    });
    $this->put(route('activities.certification.update', $activity), ['review_coach_user_ids' => [$input->id]])
        ->assertSessionHasErrors(['review_coach_user_ids' => 'Input users cannot be asked to review activities.']);
    expect($activity->reviewRequests()->count())->toBe($request ? 1 : 0);
    if ($request !== null) {
        expect($request->fresh()->cleared_at?->toDateTimeString())->toBe($request->cleared_at?->toDateTimeString());
    }
    Notification::assertNothingSent();
    $this->actingAs($input)->get(route('activities.certification.edit', $activity))->assertForbidden();
})->with(['none', 'open', 'cleared']);

test('members get empty setup lists while activity logging retains its pickers and saves', function () {
    $f = certificationActivityFixture();
    $member = User::factory()->create();
    $member->programs()->attach($f['program']);
    $f['agreement']->users()->attach($member);
    $sub = SubActivity::factory()->for($f['type'], 'activityType')->create();
    $field = LoggingField::factory()->forContactFamilies()->create();
    $f['family']->contactFamilyLoggingFields()->attach($field);
    foreach ([ContactFamily::class, ActivityType::class, SubActivity::class, LoggingField::class, CertificationInstrument::class] as $class) {
        expect($class::visibleTo($member)->count())->toBe(0);
    }

    $this->actingAs($member)->get(route('activities.create'))->assertOk()
        ->assertViewHas('contactFamilies', fn ($families) => $families->contains('id', $f['family']->id)
            && $families->find($f['family']->id)->activityTypes->find($f['type']->id)->subActivities->contains('id', $sub->id)
            && $families->find($f['family']->id)->contactFamilyLoggingFields->contains('id', $field->id))
        ->assertViewHas('certificationForm', fn ($form) => in_array($f['enrollment']->id, array_column($form['candidates'][0]['enrollments'], 'id'))
            && in_array($f['tool']->id, array_column($form['tools'], 'id')));
    $this->post(route('activities.store'), $f['payload'] + ['sub_activity_id' => $sub->id])->assertSessionHasNoErrors()->assertRedirect();
    $this->assertDatabaseHas('activities', ['user_id' => $member->id, 'activity_type_id' => $f['type']->id]);
});

test('inactive agreements require overlapping privileges despite staff or team membership', function (PrivilegeCapability $capability, PrivilegeScopeType $scope, string $membership, ProgramScopeMode $mode) {
    [$project, $program] = createProjectWithProgram();
    [, $otherProgram] = createProjectWithProgram();
    $actor = accessActor($capability, $scope, match ($scope) {
        PrivilegeScopeType::System => null,
        PrivilegeScopeType::Project => $project->id,
        PrivilegeScopeType::Program => $program->id,
    });
    $agreement = Agreement::factory()->inactive()->create(['program_scope_mode' => $mode]);
    if ($mode === ProgramScopeMode::Specific) {
        $agreement->programs()->attach($otherProgram);
    }
    if ($membership === 'team') {
        $team = Team::factory()->create();
        $team->users()->attach($actor);
        $agreement->teams()->attach($team);
    } else {
        $staff = $membership === 'report' ? User::factory()->create(['supervisor_id' => $actor->id]) : $actor;
        $actor->update(['is_supervisor' => $membership === 'report']);
        $agreement->users()->attach($staff);
    }
    $system = $scope === PrivilegeScopeType::System;
    assertRecordAccess($actor, $agreement, $system, true);
    $this->actingAs($actor->fresh())->get(route('agreements.show', $agreement))->assertStatus($system ? 200 : 403);
    $this->get(route('agreements.index'))->assertOk()
        ->assertViewHas('agreements', fn ($agreements) => $agreements->contains('id', $agreement->id) === $system);
    $this->get(route('agreements.index', ['active' => '0']))->assertOk()
        ->assertViewHas('agreements', fn ($agreements) => $agreements->contains('id', $agreement->id) === $system);
    // Assigned pivots define overlap even for All/None, as on agreement lists.
    $agreement->programs()->attach($program);
    assertRecordAccess($actor, $agreement, true);
    $this->actingAs($actor->fresh())->get(route('agreements.show', $agreement))->assertOk();
    $this->get(route('agreements.index'))->assertOk()->assertSee($agreement->name)
        ->assertSee('href="'.route('agreements.show', $agreement).'"', false);
    $this->get(route('agreements.index', ['active' => '0']))->assertOk()->assertSee($agreement->name);
    $this->get(route('agreements.index', ['active' => '1']))->assertOk()->assertDontSee($agreement->name);
})->with(PrivilegeCapability::cases())->with(PrivilegeScopeType::cases())->with(['staff', 'team', 'report'])->with(ProgramScopeMode::cases());

test('inactive agreement link checks batch privilege overlap even without eager loaded programs', function () {
    [, $program] = createProjectWithProgram();
    $actor = createProgramViewer($program);
    $counts = [];
    foreach ([2, 6] as $additional) {
        $rows = Agreement::factory()->inactive()->count($additional)->create();
        foreach ($rows as $row) {
            $row->programs()->attach($program);
        }
        $records = Agreement::query()->get();
        $freshActor = $actor->fresh();
        $freshActor->access()->viewProgramIds();
        DB::enableQueryLog();
        DB::flushQueryLog();
        foreach ($records as $record) {
            expect($record->isLinkable($freshActor))->toBeTrue();
        }
        $counts[] = count(DB::getQueryLog());
        DB::disableQueryLog();
    }
    expect($counts)->toBe([2, 2]);
});

test('certificate summaries filters and progress retain original member and duty visibility', function (?CertificationDuty $duty) {
    $f = certificationActivityFixture();
    $actor = User::factory()->create();
    $f['agreement']->users()->attach($actor);
    if ($duty !== null) {
        UserCertificationDuty::factory()->atProgram($f['program'])->create(['user_id' => $actor->id, 'duty' => $duty]);
    }
    $other = Certificate::factory()->forProgram(Program::factory()->create())->create();
    $otherEnrollment = CertificateEnrollment::factory()->create(['certificate_id' => $other->id, 'certification_candidate_id' => $f['candidate']->id]);
    $visibleIds = [$f['enrollment']->id, $otherEnrollment->id];

    $this->actingAs($actor)->get(route('agreements.show', $f['agreement']))->assertOk()
        ->assertViewHas('certificationRosterDisplay', fn ($rows) => $rows->sole()['enrollments']->pluck('id')->values()->all() === [$f['enrollment']->id]);
    $this->get(route('certification-candidates.index'))->assertOk()
        ->assertViewHas('filterCertificates', fn ($certificates) => $certificates->contains('id', $f['certificate']->id) && $certificates->contains('id', $other->id));
    $this->get(route('certification-candidates.show', $f['candidate']))->assertOk()
        ->assertViewHas('row', fn ($row) => $row['enrollments']->modelKeys() === $visibleIds && $row['hiddenCertificates'] === 0);
    $this->get(route('certificate-enrollments.show', $f['enrollment']))->assertOk();
    $this->get(route('certificate-enrollments.show', $otherEnrollment))->assertOk();
    if ($duty === null) {
        $this->get(route('certificates.index'))->assertForbidden();
    } else {
        $this->get(route('certificates.index'))->assertOk()->assertSee($f['certificate']->name)->assertDontSee($other->name);
    }
})->with(['member' => null, 'coach' => CertificationDuty::Coach, 'manager' => CertificationDuty::Manager]);

test('action log endpoint refuses program membership without activity view', function () {
    $program = Program::factory()->create();
    $actor = createProgramViewer(Program::factory()->create());
    $program->users()->attach($actor);
    $activity = Activity::factory()->create();
    $activity->programs()->attach($program);

    $this->actingAs($actor)->get(route('activities.action-logs', $activity))->assertForbidden();
    $this->actingAs($activity->user)->get(route('activities.action-logs', $activity))->assertOk();
});

test('members never see inactive agreements in the index even with an active filter or htmx', function (?string $active, bool $htmx) {
    $member = User::factory()->create(['name' => 'Agreement staff']);
    $agreement = Agreement::factory()->inactive()->create(['name' => 'Historical agreement']);
    $agreement->users()->attach($member);
    $activeAgreement = Agreement::factory()->create(['name' => 'Current agreement']);
    $activeAgreement->users()->attach($member);

    $this->actingAs($member)->get(route('agreements.index', ['active' => $active]), $htmx ? ['HX-Request' => 'true'] : [])->assertOk()
        ->assertViewHas('agreements', fn ($agreements) => $agreements->modelKeys() === ($active === '0' ? [] : [$activeAgreement->id]))
        ->assertDontSee('Historical agreement')->assertDontSee('href="'.route('agreements.show', $agreement).'"', false)
        ->assertDontSee('Agreement actions for Historical agreement');
    $this->get(route('agreements.show', $agreement))->assertForbidden();
})->with(['unfiltered' => null, 'inactive' => '0', 'active' => '1'])->with(['full page' => false, 'htmx' => true]);
