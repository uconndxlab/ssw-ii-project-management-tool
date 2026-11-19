<?php

use App\Enums\AccessProfile;
use App\Models\Certificate;
use App\Models\CertificateRequirement;
use App\Models\CertificationInstrument;
use App\Models\CertificationRole;
use App\Models\User;
use App\Support\ProjectProgramScope;

test('input user is forbidden from certification tool and certificate routes', function () {
    $user = User::factory()->create(['access_profile' => AccessProfile::Input]);
    [$project, $program] = createProjectWithProgram();
    $tool = CertificationInstrument::factory()->forProgram($program)->create();
    $certificate = Certificate::factory()->forProgram($program)->create();

    $this->actingAs($user)->get(route('certification-instruments.index'))->assertForbidden();
    $this->actingAs($user)->get(route('certificates.index'))->assertForbidden();
    $this->actingAs($user)->get(route('certification-instruments.edit', $tool))->assertForbidden();
    $this->actingAs($user)->get(route('certificates.edit', $certificate))->assertForbidden();
});

test('view-only admin cannot create tools or certificates', function () {
    [$project, $program] = createProjectWithProgram();
    $viewer = createProgramViewer($program);

    $this->actingAs($viewer)->get(route('certification-instruments.create'))->assertForbidden();
    $this->actingAs($viewer)->post(route('certification-instruments.store'), toolStorePayload())->assertForbidden();
    $this->actingAs($viewer)->get(route('certificates.create'))->assertForbidden();
    $this->actingAs($viewer)->post(route('certificates.store'), certificateStorePayload(withNestedFixture: true))->assertForbidden();
});

test('program-scoped admin can manage records in their program but not out of scope', function () {
    [$projectA, $programA] = createProjectWithProgram();
    [$projectB, $programB] = createProjectWithProgram();
    $adminA = createProgramAdmin($programA);
    $adminB = createProgramAdmin($programB);
    $systemAdmin = createSystemAdmin();

    $toolA = CertificationInstrument::factory()->forProgram($programA)->create();
    $toolB = CertificationInstrument::factory()->forProgram($programB)->create();
    $certA = Certificate::factory()->forProgram($programA)->create();
    $certB = Certificate::factory()->forProgram($programB)->create();

    $this->actingAs($adminA)->get(route('certification-instruments.edit', $toolA))->assertOk();
    $this->actingAs($adminA)->get(route('certification-instruments.edit', $toolB))->assertForbidden();
    $this->actingAs($adminA)->get(route('certificates.edit', $certA))->assertOk();
    $this->actingAs($adminA)->get(route('certificates.edit', $certB))->assertForbidden();

    $this->actingAs($systemAdmin)->get(route('certification-instruments.edit', $toolB))->assertOk();
    $this->actingAs($systemAdmin)->get(route('certificates.edit', $certB))->assertOk();
});

test('program-scoped admin create form only lists assignable programs in their scope', function () {
    [$projectA, $programA] = createProjectWithProgram();
    [$projectB, $programB] = createProjectWithProgram();
    $adminA = createProgramAdmin($programA);

    $assignable = ProjectProgramScope::assignableProjectsWithProgramsFor($adminA);
    $programIds = $assignable->flatMap(fn ($project) => $project->programs)->pluck('id')->all();

    expect($programIds)->toContain($programA->id);
    expect($programIds)->not->toContain($programB->id);

    $this->actingAs($adminA)
        ->get(route('certification-instruments.create'))
        ->assertOk()
        ->assertSee($programA->name)
        ->assertDontSee($programB->name);
});

test('destroy blocks deleting a tool referenced by a requirement', function () {
    $admin = createSystemAdmin();
    [$project, $program] = createProjectWithProgram();
    $tool = CertificationInstrument::factory()->forProgram($program)->create();
    $certificate = Certificate::factory()->forProgram($program)->create();
    CertificateRequirement::factory()->toolSubmission($tool)->for($certificate)->create();

    $this->actingAs($admin)
        ->delete(route('certification-instruments.destroy', $tool))
        ->assertRedirect(route('certification-instruments.index'))
        ->assertSessionHas('error');

    $tool->refresh();
    expect($tool->retired_at)->toBeNull();
    expect($tool->active)->toBeTrue();
});

test('destroy hard-deletes unreferenced tool', function () {
    $admin = createSystemAdmin();
    [$project, $program] = createProjectWithProgram();
    $tool = CertificationInstrument::factory()->forProgram($program)->create();

    $this->actingAs($admin)
        ->delete(route('certification-instruments.destroy', $tool))
        ->assertRedirect(route('certification-instruments.index'));

    expect(CertificationInstrument::find($tool->id))->toBeNull();
});

test('destroy blocks deleting a certificate with requirements', function () {
    $admin = createSystemAdmin();
    [$project, $program] = createProjectWithProgram();
    $certificate = Certificate::factory()->forProgram($program)->create();
    CertificateRequirement::factory()->for($certificate)->create();

    $this->actingAs($admin)
        ->delete(route('certificates.destroy', $certificate))
        ->assertRedirect(route('certificates.index'))
        ->assertSessionHas('error');

    $certificate->refresh();
    expect($certificate->retired_at)->toBeNull();
    expect($certificate->active)->toBeTrue();
});

test('destroy hard-deletes certificate without requirements', function () {
    $admin = createSystemAdmin();
    [$project, $program] = createProjectWithProgram();
    $certificate = Certificate::factory()->forProgram($program)->create();

    $this->actingAs($admin)
        ->delete(route('certificates.destroy', $certificate))
        ->assertRedirect(route('certificates.index'));

    expect(Certificate::find($certificate->id))->toBeNull();
});

test('program-scoped admin cannot create certificate scoped to programs they do not administer', function () {
    [$projectA, $programA] = createProjectWithProgram();
    [$projectB, $programB] = createProjectWithProgram();
    $adminA = createProgramAdmin($programA);

    $this->actingAs($adminA)
        ->from(route('certificates.create'))
        ->post(route('certificates.store'), [
            'name' => 'Out of Scope Cert',
            'active' => '1',
            'program_scope_mode' => 'specific',
            'project_ids' => [$projectB->id],
            'program_ids' => [$programB->id],
            'prerequisite_mode' => 'all',
        ])
        ->assertSessionHasErrors('program_ids')
        ->assertRedirect();

    expect(Certificate::where('name', 'Out of Scope Cert')->exists())->toBeFalse();
});

test('program-scoped admin cannot create tool scoped to programs they do not administer', function () {
    [$projectA, $programA] = createProjectWithProgram();
    [$projectB, $programB] = createProjectWithProgram();
    $adminA = createProgramAdmin($programA);

    $this->actingAs($adminA)
        ->from(route('certification-instruments.create'))
        ->post(route('certification-instruments.store'), [
            'name' => 'Out of Scope Tool',
            'active' => '1',
            'program_scope_mode' => 'specific',
            'project_ids' => [$projectB->id],
            'program_ids' => [$programB->id],
        ])
        ->assertSessionHasErrors('program_ids')
        ->assertRedirect();

    expect(CertificationInstrument::where('name', 'Out of Scope Tool')->exists())->toBeFalse();
});

test('certification role policy restricts global roles to system admins', function () {
    [$project, $program] = createProjectWithProgram();
    $programAdmin = createProgramAdmin($program);
    $systemAdmin = createSystemAdmin();
    $globalRole = CertificationRole::factory()->global()->create();
    $scopedRole = CertificationRole::factory()->for(
        Certificate::factory()->forProgram($program)
    )->create();

    expect($programAdmin->can('update', $globalRole))->toBeFalse();
    expect($programAdmin->can('delete', $globalRole))->toBeFalse();
    expect($systemAdmin->can('update', $globalRole))->toBeTrue();

    expect($programAdmin->can('update', $scopedRole))->toBeTrue();
});
