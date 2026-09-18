<?php

use App\Enums\CertificateDimensionRuleMode;
use App\Enums\CertificateRequirementKind;
use App\Enums\CertificateRequirementPhase;
use App\Models\Certificate;
use App\Models\CertificationTool;
use App\Models\CertificationToolDimension;
use App\Models\CertificationToolDimensionOption;

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

function toolStorePayload(array $overrides = []): array
{
    [$project, $program] = createProjectWithProgram();

    return array_merge([
        'name' => 'COMET',
        'description' => 'Coaching observation tool',
        'active' => '1',
        'program_scope_mode' => 'specific',
        'project_ids' => [$project->id],
        'program_ids' => [$program->id],
        'dimensions' => [
            0 => [
                'name' => 'Phase',
                'sort_order' => 0,
                'options' => [
                    0 => ['label' => 'Phase 1', 'sort_order' => 0],
                    1 => ['label' => 'Phase 2', 'sort_order' => 1],
                ],
            ],
        ],
        'score_fields' => [
            0 => ['name' => 'Overall Match %', 'unit' => 'percent', 'sort_order' => 0],
            1 => ['name' => 'Element Match %', 'unit' => 'percent', 'sort_order' => 1],
        ],
    ], $overrides);
}

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
