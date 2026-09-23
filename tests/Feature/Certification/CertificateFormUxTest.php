<?php

use App\Enums\CertificateRequirementKind;
use App\Enums\CertificateRequirementPhase;
use App\Enums\ProgramScopeMode;
use App\Models\Certificate;
use App\Models\CertificateRequirement;
use App\Models\CertificationTool;

test('certificate edit form marks an in-scope tool selected', function () {
    $admin = createSystemAdmin();
    [, $program] = createProjectWithProgram();
    $tool = CertificationTool::factory()->forProgram($program)->create(['name' => 'Shared Checklist']);
    $certificate = Certificate::factory()->forProgram($program)->create();
    CertificateRequirement::factory()->for($certificate)->toolSubmission($tool)->create([
        'label' => 'Checklist reviews',
    ]);

    $html = $this->actingAs($admin)->get(route('certificates.edit', $certificate))->getContent();

    expect($html)->toContain('value="'.$tool->id.'"');
    expect($html)->toMatch('/value="'.$tool->id.'"[^>]*selected/s');
    expect($html)->toContain('data-program-ids="'.$program->id.'"');
    expect($html)->toContain('tokenPickerInitialized');
    expect($html)->toContain('data-selected=\'["'.$program->id.'"]\'');
});

test('certificate create form includes group select on requirement rows', function () {
    $admin = createSystemAdmin();
    $html = $this->actingAs($admin)->get(route('certificates.create'))->getContent();

    expect($html)->toContain('data-requirement-group-select');
});

test('certificate store rejects tool scoped only to a different program', function () {
    $admin = createSystemAdmin();
    [$projectA, $programA] = createProjectWithProgram();
    [$projectB, $programB] = createProjectWithProgram();
    $focusTool = CertificationTool::factory()->forProgram($programB)->create(['name' => 'FOCUS Tool']);

    $this->actingAs($admin)
        ->from(route('certificates.create'))
        ->post(route('certificates.store'), minimalCertificatePayload([
            'project_ids' => [$projectA->id],
            'program_ids' => [$programA->id],
            'requirements' => [
                0 => [
                    'phase' => CertificateRequirementPhase::Initial->value,
                    'kind' => CertificateRequirementKind::ToolSubmission->value,
                    'label' => 'FOCUS reviews',
                    'certification_tool_id' => $focusTool->id,
                    'target_count' => 2,
                    'requires_passing' => '1',
                ],
            ],
        ]))
        ->assertSessionHasErrors('requirements.0.certification_tool_id');
});

test('certificate store accepts tool that shares a program with the certificate', function () {
    $admin = createSystemAdmin();
    [$project, $program] = createProjectWithProgram();
    $tool = CertificationTool::factory()->forProgram($program)->create();

    $response = $this->actingAs($admin)->post(route('certificates.store'), minimalCertificatePayload([
        'project_ids' => [$project->id],
        'program_ids' => [$program->id],
        'requirements' => [
            0 => [
                'phase' => CertificateRequirementPhase::Initial->value,
                'kind' => CertificateRequirementKind::ToolSubmission->value,
                'label' => 'Shared tool',
                'certification_tool_id' => $tool->id,
                'target_count' => 1,
                'requires_passing' => '1',
            ],
        ],
    ]));
    $response->assertSessionHasNoErrors();
    $response->assertRedirect();

    $certificate = Certificate::where('name', 'Validation Cert')->first();
    expect($certificate)->not->toBeNull();
    expect($certificate->requirements)->toHaveCount(1);
});

test('certificate store accepts all-programs tool on specific certificate', function () {
    $admin = createSystemAdmin();
    [$project, $program] = createProjectWithProgram();
    $tool = CertificationTool::factory()->create([
        'program_scope_mode' => ProgramScopeMode::All,
    ]);

    $this->actingAs($admin)->post(route('certificates.store'), minimalCertificatePayload([
        'project_ids' => [$project->id],
        'program_ids' => [$program->id],
        'requirements' => [
            0 => [
                'phase' => CertificateRequirementPhase::Initial->value,
                'kind' => CertificateRequirementKind::ToolSubmission->value,
                'label' => 'Global tool',
                'certification_tool_id' => $tool->id,
                'target_count' => 1,
                'requires_passing' => '1',
            ],
        ],
    ]))->assertRedirect();
});

test('certificate store rejects tool with no program scope', function () {
    $admin = createSystemAdmin();
    [$project, $program] = createProjectWithProgram();
    $tool = CertificationTool::factory()->create([
        'program_scope_mode' => ProgramScopeMode::None,
    ]);

    $this->actingAs($admin)
        ->from(route('certificates.create'))
        ->post(route('certificates.store'), minimalCertificatePayload([
            'project_ids' => [$project->id],
            'program_ids' => [$program->id],
            'requirements' => [
                0 => [
                    'phase' => CertificateRequirementPhase::Initial->value,
                    'kind' => CertificateRequirementKind::ToolSubmission->value,
                    'label' => 'Unscoped tool',
                    'certification_tool_id' => $tool->id,
                    'target_count' => 1,
                    'requires_passing' => '1',
                ],
            ],
        ]))
        ->assertSessionHasErrors('requirements.0.certification_tool_id');
});

test('renewal matches initial flag drops stored renewal requirements', function () {
    $admin = createSystemAdmin();
    [$project, $program] = createProjectWithProgram();
    $tool = CertificationTool::factory()->forProgram($program)->create();

    $this->actingAs($admin)->post(route('certificates.store'), minimalCertificatePayload([
        'project_ids' => [$project->id],
        'program_ids' => [$program->id],
        'requirements' => [
            0 => [
                'phase' => CertificateRequirementPhase::Initial->value,
                'kind' => CertificateRequirementKind::Attestation->value,
                'label' => 'Initial only',
                'target_count' => 1,
            ],
            1 => [
                'phase' => CertificateRequirementPhase::Renewal->value,
                'kind' => CertificateRequirementKind::ToolSubmission->value,
                'label' => 'Renewal tool',
                'certification_tool_id' => $tool->id,
                'target_count' => 1,
                'requires_passing' => '1',
            ],
        ],
    ]));

    $certificate = Certificate::where('name', 'Validation Cert')->first();
    expect($certificate->requirements)->toHaveCount(2);

    $this->actingAs($admin)->put(route('certificates.update', $certificate), minimalCertificatePayload([
        'name' => $certificate->name,
        'project_ids' => [$project->id],
        'program_ids' => [$program->id],
        'renewal_matches_initial' => '1',
        'requirements' => [
            0 => [
                'id' => $certificate->requirements()->where('phase', CertificateRequirementPhase::Initial)->first()->id,
                'phase' => CertificateRequirementPhase::Initial->value,
                'kind' => CertificateRequirementKind::Attestation->value,
                'label' => 'Initial only',
                'target_count' => 1,
            ],
        ],
    ]))->assertRedirect();

    $certificate->refresh();
    expect($certificate->renewal_matches_initial)->toBeTrue();
    expect($certificate->requirements()->where('phase', CertificateRequirementPhase::Renewal)->count())->toBe(0);
});
