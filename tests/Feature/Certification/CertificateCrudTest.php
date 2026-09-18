<?php

use App\Enums\CertificateDimensionRuleMode;
use App\Enums\CertificateRequirementKind;
use App\Enums\CertificateRequirementPhase;
use App\Models\Certificate;
use App\Models\CertificateRequirement;
use App\Models\CertificateRequirementGroup;
use App\Models\CertificationRole;
use App\Models\CertificationTool;
use App\Models\CertificationToolDimension;
use App\Models\CertificationToolDimensionOption;
use App\Models\User;

function certificateStorePayload(array $overrides = [], bool $withNestedFixture = false): array
{
    [$project, $program] = createProjectWithProgram();

    $payload = [
        'name' => 'NWIC Local Coach',
        'description' => 'Full coach certification',
        'active' => '1',
        'program_scope_mode' => 'specific',
        'project_ids' => [$project->id],
        'program_ids' => [$program->id],
        'validity_months' => 24,
        'default_window_months' => 12,
        'prerequisite_mode' => 'all',
        'prerequisite_certificate_ids' => [],
        'roles' => [],
        'requirement_groups' => [],
        'requirements' => [],
    ];

    if ($withNestedFixture) {
        $prerequisite = Certificate::factory()->forProgram($program)->create(['name' => 'Coach Prereq '.uniqid()]);
        $crestTool = CertificationTool::factory()->forProgram($program)->create(['name' => 'CREST '.uniqid()]);
        $cometTool = CertificationTool::factory()->forProgram($program)->create(['name' => 'COMET '.uniqid()]);
        $dimension = CertificationToolDimension::factory()->for($cometTool, 'tool')->create(['name' => 'Phase']);
        $option = CertificationToolDimensionOption::factory()->for($dimension, 'dimension')->create(['label' => 'Phase 1']);

        $payload['prerequisite_certificate_ids'] = [$prerequisite->id];
        $payload['roles'] = [
            0 => ['name' => 'Observer', 'active' => '1'],
        ];
        $payload['requirement_groups'] = [
            0 => [
                'phase' => CertificateRequirementPhase::Initial->value,
                'label' => 'Core',
                'satisfy_mode' => 'all',
            ],
        ];
        $payload['requirements'] = [
            0 => [
                'phase' => CertificateRequirementPhase::Initial->value,
                'kind' => CertificateRequirementKind::Attestation->value,
                'label' => 'Registration',
                'group_index' => 0,
                'target_count' => 1,
            ],
            1 => [
                'phase' => CertificateRequirementPhase::Initial->value,
                'kind' => CertificateRequirementKind::ToolSubmission->value,
                'label' => 'CREST submissions',
                'certification_tool_id' => $crestTool->id,
                'target_count' => 12,
                'requires_passing' => '1',
            ],
            2 => [
                'phase' => CertificateRequirementPhase::Initial->value,
                'kind' => CertificateRequirementKind::ToolSubmission->value,
                'label' => 'COMET with rules',
                'certification_tool_id' => $cometTool->id,
                'target_count' => 6,
                'requires_passing' => '1',
                'dimension_rules' => [
                    0 => [
                        'certification_tool_dimension_id' => $dimension->id,
                        'mode' => CertificateDimensionRuleMode::Coverage->value,
                        'option_ids' => [$option->id],
                    ],
                ],
            ],
            3 => [
                'phase' => CertificateRequirementPhase::Renewal->value,
                'kind' => CertificateRequirementKind::Attestation->value,
                'label' => 'Renewal attestation',
                'target_count' => 1,
            ],
        ];
    }

    return array_merge($payload, $overrides);
}

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
