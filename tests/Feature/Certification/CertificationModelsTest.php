<?php

use App\Enums\CertificateRequirementKind;
use App\Enums\CertificateRequirementPhase;
use App\Enums\ProgramScopeMode;
use App\Models\Certificate;
use App\Models\CertificateRequirement;
use App\Models\CertificateRequirementDimensionRule;
use App\Models\CertificateRequirementGroup;
use App\Models\CertificationRole;
use App\Models\CertificationTool;
use App\Models\CertificationToolDimension;
use App\Models\CertificationToolDimensionOption;
use App\Models\CertificationToolScoreField;
use App\Models\User;
use App\Models\UserCertificationDuty;

test('certification tool generates slug on create and orders nested relations', function () {
    $tool = CertificationTool::factory()->create(['name' => 'COMET Tool']);

    expect($tool->slug)->toBe('comet-tool');

    $dimB = CertificationToolDimension::factory()->for($tool, 'tool')->create(['name' => 'B', 'sort_order' => 2]);
    $dimA = CertificationToolDimension::factory()->for($tool, 'tool')->create(['name' => 'A', 'sort_order' => 1]);

    $tool->refresh();
    expect($tool->dimensions->pluck('id')->all())->toBe([$dimA->id, $dimB->id]);

    $optB = CertificationToolDimensionOption::factory()->for($dimA, 'dimension')->create(['label' => 'B', 'sort_order' => 2]);
    $optA = CertificationToolDimensionOption::factory()->for($dimA, 'dimension')->create(['label' => 'A', 'sort_order' => 1]);

    $dimA->refresh();
    expect($dimA->options->pluck('id')->all())->toBe([$optA->id, $optB->id]);
    expect($optA->value)->toBe('a');
});

test('certification tool score field generates slug on create', function () {
    $field = CertificationToolScoreField::factory()->create(['name' => 'Overall Match %']);

    expect($field->slug)->toBe('overall-match');
});

test('certificate casts program scope mode and relates to prerequisites', function () {
    $coach = Certificate::factory()->create(['name' => 'Coach Cert']);
    $trainer = Certificate::factory()->create(['name' => 'Trainer Cert', 'prerequisite_mode' => 'any']);
    $trainer->prerequisites()->attach($coach);

    expect($trainer->program_scope_mode)->toBe(ProgramScopeMode::Specific);
    expect($trainer->prerequisites->pluck('id')->all())->toBe([$coach->id]);
});

test('certificate orders requirement groups and requirements by sort_order', function () {
    $certificate = Certificate::factory()->create();
    $groupB = CertificateRequirementGroup::factory()->for($certificate)->create(['label' => 'B', 'sort_order' => 2]);
    $groupA = CertificateRequirementGroup::factory()->for($certificate)->create(['label' => 'A', 'sort_order' => 1]);

    $reqB = CertificateRequirement::factory()->for($certificate)->create(['sort_order' => 2, 'label' => 'B']);
    $reqA = CertificateRequirement::factory()->for($certificate)->create(['sort_order' => 1, 'label' => 'A']);

    $certificate->refresh();
    expect($certificate->requirementGroups->pluck('id')->all())->toBe([$groupA->id, $groupB->id]);
    expect($certificate->requirements->pluck('id')->all())->toBe([$reqA->id, $reqB->id]);
});

test('certificate requirementsForPhase filters by phase', function () {
    $certificate = Certificate::factory()->create();
    CertificateRequirement::factory()->for($certificate)->create(['phase' => CertificateRequirementPhase::Initial]);
    CertificateRequirement::factory()->for($certificate)->renewal()->create();

    expect($certificate->requirementsForPhase(CertificateRequirementPhase::Initial)->count())->toBe(1);
    expect($certificate->requirementsForPhase(CertificateRequirementPhase::Renewal)->count())->toBe(1);
});

test('certificate requirement casts kind and relates to dimension rules', function () {
    $tool = CertificationTool::factory()->create();
    $dimension = CertificationToolDimension::factory()->for($tool, 'tool')->create();
    $requirement = CertificateRequirement::factory()
        ->toolSubmission($tool)
        ->for(Certificate::factory())
        ->create();

    CertificateRequirementDimensionRule::factory()
        ->for($requirement, 'requirement')
        ->create(['certification_tool_dimension_id' => $dimension->id, 'option_ids' => [1, 2]]);

    $requirement->refresh();
    expect($requirement->kind)->toBe(CertificateRequirementKind::ToolSubmission);
    expect($requirement->dimensionRules)->toHaveCount(1);
    expect($requirement->dimensionRules->first()->option_ids)->toBe([1, 2]);
});

test('certification role generates slug and scopes to certificate', function () {
    $certificate = Certificate::factory()->create();
    $role = CertificationRole::factory()->for($certificate)->create(['name' => 'Observer']);

    expect($role->slug)->toBe('observer');
    expect($role->certificate_id)->toBe($certificate->id);

    $global = CertificationRole::factory()->global()->create(['name' => 'Attend']);
    expect($global->certificate_id)->toBeNull();
});

test('user certification duty casts duty and scope type', function () {
    [$project, $program] = createProjectWithProgram();
    $user = User::factory()->create();
    $duty = UserCertificationDuty::factory()
        ->coach()
        ->atProgram($program)
        ->create(['user_id' => $user->id]);

    expect($duty->duty->value)->toBe('coach');
    expect($duty->scope_id)->toBe($program->id);
});

test('certificate and tool scopeActive and scopeNotRetired work', function () {
    $active = CertificationTool::factory()->create();
    $retired = CertificationTool::factory()->retired()->create();

    expect(CertificationTool::query()->active()->pluck('id')->all())->toContain($active->id);
    expect(CertificationTool::query()->active()->pluck('id')->all())->not->toContain($retired->id);
    expect(CertificationTool::query()->notRetired()->pluck('id')->all())->toContain($active->id);
    expect(CertificationTool::query()->notRetired()->pluck('id')->all())->not->toContain($retired->id);

    $activeCert = Certificate::factory()->create();
    $retiredCert = Certificate::factory()->retired()->create();

    expect(Certificate::query()->active()->pluck('id')->all())->toContain($activeCert->id);
    expect(Certificate::query()->notRetired()->pluck('id')->all())->not->toContain($retiredCert->id);
});

test('certificate and tool visibleTo resolves without throwing', function () {
    [$project, $program] = createProjectWithProgram();
    $admin = createSystemAdmin();
    $tool = CertificationTool::factory()->forProgram($program)->create();
    $certificate = Certificate::factory()->forProgram($program)->create();

    expect(CertificationTool::query()->visibleTo($admin)->whereKey($tool)->exists())->toBeTrue();
    expect(Certificate::query()->visibleTo($admin)->whereKey($certificate)->exists())->toBeTrue();
});

test('deleting certificate cascades to requirements and groups', function () {
    $certificate = Certificate::factory()->create();
    $group = CertificateRequirementGroup::factory()->for($certificate)->create();
    $requirement = CertificateRequirement::factory()->for($certificate)->create([
        'certificate_requirement_group_id' => $group->id,
    ]);

    $certificate->delete();

    expect(CertificateRequirementGroup::find($group->id))->toBeNull();
    expect(CertificateRequirement::find($requirement->id))->toBeNull();
});

test('user has certification access helper and certificationDuties relation', function () {
    $user = User::factory()->create();
    UserCertificationDuty::factory()->coach()->create(['user_id' => $user->id]);

    expect($user->certification()->isCoach())->toBeTrue();
    expect($user->certificationDuties()->count())->toBe(1);
});
