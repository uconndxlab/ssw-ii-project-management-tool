<?php

use App\Enums\AccessProfile;
use App\Models\Certificate;
use App\Models\CertificateRequirement;
use App\Models\CertificationRole;
use App\Models\CertificationTool;
use App\Models\User;
use App\Support\ProjectProgramScope;

test('input user is forbidden from certification tool and certificate routes', function () {
    $user = User::factory()->create(['access_profile' => AccessProfile::Input]);
    [$project, $program] = createProjectWithProgram();
    $tool = CertificationTool::factory()->forProgram($program)->create();
    $certificate = Certificate::factory()->forProgram($program)->create();

    $this->actingAs($user)->get(route('certification-tools.index'))->assertForbidden();
    $this->actingAs($user)->get(route('certificates.index'))->assertForbidden();
    $this->actingAs($user)->get(route('certification-tools.edit', $tool))->assertForbidden();
    $this->actingAs($user)->get(route('certificates.edit', $certificate))->assertForbidden();
});

test('view-only admin cannot create tools or certificates', function () {
    [$project, $program] = createProjectWithProgram();
    $viewer = createProgramViewer($program);

    $this->actingAs($viewer)->get(route('certification-tools.create'))->assertForbidden();
    $this->actingAs($viewer)->post(route('certification-tools.store'), toolStorePayload())->assertForbidden();
    $this->actingAs($viewer)->get(route('certificates.create'))->assertForbidden();
    $this->actingAs($viewer)->post(route('certificates.store'), certificateStorePayload(withNestedFixture: true))->assertForbidden();
});

test('program-scoped admin can manage records in their program but not out of scope', function () {
    [$projectA, $programA] = createProjectWithProgram();
    [$projectB, $programB] = createProjectWithProgram();
    $adminA = createProgramAdmin($programA);
    $adminB = createProgramAdmin($programB);
    $systemAdmin = createSystemAdmin();

    $toolA = CertificationTool::factory()->forProgram($programA)->create();
    $toolB = CertificationTool::factory()->forProgram($programB)->create();
    $certA = Certificate::factory()->forProgram($programA)->create();
    $certB = Certificate::factory()->forProgram($programB)->create();

    $this->actingAs($adminA)->get(route('certification-tools.edit', $toolA))->assertOk();
    $this->actingAs($adminA)->get(route('certification-tools.edit', $toolB))->assertForbidden();
    $this->actingAs($adminA)->get(route('certificates.edit', $certA))->assertOk();
    $this->actingAs($adminA)->get(route('certificates.edit', $certB))->assertForbidden();

    $this->actingAs($systemAdmin)->get(route('certification-tools.edit', $toolB))->assertOk();
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
        ->get(route('certification-tools.create'))
        ->assertOk()
        ->assertSee($programA->name)
        ->assertDontSee($programB->name);
});

test('destroy retires tool referenced by a requirement instead of deleting', function () {
    $admin = createSystemAdmin();
    [$project, $program] = createProjectWithProgram();
    $tool = CertificationTool::factory()->forProgram($program)->create();
    $certificate = Certificate::factory()->forProgram($program)->create();
    CertificateRequirement::factory()->toolSubmission($tool)->for($certificate)->create();

    $this->actingAs($admin)
        ->delete(route('certification-tools.destroy', $tool))
        ->assertRedirect(route('certification-tools.index'));

    $tool->refresh();
    expect($tool->retired_at)->not->toBeNull();
    expect($tool->active)->toBeFalse();
    expect(CertificationTool::find($tool->id))->not->toBeNull();
});

test('destroy hard-deletes unreferenced tool', function () {
    $admin = createSystemAdmin();
    [$project, $program] = createProjectWithProgram();
    $tool = CertificationTool::factory()->forProgram($program)->create();

    $this->actingAs($admin)
        ->delete(route('certification-tools.destroy', $tool))
        ->assertRedirect(route('certification-tools.index'));

    expect(CertificationTool::find($tool->id))->toBeNull();
});

test('destroy retires certificate with requirements instead of deleting', function () {
    $admin = createSystemAdmin();
    [$project, $program] = createProjectWithProgram();
    $certificate = Certificate::factory()->forProgram($program)->create();
    CertificateRequirement::factory()->for($certificate)->create();

    $this->actingAs($admin)
        ->delete(route('certificates.destroy', $certificate))
        ->assertRedirect(route('certificates.index'));

    $certificate->refresh();
    expect($certificate->retired_at)->not->toBeNull();
    expect($certificate->active)->toBeFalse();
    expect(Certificate::find($certificate->id))->not->toBeNull();
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
        ->from(route('certification-tools.create'))
        ->post(route('certification-tools.store'), [
            'name' => 'Out of Scope Tool',
            'active' => '1',
            'program_scope_mode' => 'specific',
            'project_ids' => [$projectB->id],
            'program_ids' => [$programB->id],
        ])
        ->assertSessionHasErrors('program_ids')
        ->assertRedirect();

    expect(CertificationTool::where('name', 'Out of Scope Tool')->exists())->toBeFalse();
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
