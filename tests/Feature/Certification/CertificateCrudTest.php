<?php

use App\Enums\CertificateRequirementKind;
use App\Enums\CertificateRequirementPhase;
use App\Models\Certificate;
use App\Models\CertificateRequirement;
use App\Models\CertificateRequirementGroup;
use App\Models\CertificationRole;
use App\Models\User;

test('system admin can create a certificate with nested structure in one submission', function () {
    $admin = createSystemAdmin();
    $payload = certificateStorePayload(withNestedFixture: true);

    $response = $this->actingAs($admin)->post(route('certificates.store'), $payload);

    $certificate = Certificate::where('name', 'NWIC Local Coach')->first();
    expect($certificate)->not->toBeNull();
    expect($certificate->validity_months)->toBe(24);
    expect($certificate->default_window_months)->toBe(12);
    expect($certificate->prerequisites)->toHaveCount(1);
    expect($certificate->roles)->toHaveCount(1);
    expect($certificate->requirementGroups)->toHaveCount(1);
    expect($certificate->requirements)->toHaveCount(4);

    $groupedRequirement = $certificate->requirements->firstWhere('label', 'Registration');
    expect($groupedRequirement->certificate_requirement_group_id)->toBe($certificate->requirementGroups->first()->id);

    $cometRequirement = $certificate->requirements->firstWhere('label', 'COMET with rules');
    expect($cometRequirement->dimensionRules)->toHaveCount(1);

    $response->assertRedirect(route('certificates.edit', $certificate));
});

test('system admin can update certificate nested rows', function () {
    $admin = createSystemAdmin();
    [$project, $program] = createProjectWithProgram();
    $certificate = Certificate::factory()->forProgram($program)->create(['name' => 'Editable']);
    $role = CertificationRole::factory()->for($certificate)->create(['name' => 'Old Role']);
    $group = CertificateRequirementGroup::factory()->for($certificate)->create(['label' => 'Old Group']);
    $requirement = CertificateRequirement::factory()->for($certificate)->create([
        'certificate_requirement_group_id' => $group->id,
        'label' => 'Old Req',
    ]);

    $this->actingAs($admin)->put(route('certificates.update', $certificate), [
        'name' => 'Editable Updated',
        'active' => '1',
        'program_scope_mode' => 'specific',
        'project_ids' => [$project->id],
        'program_ids' => [$program->id],
        'prerequisite_mode' => 'all',
        'roles' => [
            0 => ['id' => $role->id, '_delete' => '1'],
            1 => ['name' => 'New Role', 'active' => '1'],
        ],
        'requirement_groups' => [
            0 => ['id' => $group->id, '_delete' => '1'],
            1 => [
                'phase' => CertificateRequirementPhase::Initial->value,
                'label' => 'New Group',
                'satisfy_mode' => 'all',
            ],
        ],
        'requirements' => [
            0 => ['id' => $requirement->id, '_delete' => '1'],
            1 => [
                'phase' => CertificateRequirementPhase::Initial->value,
                'kind' => CertificateRequirementKind::Attestation->value,
                'label' => 'New Req',
                'group_index' => 1,
                'target_count' => 1,
            ],
        ],
    ]);

    $certificate->refresh();
    expect($certificate->name)->toBe('Editable Updated');
    expect(CertificationRole::find($role->id))->toBeNull();
    expect($certificate->roles->first()->name)->toBe('New Role');
    expect(CertificateRequirementGroup::find($group->id))->toBeNull();
    expect($certificate->requirements->first()->label)->toBe('New Req');
    expect($certificate->requirements->first()->certificate_requirement_group_id)
        ->toBe($certificate->requirementGroups->first()->id);
});

test('system admin can view certificate index create and edit pages', function () {
    $admin = createSystemAdmin();
    [$project, $program] = createProjectWithProgram();
    $certificate = Certificate::factory()->forProgram($program)->create();

    $this->actingAs($admin)->get(route('certificates.index'))->assertOk();
    $this->actingAs($admin)->get(route('certificates.create'))->assertOk();
    $this->actingAs($admin)->get(route('certificates.edit', $certificate))->assertOk();
});

test('certificate index returns table partial for htmx requests', function () {
    $admin = createSystemAdmin();

    $response = $this->actingAs($admin)
        ->withHeader('HX-Request', 'true')
        ->get(route('certificates.index'));

    $response->assertOk();
    $response->assertViewIs('admin.certificates.partials.table');
});

test('certificate index supports search and filters', function () {
    $admin = createSystemAdmin();
    [$project, $program] = createProjectWithProgram();
    [$otherProject, $otherProgram] = createProjectWithProgram();

    Certificate::factory()->forProgram($program)->create(['name' => 'Visible Cert', 'active' => true]);
    Certificate::factory()->forProgram($otherProgram)->create(['name' => 'Hidden Cert', 'active' => false]);

    $this->actingAs($admin)
        ->get(route('certificates.index', ['search' => 'Visible', 'active' => 1, 'program_id' => $program->id]))
        ->assertOk()
        ->assertSee('Visible Cert')
        ->assertDontSee('Hidden Cert');
});

test('certificate index paginates at twenty per page', function () {
    $admin = createSystemAdmin();
    [$project, $program] = createProjectWithProgram();

    Certificate::factory()->count(21)->forProgram($program)->create();

    $response = $this->actingAs($admin)->get(route('certificates.index'));
    expect($response->viewData('certificates')->perPage())->toBe(20);
});

test('certificate store requires name and unique name', function () {
    $admin = createSystemAdmin();
    Certificate::factory()->create(['name' => 'Taken Cert']);

    $this->actingAs($admin)
        ->from(route('certificates.create'))
        ->post(route('certificates.store'), certificateStorePayload(['name' => ''], withNestedFixture: true))
        ->assertSessionHasErrors('name');

    $this->actingAs($admin)
        ->from(route('certificates.create'))
        ->post(route('certificates.store'), certificateStorePayload(['name' => 'Taken Cert'], withNestedFixture: true))
        ->assertSessionHasErrors('name');
});

test('certificate store requires program when scope mode is specific', function () {
    $admin = createSystemAdmin();
    [$project] = createProjectWithProgram();

    $this->actingAs($admin)
        ->from(route('certificates.create'))
        ->post(route('certificates.store'), [
            'name' => 'No Programs',
            'active' => '1',
            'program_scope_mode' => 'specific',
            'project_ids' => [$project->id],
            'program_ids' => [],
            'prerequisite_mode' => 'all',
        ])
        ->assertSessionHasErrors('program_ids');
});

test('certificate nested rows assign sort_order from submitted order', function () {
    $admin = createSystemAdmin();

    $this->actingAs($admin)->post(route('certificates.store'), certificateStorePayload([
        'name' => 'Sort Order Certificate',
        'roles' => [
            0 => ['name' => 'First Role', 'active' => '1'],
            1 => ['name' => 'Second Role', 'active' => '1'],
        ],
        'requirement_groups' => [
            0 => [
                'phase' => 'initial',
                'label' => 'First Group',
                'satisfy_mode' => 'all',
            ],
            1 => [
                'phase' => 'initial',
                'label' => 'Second Group',
                'satisfy_mode' => 'all',
            ],
        ],
        'requirements' => [
            0 => [
                'phase' => 'initial',
                'kind' => 'attestation',
                'label' => 'First Req',
                'target_count' => 1,
            ],
            1 => [
                'phase' => 'initial',
                'kind' => 'attestation',
                'label' => 'Second Req',
                'target_count' => 1,
            ],
        ],
    ]));

    $certificate = Certificate::where('name', 'Sort Order Certificate')->firstOrFail();

    expect($certificate->roles->pluck('sort_order')->all())->toBe([0, 1]);
    expect($certificate->requirementGroups->pluck('sort_order')->all())->toBe([0, 1]);
    expect($certificate->requirements->pluck('sort_order')->all())->toBe([0, 1]);
});

test('duplicate certificate role slugs return validation errors not a server error', function () {
    $admin = createSystemAdmin();

    $this->actingAs($admin)
        ->from(route('certificates.create'))
        ->post(route('certificates.store'), certificateStorePayload([
            'name' => 'Duplicate Roles Certificate',
            'roles' => [
                0 => ['name' => 'Observer', 'active' => '1'],
                1 => ['name' => 'Observer', 'active' => '1'],
            ],
            'requirements' => [],
            'requirement_groups' => [],
        ]))
        ->assertSessionHasErrors('roles.1.name')
        ->assertRedirect();

    expect(Certificate::where('name', 'Duplicate Roles Certificate')->exists())->toBeFalse();
});

test('member cannot access certificate routes', function () {
    $user = User::factory()->create();
    [$project, $program] = createProjectWithProgram();
    $certificate = Certificate::factory()->forProgram($program)->create();

    $this->actingAs($user)->get(route('certificates.index'))->assertForbidden();
    $this->actingAs($user)->get(route('certificates.create'))->assertForbidden();
    $this->actingAs($user)->post(route('certificates.store'), certificateStorePayload(withNestedFixture: true))->assertForbidden();
    $this->actingAs($user)->get(route('certificates.edit', $certificate))->assertForbidden();
    $this->actingAs($user)->put(route('certificates.update', $certificate), certificateStorePayload(withNestedFixture: true))->assertForbidden();
    $this->actingAs($user)->delete(route('certificates.destroy', $certificate))->assertForbidden();
});
