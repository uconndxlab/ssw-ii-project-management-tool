<?php

use App\Models\ActivityType;
use App\Models\Certificate;
use App\Models\CertificateRequirement;
use App\Models\CertificateRequirementDimensionRule;
use App\Models\CertificateRequirementGroup;
use App\Models\CertificationRole;
use App\Models\CertificationTool;
use App\Models\CertificationToolDimension;
use App\Models\CertificationToolDimensionOption;
use App\Models\ContactFamily;

test('new contextual roles and sparse group links save together and reopen without changing ids', function () {
    $admin = createSystemAdmin();
    $coach = Certificate::factory()->create(['name' => 'Coach prerequisite']);
    $supervisor = Certificate::factory()->create(['name' => 'Supervisor prerequisite']);
    $payload = minimalCertificatePayload([
        'name' => 'Trainer authoring',
        'prerequisite_mode' => 'any',
        'prerequisite_certificate_ids' => [$coach->id, $supervisor->id],
        'validity_months' => 24,
        'default_window_months' => 12,
        'roles' => [7 => ['name' => 'Co-train', 'active' => 1]],
        'requirement_groups' => [9 => ['phase' => 'initial', 'label' => 'Training route', 'satisfy_mode' => 'any']],
        'requirements' => [
            30 => ['phase' => 'initial', 'kind' => 'activity_count', 'label' => 'Deliver training', 'target_count' => 8, 'role_index' => 7, 'group_index' => 9, 'window_months' => 6],
            12 => ['phase' => 'initial', 'kind' => 'attestation', 'label' => 'Coach approves equivalent experience', 'target_count' => 1, 'group_index' => 9],
            50 => ['phase' => 'renewal', 'kind' => 'activity_count', 'label' => 'Renewal delivery', 'target_count' => 4, 'role_index' => 7],
        ],
    ]);

    $this->actingAs($admin)->post(route('certificates.store'), $payload)->assertSessionHasNoErrors()->assertRedirect();

    $certificate = Certificate::where('name', 'Trainer authoring')->firstOrFail();
    $role = $certificate->roles()->sole();
    $group = $certificate->requirementGroups()->sole();
    $requirements = $certificate->requirements()->get();
    expect($requirements->pluck('label')->all())->toBe(['Deliver training', 'Coach approves equivalent experience', 'Renewal delivery']);
    expect($requirements[0]->certification_role_id)->toBe($role->id);
    expect($requirements[0]->certificate_requirement_group_id)->toBe($group->id);
    expect($requirements[0]->window_months)->toBe(6);
    expect($certificate->prerequisites()->count())->toBe(2);
    $this->actingAs($admin)->get(route('certificates.edit', $certificate))->assertOk()->assertSee('Deliver training')->assertSee('Co-train')->assertSee('Review rules');

    $payload['roles'][7]['id'] = $role->id;
    $payload['requirement_groups'][9]['id'] = $group->id;
    foreach ([30, 12, 50] as $position => $index) {
        $payload['requirements'][$index]['id'] = $requirements[$position]->id;
    }
    $payload['requirements'][12]['_delete'] = 1;
    $payload['requirements'][30]['target_count'] = 9;
    $this->actingAs($admin)->put(route('certificates.update', $certificate), $payload)->assertSessionHasNoErrors()->assertRedirect();

    expect($certificate->requirements()->count())->toBe(2);
    expect($requirements[0]->fresh()->target_count)->toBe(9);
    $this->assertModelMissing($requirements[1]);
    expect($certificate->roles()->sole()->id)->toBe($role->id);
    expect($certificate->requirementGroups()->sole()->id)->toBe($group->id);
});

test('foreign catalog row ids are rejected without modifying either certificate', function (string $collection, string $model) {
    $admin = createSystemAdmin();
    $certificate = Certificate::factory()->create();
    $other = Certificate::factory()->create();
    $foreign = $model::factory()->for($other, 'certificate')->create();
    $row = match ($collection) {
        'roles' => ['id' => $foreign->id, 'name' => 'Forged role'],
        'requirement_groups' => ['id' => $foreign->id, 'label' => 'Forged group', 'phase' => 'initial', 'satisfy_mode' => 'all'],
        default => ['id' => $foreign->id, 'label' => 'Forged requirement', 'kind' => 'attestation', 'target_count' => 1],
    };

    $this->actingAs($admin)->put(route('certificates.update', $certificate), minimalCertificatePayload([
        'name' => 'Unauthorized edit', $collection => [3 => $row],
    ]))->assertSessionHasErrors($collection.'.3.id');

    expect($certificate->fresh()->name)->toBe($certificate->name);
    expect($foreign->fresh()->certificate_id)->toBe($other->id);
})->with([
    ['roles', CertificationRole::class],
    ['requirement_groups', CertificateRequirementGroup::class],
    ['requirements', CertificateRequirement::class],
]);

test('dimension rules cannot be moved from a different requirement', function () {
    $admin = createSystemAdmin();
    $certificate = Certificate::factory()->create();
    $tool = CertificationTool::factory()->create(['program_scope_mode' => 'all']);
    $dimension = CertificationToolDimension::factory()->for($tool, 'tool')->create();
    $option = CertificationToolDimensionOption::factory()->for($dimension, 'dimension')->create();
    $first = CertificateRequirement::factory()->for($certificate)->toolSubmission($tool)->create();
    $second = CertificateRequirement::factory()->for($certificate)->toolSubmission($tool)->create();
    $foreign = CertificateRequirementDimensionRule::factory()->for($first, 'requirement')->create(['certification_tool_dimension_id' => $dimension->id]);

    $this->actingAs($admin)->put(route('certificates.update', $certificate), minimalCertificatePayload([
        'name' => $certificate->name,
        'requirements' => [4 => ['id' => $second->id, 'kind' => 'tool_submission', 'certification_tool_id' => $tool->id, 'target_count' => 6,
            'dimension_rules' => [8 => ['id' => $foreign->id, 'certification_tool_dimension_id' => $dimension->id, 'mode' => 'coverage', 'option_ids' => [$option->id]]],
        ]],
    ]))->assertSessionHasErrors('requirements.4.dimension_rules.8.id');

    expect($foreign->fresh()->certificate_requirement_id)->toBe($first->id);
    $this->assertModelExists($first);
});

test('coverage and quota categories must belong to their selected dimension', function (string $mode) {
    $admin = createSystemAdmin();
    $tool = CertificationTool::factory()->create(['program_scope_mode' => 'all']);
    $dimension = CertificationToolDimension::factory()->for($tool, 'tool')->create();
    $otherDimension = CertificationToolDimension::factory()->for($tool, 'tool')->create();
    $foreign = CertificationToolDimensionOption::factory()->for($otherDimension, 'dimension')->create();

    $this->actingAs($admin)->post(route('certificates.store'), minimalCertificatePayload([
        'requirements' => [['kind' => 'tool_submission', 'certification_tool_id' => $tool->id, 'target_count' => 6,
            'dimension_rules' => [['certification_tool_dimension_id' => $dimension->id, 'mode' => $mode, 'option_ids' => [$foreign->id], 'min_count' => 4]],
        ]],
    ]))->assertSessionHasErrors('requirements.0.dimension_rules.0.option_ids');

    expect(Certificate::where('name', 'Validation Cert')->exists())->toBeFalse();
})->with(['coverage', 'quota']);

test('requirement groups cannot reference a different stage or a removed group', function (array $group) {
    $admin = createSystemAdmin();

    $this->actingAs($admin)->post(route('certificates.store'), minimalCertificatePayload([
        'requirement_groups' => [8 => array_merge(['label' => 'Other group', 'satisfy_mode' => 'all'], $group)],
        'requirements' => [7 => ['phase' => 'initial', 'kind' => 'attestation', 'target_count' => 1, 'group_index' => 8]],
    ]))->assertSessionHasErrors('requirements.7.group_index');

    expect(Certificate::where('name', 'Validation Cert')->exists())->toBeFalse();
})->with([
    [['phase' => 'renewal']],
    [['phase' => 'initial', '_delete' => 1]],
]);

test('requirements cannot use a role owned by another certificate', function () {
    $admin = createSystemAdmin();
    $foreign = CertificationRole::factory()->for(Certificate::factory(), 'certificate')->create();

    $this->actingAs($admin)->post(route('certificates.store'), minimalCertificatePayload([
        'requirements' => [['kind' => 'activity_count', 'target_count' => 8, 'certification_role_id' => $foreign->id]],
    ]))->assertSessionHasErrors('requirements.0.certification_role_id');
});

test('invalid nested configuration restores all authored fields for correction', function () {
    $admin = createSystemAdmin();
    $payload = minimalCertificatePayload([
        'roles' => [6 => ['name' => 'Trainer', 'active' => 1]],
        'requirement_groups' => [11 => ['phase' => 'renewal', 'label' => 'Renewal alternatives', 'satisfy_mode' => 'n_of', 'required_count' => 3]],
        'requirements' => [17 => ['phase' => 'renewal', 'kind' => 'activity_count', 'target_count' => 8, 'group_index' => 11, 'role_index' => 6, 'window_months' => 18, 'notes' => 'Keep this note']],
    ]);

    $this->actingAs($admin)->from(route('certificates.create'))->post(route('certificates.store'), $payload)
        ->assertSessionHasErrors('requirement_groups.11.required_count')
        ->assertSessionHasInput('requirements.17.notes', 'Keep this note')
        ->assertSessionHasInput('requirements.17.role_index', 6)
        ->assertSessionHasInput('requirements.17.window_months', 18);
    $this->get(route('certificates.create'))->assertOk()->assertSee('Renewal alternatives')->assertSee('Keep this note');
});

test('nested catalog references reject arrays of otherwise valid ids', function () {
    $admin = createSystemAdmin();
    $tool = CertificationTool::factory()->create(['program_scope_mode' => 'all']);
    $dimension = CertificationToolDimension::factory()->for($tool, 'tool')->create();
    $role = CertificationRole::factory()->create(['certificate_id' => null]);
    $family = ContactFamily::factory()->create();
    $type = ActivityType::factory()->create();

    $this->actingAs($admin)->post(route('certificates.store'), minimalCertificatePayload([
        'requirements' => [['kind' => 'tool_submission', 'target_count' => 6,
            'contact_family_id' => [$family->id], 'activity_type_id' => [$type->id],
            'certification_tool_id' => [$tool->id], 'certification_role_id' => [$role->id],
            'dimension_rules' => [['certification_tool_dimension_id' => [$dimension->id], 'mode' => 'coverage']],
        ]],
    ]))->assertSessionHasErrors([
        'requirements.0.contact_family_id', 'requirements.0.activity_type_id',
        'requirements.0.certification_tool_id', 'requirements.0.certification_role_id',
        'requirements.0.dimension_rules.0.certification_tool_dimension_id',
    ]);

    expect(Certificate::where('name', 'Validation Cert')->exists())->toBeFalse();
});

test('existing dimension ids survive edits and incompatible kind changes require deleting the rules', function () {
    $admin = createSystemAdmin();
    $certificate = Certificate::factory()->create();
    $tool = CertificationTool::factory()->create(['program_scope_mode' => 'all']);
    $dimension = CertificationToolDimension::factory()->for($tool, 'tool')->create();
    $option = CertificationToolDimensionOption::factory()->for($dimension, 'dimension')->create();
    $requirement = CertificateRequirement::factory()->for($certificate)->toolSubmission($tool)->create();
    $rule = CertificateRequirementDimensionRule::factory()->for($requirement, 'requirement')->create([
        'certification_tool_dimension_id' => $dimension->id, 'option_ids' => [$option->id],
    ]);
    $payload = minimalCertificatePayload([
        'name' => $certificate->name,
        'requirements' => [14 => ['id' => $requirement->id, 'kind' => 'tool_submission', 'certification_tool_id' => $tool->id, 'target_count' => 6,
            'threshold_note' => 'Coach judgment, not score math',
            'dimension_rules' => [19 => ['id' => $rule->id, 'certification_tool_dimension_id' => $dimension->id, 'mode' => 'quota', 'option_ids' => [$option->id], 'min_count' => 4]],
        ]],
    ]);

    $this->actingAs($admin)->put(route('certificates.update', $certificate), $payload)->assertSessionHasNoErrors()->assertRedirect();
    expect($rule->fresh()->min_count)->toBe(4);
    expect($requirement->fresh()->threshold_note)->toBe('Coach judgment, not score math');
    expect($requirement->dimensionRules()->sole()->id)->toBe($rule->id);
    $this->get(route('certificates.edit', $certificate))->assertOk()->assertSee('Coach judgment, not score math');

    $payload['requirements'][14]['kind'] = 'attestation';
    $this->put(route('certificates.update', $certificate), $payload)->assertSessionHasErrors('requirements.14.kind');
    expect($requirement->fresh()->kind->value)->toBe('tool_submission');
    $this->assertModelExists($rule);

    $payload['requirements'][14]['dimension_rules'][19]['_delete'] = 1;
    $this->put(route('certificates.update', $certificate), $payload)->assertSessionHasNoErrors()->assertRedirect();
    expect($requirement->fresh()->kind->value)->toBe('attestation');
    expect($requirement->fresh()->certification_tool_id)->toBeNull();
    $this->assertModelMissing($rule);
});

test('nullable catalog collections remain valid for a certificate with no requirements', function () {
    $admin = createSystemAdmin();

    $this->actingAs($admin)->post(route('certificates.store'), minimalCertificatePayload([
        'roles' => null, 'requirement_groups' => null, 'requirements' => null,
    ]))->assertSessionHasNoErrors()->assertRedirect();

    expect(Certificate::where('name', 'Validation Cert')->sole()->requirements()->count())->toBe(0);
});
