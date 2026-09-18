<?php

use App\Enums\CertificateDimensionRuleMode;
use App\Enums\CertificateRequirementKind;
use App\Enums\CertificateRequirementPhase;
use App\Models\Certificate;
use App\Models\CertificationTool;
use App\Models\CertificationToolDimension;
use App\Models\CertificationToolDimensionOption;

function minimalCertificatePayload(array $overrides = []): array
{
    [$project, $program] = createProjectWithProgram();

    return array_merge([
        'name' => 'Validation Cert',
        'active' => '1',
        'program_scope_mode' => 'specific',
        'project_ids' => [$project->id],
        'program_ids' => [$program->id],
        'prerequisite_mode' => 'all',
    ], $overrides);
}

test('tool submission requirement requires certification_tool_id', function () {
    $admin = createSystemAdmin();

    $this->actingAs($admin)
        ->from(route('certificates.create'))
        ->post(route('certificates.store'), minimalCertificatePayload([
            'requirements' => [
                0 => [
                    'phase' => CertificateRequirementPhase::Initial->value,
                    'kind' => CertificateRequirementKind::ToolSubmission->value,
                    'label' => 'Missing tool',
                    'target_count' => 1,
                ],
            ],
        ]))
        ->assertSessionHasErrors('requirements.0.certification_tool_id');

    expect(Certificate::where('name', 'Validation Cert')->exists())->toBeFalse();
});

test('dimension rule must belong to the selected tool', function () {
    $admin = createSystemAdmin();
    [$project, $program] = createProjectWithProgram();
    $toolA = CertificationTool::factory()->forProgram($program)->create();
    $toolB = CertificationTool::factory()->forProgram($program)->create();
    $foreignDimension = CertificationToolDimension::factory()->for($toolB, 'tool')->create();

    $this->actingAs($admin)
        ->from(route('certificates.create'))
        ->post(route('certificates.store'), minimalCertificatePayload([
            'requirements' => [
                0 => [
                    'phase' => CertificateRequirementPhase::Initial->value,
                    'kind' => CertificateRequirementKind::ToolSubmission->value,
                    'certification_tool_id' => $toolA->id,
                    'target_count' => 1,
                    'dimension_rules' => [
                        0 => [
                            'certification_tool_dimension_id' => $foreignDimension->id,
                            'mode' => CertificateDimensionRuleMode::Coverage->value,
                            'option_ids' => [],
                        ],
                    ],
                ],
            ],
        ]))
        ->assertSessionHasErrors('requirements.0.dimension_rules.0.certification_tool_dimension_id');
});

test('quota dimension rule requires min_count', function () {
    $admin = createSystemAdmin();
    [$project, $program] = createProjectWithProgram();
    $tool = CertificationTool::factory()->forProgram($program)->create();
    $dimension = CertificationToolDimension::factory()->for($tool, 'tool')->create();
    $option = CertificationToolDimensionOption::factory()->for($dimension, 'dimension')->create();

    $this->actingAs($admin)
        ->from(route('certificates.create'))
        ->post(route('certificates.store'), minimalCertificatePayload([
            'requirements' => [
                0 => [
                    'phase' => CertificateRequirementPhase::Initial->value,
                    'kind' => CertificateRequirementKind::ToolSubmission->value,
                    'certification_tool_id' => $tool->id,
                    'target_count' => 1,
                    'dimension_rules' => [
                        0 => [
                            'certification_tool_dimension_id' => $dimension->id,
                            'mode' => CertificateDimensionRuleMode::Quota->value,
                            'option_ids' => [$option->id],
                        ],
                    ],
                ],
            ],
        ]))
        ->assertSessionHasErrors('requirements.0.dimension_rules.0.min_count');
});

test('coverage dimension rule requires at least one option', function () {
    $admin = createSystemAdmin();
    [$project, $program] = createProjectWithProgram();
    $tool = CertificationTool::factory()->forProgram($program)->create();
    $dimension = CertificationToolDimension::factory()->for($tool, 'tool')->create();

    $this->actingAs($admin)
        ->from(route('certificates.create'))
        ->post(route('certificates.store'), minimalCertificatePayload([
            'requirements' => [
                0 => [
                    'phase' => CertificateRequirementPhase::Initial->value,
                    'kind' => CertificateRequirementKind::ToolSubmission->value,
                    'certification_tool_id' => $tool->id,
                    'target_count' => 1,
                    'dimension_rules' => [
                        0 => [
                            'certification_tool_dimension_id' => $dimension->id,
                            'mode' => CertificateDimensionRuleMode::Coverage->value,
                            'option_ids' => [],
                        ],
                    ],
                ],
            ],
        ]))
        ->assertSessionHasErrors('requirements.0.dimension_rules.0.option_ids');
});

test('n_of group requires required_count within requirement count', function () {
    $admin = createSystemAdmin();

    $this->actingAs($admin)
        ->from(route('certificates.create'))
        ->post(route('certificates.store'), minimalCertificatePayload([
            'requirement_groups' => [
                0 => [
                    'phase' => CertificateRequirementPhase::Initial->value,
                    'label' => 'Pick 3 of 5',
                    'satisfy_mode' => 'n_of',
                    'required_count' => 4,
                ],
            ],
            'requirements' => [
                0 => [
                    'phase' => CertificateRequirementPhase::Initial->value,
                    'kind' => CertificateRequirementKind::Attestation->value,
                    'label' => 'Only one',
                    'group_index' => 0,
                    'target_count' => 1,
                ],
            ],
        ]))
        ->assertSessionHasErrors('requirement_groups.0.required_count');
});

test('certificate cannot be its own prerequisite on update', function () {
    $admin = createSystemAdmin();
    [$project, $program] = createProjectWithProgram();
    $certificate = Certificate::factory()->forProgram($program)->create();

    $this->actingAs($admin)
        ->from(route('certificates.edit', $certificate))
        ->put(route('certificates.update', $certificate), minimalCertificatePayload([
            'name' => $certificate->name,
            'prerequisite_certificate_ids' => [$certificate->id],
        ]))
        ->assertSessionHasErrors('prerequisite_certificate_ids');
});

test('prerequisite cycle is rejected on update', function () {
    $admin = createSystemAdmin();
    [$project, $program] = createProjectWithProgram();
    $certA = Certificate::factory()->forProgram($program)->create(['name' => 'Cert A']);
    $certB = Certificate::factory()->forProgram($program)->create(['name' => 'Cert B']);
    $certB->prerequisites()->attach($certA);

    $this->actingAs($admin)
        ->from(route('certificates.edit', $certA))
        ->put(route('certificates.update', $certA), minimalCertificatePayload([
            'name' => $certA->name,
            'prerequisite_certificate_ids' => [$certB->id],
        ]))
        ->assertSessionHasErrors('prerequisite_certificate_ids');
});
