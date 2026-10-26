<?php

use App\Enums\CertificationDuty;
use App\Enums\PrivilegeScopeType;
use App\Models\Agreement;
use App\Models\AgreementCertificationCandidate;
use App\Models\Certificate;
use App\Models\CertificateEnrollment;
use App\Models\CertificationCandidate;
use App\Models\User;
use App\Models\UserCertificationDuty;

test('a system admin can grant and revoke their own duties without editing their account privileges', function () {
    $admin = createSystemAdmin();
    $originalName = $admin->name;
    $this->actingAs($admin)->get(route('profile.edit'))->assertOk()->assertSee('Save certification duties');
    $this->actingAs($admin)->put(route('profile.certification-duties.update'), [
        'duty_coach_present' => '1', 'duty_coach_system' => '1', 'name' => 'Untrusted replacement', 'access_profile' => 'member',
    ])->assertSessionHasNoErrors()->assertRedirect(route('profile.edit'));
    $duty = UserCertificationDuty::where('user_id', $admin->id)->sole();
    expect($duty->duty)->toBe(CertificationDuty::Coach)
        ->and($duty->scope_type)->toBe(PrivilegeScopeType::System)
        ->and($duty->granted_by_user_id)->toBe($admin->id)
        ->and($admin->fresh()->name)->toBe($originalName)
        ->and($admin->fresh()->access()->isSystemAdmin())->toBeTrue();

    $this->actingAs($admin)->put(route('profile.certification-duties.update'), [
        'duty_coach_present' => '1',
    ])->assertSessionHasNoErrors();
    expect($duty->fresh()->revoked_at)->not->toBeNull()
        ->and($duty->fresh()->revoked_by_user_id)->toBe($admin->id);
    $this->actingAs($admin)->get(route('admin.users.edit', $admin))->assertForbidden();
});

test('a program admin can self-grant only within their own scope', function () {
    [, $program] = createProjectWithProgram();
    [, $otherProgram] = createProjectWithProgram();
    $admin = createProgramAdmin($program);
    $this->actingAs($admin)->put(route('profile.certification-duties.update'), [
        'duty_coach_program_ids' => [$program->id],
    ])->assertSessionHasNoErrors();
    expect($admin->fresh()->certification()->coachProgramIds())->toBe([$program->id]);

    $this->actingAs($admin)->put(route('profile.certification-duties.update'), [
        'duty_coach_program_ids' => [$otherProgram->id],
    ])->assertSessionHasErrors('duty_coach_program_ids.0');
    $this->actingAs($admin)->put(route('profile.certification-duties.update'), [
        'duty_manager_system' => '1',
    ])->assertSessionHasErrors('duty_manager_system');
    expect($admin->fresh()->certification()->coachProgramIds())->toBe([$program->id])
        ->and($admin->fresh()->certification()->isManager())->toBeFalse();
});

test('ordinary members cannot self-grant certification duties', function () {
    $member = User::factory()->create(['access_profile' => 'member']);
    $this->actingAs($member)->get(route('profile.edit'))->assertOk()->assertDontSee('Save certification duties');
    $this->actingAs($member)->put(route('profile.certification-duties.update'), ['duty_coach_system' => '1'])->assertForbidden();
    expect(UserCertificationDuty::where('user_id', $member->id)->exists())->toBeFalse();
});

test('self-grant forms preserve existing duties outside the current admin scope', function () {
    [, $program] = createProjectWithProgram();
    [, $otherProgram] = createProjectWithProgram();
    $admin = createProgramAdmin($program);
    $outsideDuty = UserCertificationDuty::factory()->coach()->atProgram($otherProgram)->create(['user_id' => $admin->id]);
    $this->actingAs($admin)->get(route('profile.edit'))->assertOk()->assertViewHas('existingCoachProgramIds', []);

    $this->actingAs($admin)->put(route('profile.certification-duties.update'), [
        'duty_coach_present' => '1', 'duty_coach_program_ids' => [$program->id],
    ])->assertSessionHasNoErrors()->assertRedirect(route('profile.edit'));

    expect($outsideDuty->fresh()->revoked_at)->toBeNull()
        ->and($admin->fresh()->certification()->coachProgramIds())->toContain($program->id, $otherProgram->id);
});

test('duty self-grants require authentication and a valid payload', function () {
    $this->put(route('profile.certification-duties.update'), ['duty_coach_system' => '1'])->assertRedirect(route('login'));
    $admin = createSystemAdmin();
    $this->actingAs($admin)->put(route('profile.certification-duties.update'), ['duty_coach_program_ids' => 'invalid'])
        ->assertSessionHasErrors('duty_coach_program_ids');
    expect(UserCertificationDuty::count())->toBe(0);
});

test('candidate search offers only reachable people and preserves shared-edit authority', function () {
    [, $program] = createProjectWithProgram();
    [, $otherProgram] = createProjectWithProgram();
    $admin = createProgramAdmin($program);
    $agreement = Agreement::factory()->create(['name' => 'Visible candidate context']);
    $agreement->programs()->attach($program);
    $otherAgreement = Agreement::factory()->create();
    $otherAgreement->programs()->attach($otherProgram);
    $person = CertificationCandidate::factory()->create(['name' => 'Searchable candidate', 'email' => 'known@example.test']);
    $legacy = CertificationCandidate::factory()->create(['name' => 'Legacy person', 'email' => null]);
    foreach ([$person, $legacy] as $candidate) {
        AgreementCertificationCandidate::factory()->create(['agreement_id' => $agreement->id, 'certification_candidate_id' => $candidate->id]);
    }
    AgreementCertificationCandidate::factory()->create(['agreement_id' => $otherAgreement->id, 'certification_candidate_id' => $person->id]);
    $hidden = AgreementCertificationCandidate::factory()->create(['agreement_id' => $otherAgreement->id]);

    $this->actingAs($admin)->get(route('agreements.edit', $agreement))->assertOk()
        ->assertSee('Search name or email...')->assertSee($person->email)->assertSee('Visible candidate context')
        ->assertDontSee($hidden->candidate->email)
        ->assertViewHas('certificationCandidateOptions', fn ($options) => $options->firstWhere('id', $person->id)?->getAttribute('can_edit_identity') === false);
});

test('malformed candidate fields recover to a working agreement editor', function () {
    $admin = createSystemAdmin();
    [$project, $program] = createProjectWithProgram();
    $agreement = Agreement::factory()->create();
    $agreement->programs()->attach($program);

    $this->actingAs($admin)->from(route('agreements.edit', $agreement))->followingRedirects()
        ->put(route('agreements.update', $agreement), agreementStorePayload($project, $program, [
            'certification_candidates' => [['name' => ['invalid'], 'email' => ['invalid']]],
        ]))->assertOk()->assertSee('cert-candidate-0-name', false);
    expect(CertificationCandidate::count())->toBe(0);
});

test('choosing a shared candidate exposes their existing visible assignments for immediate editing', function () {
    $admin = createSystemAdmin();
    [, $program] = createProjectWithProgram();
    $agreement = Agreement::factory()->create();
    $agreement->programs()->attach($program);
    $otherRoster = AgreementCertificationCandidate::factory()->create();
    $certificate = Certificate::factory()->forProgram($program)->create();
    $enrollment = CertificateEnrollment::factory()->create([
        'certification_candidate_id' => $otherRoster->certification_candidate_id, 'certificate_id' => $certificate->id,
    ]);

    $this->actingAs($admin)->get(route('agreements.edit', $agreement))->assertOk()
        ->assertViewHas('certificationCandidateOptions', fn ($options) => collect($options->firstWhere('id', $otherRoster->certification_candidate_id)->getAttribute('assignment_options'))
            ->contains(fn ($assignment) => $assignment['enrollment_id'] === $enrollment->id));
});

test('agreement assignments show actionable facts without presenting an unstarted status as calculated progress', function () {
    $admin = createSystemAdmin();
    [, $program] = createProjectWithProgram();
    $agreement = Agreement::factory()->create();
    $agreement->programs()->attach($program);
    $roster = AgreementCertificationCandidate::factory()->create(['agreement_id' => $agreement->id]);
    $certificate = Certificate::factory()->forProgram($program)->create(['name' => 'Shared coach certification']);
    $coach = User::factory()->create(['name' => 'Assigned supervisor']);
    CertificateEnrollment::factory()->create([
        'certification_candidate_id' => $roster->certification_candidate_id, 'certificate_id' => $certificate->id,
        'supervising_coach_user_id' => $coach->id, 'enrolled_at' => '2026-01-02',
    ]);

    $this->actingAs($admin)->get(route('agreements.show', $agreement))->assertOk()
        ->assertSee('Certificate assignments')->assertSee($certificate->name)->assertSee('Coach: Assigned supervisor')
        ->assertSee('Assigned Jan 2, 2026')->assertSee('#certification-candidate-'.$roster->certification_candidate_id, false)
        ->assertDontSee('Not Started');
});

test('validation recovery retains shared assignment editing when adding a candidate to another agreement', function () {
    $admin = createSystemAdmin();
    [$project, $program] = createProjectWithProgram();
    $agreement = Agreement::factory()->create();
    $agreement->programs()->attach($program);
    $otherRoster = AgreementCertificationCandidate::factory()->create();
    $certificate = Certificate::factory()->forProgram($program)->create();
    $enrollment = CertificateEnrollment::factory()->create([
        'certification_candidate_id' => $otherRoster->certification_candidate_id, 'certificate_id' => $certificate->id,
    ]);
    $coach = createProgramViewer($program);
    UserCertificationDuty::factory()->coach()->atProgram($program)->create(['user_id' => $coach->id]);

    $response = $this->actingAs($admin)->from(route('agreements.edit', $agreement))->followingRedirects()
        ->put(route('agreements.update', $agreement), agreementStorePayload($project, $program, [
            'name' => '',
            'certification_candidates' => [[
                'certification_candidate_id' => $otherRoster->certification_candidate_id,
                'name' => $otherRoster->candidate->name, 'email' => $otherRoster->candidate->email,
                'assignments' => [['enrollment_id' => $enrollment->id, 'certificate_id' => $certificate->id, 'coach_user_id' => $coach->id]],
            ]],
        ]));

    $response->assertOk()->assertSee('id="cert-assignment-0-0-coach"', false)->assertSee('data-original-coach=""', false);
    expect($enrollment->fresh()->supervising_coach_user_id)->toBeNull();
});
