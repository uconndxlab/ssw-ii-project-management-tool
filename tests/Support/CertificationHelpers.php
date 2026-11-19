<?php

use App\Enums\CertificateDimensionRuleMode;
use App\Enums\CertificateRequirementKind;
use App\Enums\CertificateRequirementPhase;
use App\Models\ActivityType;
use App\Models\Agreement;
use App\Models\AgreementCertificationCandidate;
use App\Models\AgreementDeliverable;
use App\Models\Certificate;
use App\Models\CertificateEnrollment;
use App\Models\CertificationCandidate;
use App\Models\CertificationInstrument;
use App\Models\CertificationInstrumentDimension;
use App\Models\CertificationInstrumentDimensionOption;
use App\Models\CertificationInstrumentScoreField;
use App\Models\ContactFamily;
use App\Models\Organization;
use App\Models\Program;
use App\Models\Project;
use App\Models\State;
use App\Models\User;

/**
 * @return array<string, mixed>
 */
function agreementStorePayload(Project $project, Program $program, array $overrides = []): array
{
    $state = State::factory()->create();
    $member = User::factory()->create();
    $member->programs()->attach($program->id);

    return array_merge([
        'name' => 'Agreement '.uniqid(),
        'program_scope_mode' => 'specific',
        'project_ids' => [$project->id],
        'program_ids' => [$program->id],
        'state_ids' => [$state->id],
        'user_ids' => [$member->id],
        'principal_investigator_ids' => [$member->id],
        'time_tracking_mode' => 'none',
        'require_payor' => 0,
        'require_payee' => 0,
        'start_date' => now()->toDateString(),
        'end_date' => now()->addYear()->toDateString(),
    ], $overrides);
}

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
        $crestTool = CertificationInstrument::factory()->forProgram($program)->create(['name' => 'CREST '.uniqid()]);
        $cometTool = CertificationInstrument::factory()->forProgram($program)->create(['name' => 'COMET '.uniqid()]);
        $dimension = CertificationInstrumentDimension::factory()->for($cometTool, 'instrument')->create(['name' => 'Phase']);
        $option = CertificationInstrumentDimensionOption::factory()->for($dimension, 'dimension')->create(['label' => 'Phase 1']);

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
                'certification_instrument_id' => $crestTool->id,
                'target_count' => 12,
                'requires_passing' => '1',
            ],
            2 => [
                'phase' => CertificateRequirementPhase::Initial->value,
                'kind' => CertificateRequirementKind::ToolSubmission->value,
                'label' => 'COMET with rules',
                'certification_instrument_id' => $cometTool->id,
                'target_count' => 6,
                'requires_passing' => '1',
                'dimension_rules' => [
                    0 => [
                        'certification_instrument_dimension_id' => $dimension->id,
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

function certificationActivityFixture(): array
{
    [$project, $program] = createProjectWithProgram();
    $actor = createSystemAdmin();
    $state = State::factory()->create();
    $organization = Organization::factory()->create();
    $organization->states()->attach($state);
    $family = ContactFamily::factory()->create();
    $type = ActivityType::factory()->for($family)->create();
    $agreement = Agreement::factory()->current()->create();
    $agreement->programs()->attach($program);
    $agreement->states()->attach($state);
    $agreement->organizations()->attach($organization, ['payor_source' => false, 'recipient' => false]);
    AgreementDeliverable::factory()->for($agreement)->completionByContact()->create(['contact_family_id' => $family->id, 'program_id' => $program->id]);
    $candidate = CertificationCandidate::factory()->create();
    $roster = AgreementCertificationCandidate::factory()->create(['agreement_id' => $agreement->id, 'certification_candidate_id' => $candidate->id, 'program_id' => $program->id]);
    $certificate = Certificate::factory()->forProgram($program)->create();
    $enrollment = CertificateEnrollment::factory()->create(['certificate_id' => $certificate->id, 'certification_candidate_id' => $candidate->id, 'sponsoring_agreement_id' => $agreement->id]);
    $tool = CertificationInstrument::factory()->forProgram($program)->create();
    $dimension = CertificationInstrumentDimension::factory()->for($tool, 'instrument')->create(['name' => 'Phase']);
    $option = CertificationInstrumentDimensionOption::factory()->for($dimension, 'dimension')->create(['label' => 'Phase 2']);
    $score = CertificationInstrumentScoreField::factory()->for($tool, 'instrument')->create();
    $payload = [
        'agreement_ids' => [$agreement->id], 'state_ids' => [$state->id], 'organization_ids' => [$organization->id],
        'project_ids' => [$project->id], 'program_ids' => [$program->id], 'engagement_date' => '2026-10-26',
        'contact_family_id' => $family->id, 'activity_type_id' => $type->id, 'completion_count' => 1,
        'certification_candidates' => [[
            'certification_candidate_id' => $candidate->id, 'agreement_id' => $agreement->id, 'completion_units' => 1,
        ]],
    ];

    return compact('project', 'program', 'actor', 'state', 'organization', 'family', 'type', 'agreement', 'candidate', 'roster', 'certificate', 'enrollment', 'tool', 'dimension', 'option', 'score', 'payload');
}

function certificationSubmissionPayload(array $fixture, array $overrides = []): array
{
    return array_merge([
        'certification_instrument_id' => $fixture['tool']->id, 'outcome' => 'passed',
        'dimensions' => [$fixture['dimension']->id => $fixture['option']->id],
        'scores' => [$fixture['score']->id => 5],
    ], $overrides);
}
