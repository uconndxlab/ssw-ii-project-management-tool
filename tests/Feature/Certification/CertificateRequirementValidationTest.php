<?php

use App\Enums\CertificateDimensionRuleMode;
use App\Enums\CertificateRequirementKind;
use App\Enums\CertificateRequirementPhase;
use App\Models\ActivityType;
use App\Models\Certificate;
use App\Models\CertificationInstrument;
use App\Models\CertificationInstrumentDimension;
use App\Models\CertificationInstrumentDimensionOption;
use App\Models\ContactFamily;

test('tool submission requirement requires certification_instrument_id', function () {
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
        ->assertSessionHasErrors('requirements.0.certification_instrument_id');

    expect(Certificate::where('name', 'Validation Cert')->exists())->toBeFalse();
});

test('dimension rule must belong to the selected tool', function () {
    $admin = createSystemAdmin();
    [$project, $program] = createProjectWithProgram();
    $toolA = CertificationInstrument::factory()->forProgram($program)->create();
    $toolB = CertificationInstrument::factory()->forProgram($program)->create();
    $foreignDimension = CertificationInstrumentDimension::factory()->for($toolB, 'instrument')->create();

    $this->actingAs($admin)
        ->from(route('certificates.create'))
        ->post(route('certificates.store'), minimalCertificatePayload([
            'requirements' => [
                0 => [
                    'phase' => CertificateRequirementPhase::Initial->value,
                    'kind' => CertificateRequirementKind::ToolSubmission->value,
                    'certification_instrument_id' => $toolA->id,
                    'target_count' => 1,
                    'dimension_rules' => [
                        0 => [
                            'certification_instrument_dimension_id' => $foreignDimension->id,
                            'mode' => CertificateDimensionRuleMode::Coverage->value,
                            'option_ids' => [],
                        ],
                    ],
                ],
            ],
        ]))
        ->assertSessionHasErrors('requirements.0.dimension_rules.0.certification_instrument_dimension_id');
});

test('quota dimension rule requires min_count', function () {
    $admin = createSystemAdmin();
    [$project, $program] = createProjectWithProgram();
    $tool = CertificationInstrument::factory()->forProgram($program)->create();
    $dimension = CertificationInstrumentDimension::factory()->for($tool, 'instrument')->create();
    $option = CertificationInstrumentDimensionOption::factory()->for($dimension, 'dimension')->create();

    $this->actingAs($admin)
        ->from(route('certificates.create'))
        ->post(route('certificates.store'), minimalCertificatePayload([
            'requirements' => [
                0 => [
                    'phase' => CertificateRequirementPhase::Initial->value,
                    'kind' => CertificateRequirementKind::ToolSubmission->value,
                    'certification_instrument_id' => $tool->id,
                    'target_count' => 1,
                    'dimension_rules' => [
                        0 => [
                            'certification_instrument_dimension_id' => $dimension->id,
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
    $tool = CertificationInstrument::factory()->forProgram($program)->create();
    $dimension = CertificationInstrumentDimension::factory()->for($tool, 'instrument')->create();

    $this->actingAs($admin)
        ->from(route('certificates.create'))
        ->post(route('certificates.store'), minimalCertificatePayload([
            'requirements' => [
                0 => [
                    'phase' => CertificateRequirementPhase::Initial->value,
                    'kind' => CertificateRequirementKind::ToolSubmission->value,
                    'certification_instrument_id' => $tool->id,
                    'target_count' => 1,
                    'dimension_rules' => [
                        0 => [
                            'certification_instrument_dimension_id' => $dimension->id,
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

test('an activity type outside the selected family is rejected', function () {
    $admin = createSystemAdmin();
    $training = ContactFamily::factory()->create();
    $coaching = ContactFamily::factory()->create();
    $coachingType = ActivityType::factory()->for($coaching)->create();

    $this->actingAs($admin)
        ->from(route('certificates.create'))
        ->post(route('certificates.store'), minimalCertificatePayload([
            'requirements' => [
                0 => [
                    'phase' => CertificateRequirementPhase::Initial->value,
                    'kind' => CertificateRequirementKind::ActivityCount->value,
                    'contact_family_id' => $training->id,
                    'activity_type_id' => $coachingType->id,
                    'target_count' => 1,
                ],
            ],
        ]))
        ->assertSessionHasErrors('requirements.0.activity_type_id');

    expect(Certificate::where('name', 'Validation Cert')->exists())->toBeFalse();
});
