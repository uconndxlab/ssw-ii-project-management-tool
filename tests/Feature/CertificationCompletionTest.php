<?php

use App\Models\Agreement;
use App\Models\AgreementCertificationCandidate;
use App\Models\Certificate;
use App\Models\CertificateEnrollment;
use App\Models\CertificationCandidate;
use App\Models\User;
use App\Models\UserCertificationDuty;
use Illuminate\Support\Facades\Hash;

test('profile editing exposes only password fields even for a system admin', function () {
    $admin = createSystemAdmin();

    $this->actingAs($admin)->get(route('profile.edit'))
        ->assertOk()
        ->assertSee('Save password')
        ->assertSee('name="current_password"', false)
        ->assertSee('name="password"', false)
        ->assertSee('name="password_confirmation"', false)
        ->assertDontSee('Certification duties')
        ->assertDontSee('duty_coach_')
        ->assertDontSee('duty_manager_')
        ->assertDontSee('id="name"', false)
        ->assertDontSee('id="email"', false);
});

test('the removed profile duty endpoint cannot grant duties to a program admin', function () {
    [, $program] = createProjectWithProgram();
    $admin = createProgramAdmin($program);

    $this->actingAs($admin)->put('/profile/certification-duties', [
        'duty_coach_program_ids' => [$program->id],
    ])->assertNotFound();

    expect($admin->fresh()->certification()->isCoach())->toBeFalse();
});

test('ordinary member profiles have no duty editor or duty update endpoint', function () {
    $member = User::factory()->create(['access_profile' => 'member']);

    $this->actingAs($member)->get(route('profile.edit'))->assertOk()->assertDontSee('Save certification duties');
    $this->actingAs($member)->put('/profile/certification-duties', ['duty_coach_system' => '1'])->assertNotFound();

    expect(UserCertificationDuty::where('user_id', $member->id)->exists())->toBeFalse();
});

test('the removed profile duty endpoint cannot revoke existing admin duties', function () {
    $admin = createSystemAdmin();
    $duty = UserCertificationDuty::factory()->coach()->atSystem()->create(['user_id' => $admin->id]);

    $this->actingAs($admin)->put('/profile/certification-duties', [
        'duty_coach_present' => '1',
    ])->assertNotFound();

    expect($duty->fresh()->revoked_at)->toBeNull();
});

test('password updates ignore certification duties and account privilege changes', function () {
    $admin = createSystemAdmin();
    $originalName = $admin->name;
    $duty = UserCertificationDuty::factory()->coach()->atSystem()->create(['user_id' => $admin->id]);

    $this->actingAs($admin)->put(route('profile.password.update'), [
        'current_password' => 'password',
        'password' => 'new-password-123',
        'password_confirmation' => 'new-password-123',
        'duty_coach_present' => '1',
        'duty_manager_system' => '1',
        'name' => 'Untrusted replacement',
        'access_profile' => 'member',
    ])->assertSessionHasNoErrors()->assertRedirect(route('profile.edit'));

    expect(Hash::check('new-password-123', $admin->fresh()->password))->toBeTrue();
    expect($admin->fresh()->name)->toBe($originalName);
    expect($admin->fresh()->access()->isSystemAdmin())->toBeTrue();
    expect($duty->fresh()->revoked_at)->toBeNull();
    expect($admin->fresh()->certification()->isManager())->toBeFalse();
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
