<?php

use App\Enums\AccessProfile;
use App\Enums\ProgramScopeMode;
use App\Models\Certificate;
use App\Models\CertificateEnrollment;
use App\Models\CertificateRequirement;
use App\Models\CertificateRequirementGroup;
use App\Models\CertificationCandidate;
use App\Models\CertificationRole;
use App\Models\CertificationTool;
use App\Models\User;
use App\Models\UserCertificationDuty;

test('scoped admins can open and edit a certificate', function () {
    [, $program] = createProjectWithProgram();
    $admin = createProgramAdmin($program);
    $certificate = Certificate::factory()->forProgram($program)->create();

    $this->actingAs($admin)->get(route('certificates.show', $certificate))
        ->assertOk()->assertSee(route('certificates.edit', $certificate), false);
    $this->get(route('certificates.edit', $certificate))->assertOk();
    $this->assertDatabaseHas('recent_records', ['user_id' => $admin->id, 'record_type' => 'certificate', 'record_id' => $certificate->id]);
});

test('enhanced viewers can read scoped certificates and tools but cannot edit or create', function () {
    [, $program] = createProjectWithProgram();
    [, $otherProgram] = createProjectWithProgram();
    $viewer = createProgramViewer($program);
    $certificate = Certificate::factory()->forProgram($program)->create();
    $otherCertificate = Certificate::factory()->forProgram($otherProgram)->create();
    $tool = CertificationTool::factory()->forProgram($program)->create();
    $otherTool = CertificationTool::factory()->forProgram($otherProgram)->create();

    $this->actingAs($viewer)->get(route('certificates.show', $certificate))
        ->assertOk()->assertDontSee(route('certificates.edit', $certificate), false);
    $this->get(route('certificates.index'))->assertOk()->assertSee($certificate->name)->assertDontSee($otherCertificate->name);
    $this->get(route('certificates.edit', $certificate))->assertForbidden();
    $this->get(route('certificates.show', $otherCertificate))->assertForbidden();
    $this->get(route('certification-tools.index'))->assertOk()->assertSee($tool->name)->assertDontSee($otherTool->name)
        ->assertDontSee(route('certification-tools.edit', $tool), false);
    $this->get(route('certification-tools.create'))->assertForbidden();
    $this->get(route('certification-tools.edit', $tool))->assertForbidden();
    expect($viewer->can('view', $tool))->toBeTrue();
    expect($viewer->can('view', $otherTool))->toBeFalse();
});

test('duty holders see overlapping and all-program certificates read only', function (string $duty) {
    [, $program] = createProjectWithProgram();
    [, $otherProgram] = createProjectWithProgram();
    $actor = User::factory()->create();
    UserCertificationDuty::factory()->atProgram($program)->create(['user_id' => $actor->id, 'duty' => $duty]);
    $certificate = Certificate::factory()->forProgram($program)->create();
    $other = Certificate::factory()->forProgram($otherProgram)->create();
    $all = Certificate::factory()->create(['program_scope_mode' => ProgramScopeMode::All]);
    $none = Certificate::factory()->create(['program_scope_mode' => ProgramScopeMode::None]);

    $this->actingAs($actor)->get(route('certificates.index'))->assertOk()
        ->assertSee($certificate->name)->assertSee($all->name)->assertDontSee($other->name)->assertDontSee($none->name)
        ->assertDontSee(route('certificates.edit', $certificate), false);
    $this->get(route('certificates.show', $certificate))->assertOk();
    $this->get(route('certificates.show', $all))->assertOk();
    $this->get(route('certificates.show', $other))->assertForbidden();
    $this->get(route('certificates.show', $none))->assertForbidden();
    $this->get(route('certificates.edit', $certificate))->assertForbidden();
    $this->get(route('certification-tools.index'))->assertForbidden();
})->with(['coach', 'manager']);

test('members without duties and input users cannot open the certificate catalog', function (AccessProfile $profile) {
    [, $program] = createProjectWithProgram();
    $actor = User::factory()->create(['access_profile' => $profile]);
    if ($profile === AccessProfile::Input) {
        UserCertificationDuty::factory()->coach()->atProgram($program)->create(['user_id' => $actor->id]);
    }
    $certificate = Certificate::factory()->forProgram($program)->create();

    $this->actingAs($actor)->get(route('certificates.index'))->assertForbidden();
    $this->get(route('certificates.show', $certificate))->assertForbidden();
})->with([AccessProfile::Member, AccessProfile::Input]);

test('certificate candidates include only visible people and a count of hidden enrollments', function () {
    [, $program] = createProjectWithProgram();
    $coach = User::factory()->create();
    UserCertificationDuty::factory()->coach()->atProgram($program)->create(['user_id' => $coach->id]);
    $certificate = Certificate::factory()->forProgram($program)->create();
    $visible = CertificationCandidate::factory()->create(['name' => 'Visible Candidate']);
    $hidden = CertificationCandidate::factory()->create(['name' => 'Hidden Candidate']);
    CertificateEnrollment::factory()->for($certificate)->create(['certification_candidate_id' => $visible->id, 'supervising_coach_user_id' => $coach->id]);
    CertificateEnrollment::factory()->for($certificate)->create(['certification_candidate_id' => $hidden->id]);

    $this->actingAs($coach)->get(route('certificates.show', $certificate))->assertOk()
        ->assertSee('Visible Candidate')->assertSee(route('certification-candidates.show', $visible), false)
        ->assertDontSee('Hidden Candidate')->assertSee('<span class="app-count">2</span>', false)->assertSee('1 restricted')
        ->assertSee('Enrollment totals')->assertSee('Assigned');
});

test('a duty holder with no visible candidates gets an empty state and hidden count', function () {
    [, $program] = createProjectWithProgram();
    $coach = User::factory()->create();
    UserCertificationDuty::factory()->coach()->atProgram($program)->create(['user_id' => $coach->id]);
    $certificate = Certificate::factory()->forProgram($program)->create();
    CertificateEnrollment::factory()->for($certificate)->create();

    $this->actingAs($coach)->get(route('certificates.show', $certificate))->assertOk()
        ->assertSee('No candidates you can see are enrolled.')->assertSee('1 restricted');
});

test('certificate details render grouped requirements roles and safe prerequisite links', function () {
    [, $program] = createProjectWithProgram();
    [, $otherProgram] = createProjectWithProgram();
    $viewer = createProgramViewer($program);
    $certificate = Certificate::factory()->forProgram($program)->create(['renewal_matches_initial' => true]);
    $visiblePrerequisite = Certificate::factory()->forProgram($program)->create();
    $hiddenPrerequisite = Certificate::factory()->forProgram($otherProgram)->create();
    $certificate->prerequisites()->attach([$visiblePrerequisite->id, $hiddenPrerequisite->id]);
    $globalRole = CertificationRole::factory()->global()->create();
    $role = CertificationRole::factory()->for($certificate)->create();
    $group = CertificateRequirementGroup::factory()->for($certificate)->nOf(2)->create(['label' => 'Choose two']);
    $tool = CertificationTool::factory()->forProgram($program)->create();
    CertificateRequirement::factory()->for($certificate)->toolSubmission($tool)->create([
        'certificate_requirement_group_id' => $group->id,
        'certification_role_id' => $role->id,
        'label' => 'Observed sessions', 'target_count' => 6, 'window_months' => 12,
        'threshold_note' => 'Coach decides the outcome', 'notes' => 'Keep the observation record',
    ]);

    $this->actingAs($viewer)->get(route('certificates.show', $certificate))->assertOk()
        ->assertSee('Choose two')->assertSee('2 required')->assertSee('Observed sessions')
        ->assertSee('Target: 6')->assertSee('12 month window')->assertSee($tool->name)
        ->assertSee($globalRole->name)->assertSee($role->name)
        ->assertSee('Coach decides the outcome')->assertSee('Keep the observation record')
        ->assertSee('Renewal matches initial requirements.')
        ->assertSee(route('certificates.show', $visiblePrerequisite), false)
        ->assertDontSee(route('certificates.show', $hiddenPrerequisite).'"', false);
});
