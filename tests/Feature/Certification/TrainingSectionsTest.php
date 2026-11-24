<?php

use App\Models\Activity;
use App\Models\ActivityCertificationCandidate;
use App\Models\ActivityType;
use App\Models\Certificate;
use App\Models\CertificateEnrollment;
use App\Models\CertificateRequirement;
use App\Models\CertificateRequirementDimensionRule;
use App\Models\CertificationCandidate;
use App\Models\CertificationInstrument;
use App\Models\CertificationInstrumentDimension;
use App\Models\CertificationInstrumentDimensionOption;
use App\Models\CertificationInstrumentScoreField;
use App\Models\CertificationInstrumentSubmission;
use App\Models\Program;
use App\Models\UserCertificationDuty;
use App\Services\CertificateProgressService;

/**
 * The capture fixture plus a coach-recorded training: Section 1 and 2 are Pass/Fail scored, Section 3 is not.
 *
 * @return array<string, mixed>
 */
function trainingFixture(): array
{
    $f = certificationActivityFixture();
    UserCertificationDuty::factory()->coach()->atProgram($f['program'])->create(['user_id' => $f['actor']->id]);
    $training = CertificationInstrument::factory()->training()->forProgram($f['program'])->create(['name' => 'W101', 'activity_type_id' => $f['type']->id]);
    $sections = CertificationInstrumentDimension::factory()->for($training, 'instrument')->create(['name' => 'Sections', 'type' => 'sections']);
    $s1 = CertificationInstrumentDimensionOption::factory()->for($sections, 'dimension')->create(['label' => 'Section 1', 'scored' => true, 'sort_order' => 1]);
    $s2 = CertificationInstrumentDimensionOption::factory()->for($sections, 'dimension')->create(['label' => 'Section 2', 'scored' => true, 'sort_order' => 2]);
    $s3 = CertificationInstrumentDimensionOption::factory()->for($sections, 'dimension')->create(['label' => 'Section 3', 'scored' => false, 'sort_order' => 3]);
    $passFail = CertificationInstrumentScoreField::factory()->for($training, 'instrument')->create(['name' => 'Overall', 'unit' => 'pass_fail']);

    return $f + compact('training', 'sections', 's1', 's2', 's3', 'passFail');
}

/** @param array<int, string|null> $results option id => result */
function recordTrainingSubmission(array $f, array $results, string $outcome = 'not_scored', string $date = '2026-10-26'): CertificationInstrumentSubmission
{
    $activity = Activity::factory()->create(['activity_type_id' => $f['type']->id, 'engagement_date' => $date, 'user_id' => $f['actor']->id]);
    $activity->programs()->attach($f['program']);
    $tag = ActivityCertificationCandidate::factory()->create(['activity_id' => $activity->id, 'certification_candidate_id' => $f['candidate']->id]);
    $submission = CertificationInstrumentSubmission::factory()->create(['activity_certification_candidate_id' => $tag->id, 'certification_instrument_id' => $f['training']->id, 'outcome' => $outcome]);
    foreach ($results as $optionId => $result) {
        $submission->dimensionValues()->create(['certification_instrument_dimension_id' => $f['sections']->id, 'certification_instrument_dimension_option_id' => $optionId, 'result' => $result]);
    }

    return $submission;
}

/** @param array<string, mixed> $overrides */
function trainingRequirement(array $f, array $optionIds, array $overrides = [], array $rule = []): CertificateRequirement
{
    $requirement = CertificateRequirement::factory()->for($f['certificate'])->create(array_merge(['kind' => 'training_sections', 'certification_instrument_id' => $f['training']->id, 'target_count' => 1, 'requires_passing' => false], $overrides));
    CertificateRequirementDimensionRule::factory()->for($requirement, 'requirement')->create(array_merge(['certification_instrument_dimension_id' => $f['sections']->id, 'mode' => 'coverage', 'option_ids' => $optionIds, 'min_count' => null], $rule));

    return $requirement;
}

/** @return array<string, mixed> */
function trainingSectionsResult(array $f, CertificateRequirement $requirement): array
{
    return app(CertificateProgressService::class)->evaluate($f['enrollment']->fresh())['requirements'][$requirement->id];
}

test('an admin builds a training with scored and unscored sections and a pass fail score field', function () {
    $admin = createSystemAdmin();
    $activityType = ActivityType::factory()->create();
    $payload = toolStorePayload([
        'name' => 'Wraparound 101', 'type' => 'training', 'activity_type_id' => $activityType->id, 'dimensions' => [],
        'sections' => [['label' => 'Engagement', 'scored' => '1'], ['label' => 'Planning', 'scored' => '0']],
        'score_fields' => [['name' => 'Overall', 'unit' => 'pass_fail']],
    ]);

    $this->actingAs($admin)->post(route('certification-instruments.store'), $payload)->assertSessionHasNoErrors();

    $training = CertificationInstrument::query()->where('name', 'Wraparound 101')->sole();
    expect($training->type->value)->toBe('training')
        ->and($training->activity_type_id)->toBe($activityType->id)
        ->and($training->dimensions->sole()->type->value)->toBe('sections')
        ->and($training->sectionsDimension()->options->pluck('scored', 'label')->all())->toBe(['Engagement' => true, 'Planning' => false])
        ->and($training->scoreFields->sole()->unit->value)->toBe('pass_fail');
});

test('a used training keeps its type, section scoring and pass fail units', function () {
    $f = trainingFixture();
    $numeric = CertificationInstrumentScoreField::factory()->for($f['training'], 'instrument')->create(['name' => 'Minutes', 'unit' => 'number']);
    $submission = recordTrainingSubmission($f, [$f['s1']->id => 'passed']);
    $submission->scores()->create(['certification_instrument_score_field_id' => $numeric->id, 'value' => 30]);
    $base = [
        'name' => 'W101', 'active' => '1', 'program_scope_mode' => 'specific', 'project_ids' => [$f['project']->id], 'program_ids' => [$f['program']->id],
        'sections' => [['id' => $f['s1']->id, 'label' => 'Section 1', 'scored' => '0'], ['id' => $f['s2']->id, 'label' => 'Section 2', 'scored' => '1'], ['id' => $f['s3']->id, 'label' => 'Section 3', 'scored' => '0']],
        'score_fields' => [['id' => $f['passFail']->id, 'name' => 'Overall', 'unit' => 'pass_fail'], ['id' => $numeric->id, 'name' => 'Minutes', 'unit' => 'pass_fail']],
    ];

    $this->actingAs($f['actor'])->put(route('certification-instruments.update', $f['training']), $base + ['type' => 'tool'])
        ->assertSessionHasErrors(['type', 'sections.0.scored', 'score_fields.1.unit']);
    $this->actingAs($f['actor'])->put(route('certification-instruments.update', $f['training']), $base + ['type' => 'training', 'activity_type_id' => ActivityType::factory()->create()->id])
        ->assertSessionHasErrors(['activity_type_id']);

    expect($f['training']->fresh()->type->value)->toBe('training')
        ->and($f['training']->fresh()->activity_type_id)->toBe($f['type']->id)
        ->and($f['s1']->fresh()->scored)->toBeTrue()
        ->and($numeric->fresh()->unit->value)->toBe('number');
});

test('a coach records many sections in one training submission with an optional outcome', function () {
    $f = trainingFixture();
    $payload = $f['payload'];
    $payload['certification_candidates'][0]['submissions'] = [[
        'certification_instrument_id' => $f['training']->id,
        'sections' => [$f['s1']->id, $f['s2']->id, $f['s3']->id],
        'section_results' => [$f['s1']->id => 'passed', $f['s2']->id => 'failed'],
        'scores' => [$f['passFail']->id => '0'],
    ]];

    $this->actingAs($f['actor'])->post(route('activities.store'), $payload)->assertSessionHasNoErrors();

    $submission = CertificationInstrumentSubmission::query()->sole();
    expect($submission->outcome->value)->toBe('not_scored')
        ->and($submission->dimensionValues->mapWithKeys(fn ($value) => [$value->certification_instrument_dimension_option_id => $value->result?->value])->all())
        ->toBe([$f['s1']->id => 'passed', $f['s2']->id => 'failed', $f['s3']->id => null])
        ->and($submission->scores->sole()->value)->toBe('0.0000');
});

test('re-saving an unchanged training submission keeps its recorder and section rows', function () {
    $f = trainingFixture();
    $payload = $f['payload'];
    $payload['certification_candidates'][0]['submissions'] = [['certification_instrument_id' => $f['training']->id, 'sections' => [$f['s3']->id, $f['s1']->id], 'section_results' => [$f['s1']->id => 'passed']]];
    $this->actingAs($f['actor'])->post(route('activities.store'), $payload)->assertSessionHasNoErrors();
    $activity = Activity::query()->latest('id')->firstOrFail();
    $submission = CertificationInstrumentSubmission::query()->sole();
    $valueIds = $submission->dimensionValues->modelKeys();
    $otherCoach = createSystemAdmin();
    UserCertificationDuty::factory()->coach()->atProgram($f['program'])->create(['user_id' => $otherCoach->id]);
    $payload['certification_candidates'][0]['id'] = $activity->certificationCandidates->sole()->id;
    $payload['certification_candidates'][0]['submissions'][0]['id'] = $submission->id;

    $this->actingAs($otherCoach)->put(route('activities.update', $activity), $payload)->assertSessionHasNoErrors();

    expect($submission->fresh()->recorded_by_user_id)->toBe($f['actor']->id)
        ->and($submission->fresh()->dimensionValues->modelKeys())->toBe($valueIds);
});

test('two candidates in one delivery can both record the same sections', function () {
    $f = trainingFixture();
    $second = CertificationCandidate::factory()->create();
    $f['agreement']->certificationCandidates()->create(['certification_candidate_id' => $second->id]);
    CertificateEnrollment::factory()->create(['certificate_id' => $f['certificate']->id, 'certification_candidate_id' => $second->id]);
    $submission = ['certification_instrument_id' => $f['training']->id, 'sections' => [$f['s1']->id, $f['s3']->id], 'section_results' => [$f['s1']->id => 'passed']];
    $payload = $f['payload'];
    $payload['certification_candidates'][0]['submissions'] = [$submission];
    $payload['certification_candidates'][] = ['certification_candidate_id' => $second->id, 'agreement_id' => $f['agreement']->id, 'completion_units' => 1, 'submissions' => [$submission]];

    $this->actingAs($f['actor'])->post(route('activities.store'), $payload)->assertSessionHasNoErrors();

    expect(CertificationInstrumentSubmission::query()->count())->toBe(2);
});

test('a blank pass fail score is not saved as a fail', function () {
    $f = trainingFixture();
    $payload = $f['payload'];
    $payload['certification_candidates'][0]['submissions'] = [['certification_instrument_id' => $f['training']->id, 'sections' => [$f['s3']->id], 'scores' => [$f['passFail']->id => '']]];

    $this->actingAs($f['actor'])->post(route('activities.store'), $payload)->assertSessionHasNoErrors();

    expect(CertificationInstrumentSubmission::query()->sole()->scores)->toBeEmpty();
});

test('invalid training submissions roll back the activity', function (string $case) {
    $f = trainingFixture();
    $submission = ['certification_instrument_id' => $f['training']->id, 'sections' => [$f['s1']->id], 'section_results' => [$f['s1']->id => 'passed']];
    $key = 'certification_candidates.0.submissions.0.';
    $error = match ($case) {
        'scored section without a result' => [$submission['section_results'] = [], $key.'section_results.'.$f['s1']->id][1],
        'unscored section with a result' => [$submission = ['sections' => [$f['s3']->id], 'section_results' => [$f['s3']->id => 'failed']] + $submission, $key.'section_results.'.$f['s3']->id][1],
        'section from another training' => [$submission['sections'] = [CertificationInstrumentDimensionOption::factory()->create()->id], $key.'sections'][1],
        'no sections' => [$submission = ['sections' => [], 'section_results' => []] + $submission, $key.'sections'][1],
        'pass fail score other than 0 or 1' => [$submission['scores'] = [$f['passFail']->id => '2'], $key.'scores.'.$f['passFail']->id][1],
        'training of another activity type' => [$f['training']->update(['activity_type_id' => ActivityType::factory()->create()->id]), $key.'certification_instrument_id'][1],
    };
    $payload = $f['payload'];
    $payload['certification_candidates'][0]['submissions'] = [$submission];

    $this->actingAs($f['actor'])->post(route('activities.store'), $payload)->assertSessionHasErrors($error);

    $this->assertDatabaseCount('activities', 0);
})->with(['scored section without a result', 'unscored section with a result', 'section from another training', 'no sections', 'pass fail score other than 0 or 1', 'training of another activity type']);

test('a training needs an activity type and a tool never keeps one', function () {
    $admin = createSystemAdmin();

    $this->actingAs($admin)->post(route('certification-instruments.store'), toolStorePayload(['name' => 'W101', 'type' => 'training', 'dimensions' => [], 'sections' => [['label' => 'Engagement']]]))
        ->assertSessionHasErrors(['activity_type_id']);
    $this->actingAs($admin)->post(route('certification-instruments.store'), toolStorePayload(['activity_type_id' => ActivityType::factory()->create()->id]))
        ->assertSessionHasNoErrors();

    expect(CertificationInstrument::query()->sole()->activity_type_id)->toBeNull();
});

test('a training sections requirement takes its activity type and family from the training', function () {
    $admin = createSystemAdmin();
    $payload = minimalCertificatePayload();
    $program = Program::query()->findOrFail($payload['program_ids'][0]);
    $training = CertificationInstrument::factory()->training()->forProgram($program)->create();
    $sections = CertificationInstrumentDimension::factory()->for($training, 'instrument')->create(['name' => 'Sections', 'type' => 'sections']);
    $option = CertificationInstrumentDimensionOption::factory()->for($sections, 'dimension')->create();
    $otherType = ActivityType::factory()->create();
    $payload['requirements'] = [[
        'phase' => 'initial', 'kind' => 'training_sections', 'certification_instrument_id' => $training->id, 'target_count' => 1,
        'activity_type_id' => $otherType->id, 'contact_family_id' => $otherType->contact_family_id,
        'dimension_rules' => ['sections' => ['mode' => 'coverage', 'option_ids' => [$option->id]]],
    ]];

    $this->actingAs($admin)->post(route('certificates.store'), $payload)->assertSessionHasNoErrors();

    expect(Certificate::query()->where('name', 'Validation Cert')->sole()->requirements->sole()->only(['activity_type_id', 'contact_family_id']))
        ->toBe(['activity_type_id' => $training->activity_type_id, 'contact_family_id' => $training->activityType->contact_family_id]);
});

test('a training sections requirement stores its sections rule on the training sections list', function () {
    $admin = createSystemAdmin();
    $payload = minimalCertificatePayload();
    $program = Program::query()->findOrFail($payload['program_ids'][0]);
    $training = CertificationInstrument::factory()->training()->forProgram($program)->create();
    $sections = CertificationInstrumentDimension::factory()->for($training, 'instrument')->create(['name' => 'Sections', 'type' => 'sections']);
    $options = CertificationInstrumentDimensionOption::factory()->count(3)->for($sections, 'dimension')->create();
    $payload['requirements'] = [[
        'phase' => 'initial', 'kind' => 'training_sections', 'certification_instrument_id' => $training->id, 'target_count' => 2, 'requires_passing' => '1',
        'dimension_rules' => ['sections' => ['mode' => 'quota', 'min_count' => 2, 'option_ids' => $options->take(2)->pluck('id')->all()]],
    ]];

    $this->actingAs($admin)->post(route('certificates.store'), $payload)->assertSessionHasNoErrors();

    $requirement = Certificate::query()->where('name', 'Validation Cert')->sole()->requirements->sole();
    expect($requirement->kind->value)->toBe('training_sections')
        ->and($requirement->requires_passing)->toBeTrue()
        ->and($requirement->dimensionRules->sole()->only(['certification_instrument_dimension_id', 'option_ids', 'min_count']))
        ->toBe(['certification_instrument_dimension_id' => $sections->id, 'option_ids' => $options->take(2)->pluck('id')->all(), 'min_count' => 2]);
});

test('requirement kinds reject the wrong instrument type and impossible section counts', function (string $case) {
    $admin = createSystemAdmin();
    $payload = minimalCertificatePayload();
    $program = Program::query()->findOrFail($payload['program_ids'][0]);
    $training = CertificationInstrument::factory()->training()->forProgram($program)->create();
    $sections = CertificationInstrumentDimension::factory()->for($training, 'instrument')->create(['name' => 'Sections', 'type' => 'sections']);
    $option = CertificationInstrumentDimensionOption::factory()->for($sections, 'dimension')->create();
    $tool = CertificationInstrument::factory()->forProgram($program)->create();
    $row = ['phase' => 'initial', 'kind' => 'training_sections', 'certification_instrument_id' => $training->id, 'target_count' => 1, 'dimension_rules' => ['sections' => ['mode' => 'coverage', 'option_ids' => [$option->id]]]];
    [$row, $error] = match ($case) {
        'tool for a training requirement' => [['certification_instrument_id' => $tool->id] + $row, 'requirements.0.certification_instrument_id'],
        'training for a tool requirement' => [['kind' => 'tool_submission', 'dimension_rules' => []] + $row, 'requirements.0.certification_instrument_id'],
        'more sections than checked' => [['dimension_rules' => ['sections' => ['mode' => 'quota', 'min_count' => 2, 'option_ids' => [$option->id]]]] + $row, 'requirements.0.dimension_rules.sections.min_count'],
        'sections of another training' => [['dimension_rules' => ['sections' => ['mode' => 'coverage', 'option_ids' => [CertificationInstrumentDimensionOption::factory()->create()->id]]]] + $row, 'requirements.0.dimension_rules.sections.option_ids'],
    };

    $this->actingAs($admin)->post(route('certificates.store'), minimalCertificatePayload(['requirements' => [$row]] + $payload))->assertSessionHasErrors($error);

    expect(Certificate::query()->where('name', 'Validation Cert')->exists())->toBeFalse();
})->with(['tool for a training requirement', 'training for a tool requirement', 'more sections than checked', 'sections of another training']);

test('a scored section counts once any eligible submission passes it when passing is required', function () {
    $this->travelTo(now()->setDate(2026, 10, 27)->startOfDay());
    $f = trainingFixture();
    $requirement = trainingRequirement($f, [$f['s1']->id, $f['s2']->id, $f['s3']->id], ['requires_passing' => true, 'target_count' => 2]);
    recordTrainingSubmission($f, [$f['s1']->id => 'failed', $f['s2']->id => 'failed', $f['s3']->id => null], 'not_passed', '2026-10-20');
    recordTrainingSubmission($f, [$f['s1']->id => 'passed'], 'not_passed', '2026-10-25');

    $result = trainingSectionsResult($f, $requirement);

    expect($result['count'])->toBe(2)
        ->and($result['rules']->sole()['count'])->toBe(2)
        ->and($result['rules']->sole()['sections']->where('counted', false)->map(fn ($section) => $section['option']->label)->values()->all())->toBe(['Section 2'])
        ->and($result['satisfied'])->toBeFalse();
});

test('without passing required ticked sections count and the overall outcome never gates a training', function () {
    $this->travelTo(now()->setDate(2026, 10, 27)->startOfDay());
    $f = trainingFixture();
    $requirement = trainingRequirement($f, [$f['s1']->id, $f['s2']->id, $f['s3']->id]);
    recordTrainingSubmission($f, [$f['s1']->id => 'failed', $f['s2']->id => 'failed', $f['s3']->id => null], 'not_passed');

    expect(trainingSectionsResult($f, $requirement)['satisfied'])->toBeTrue();
});

test('an at least N rule counts distinct sections and still needs the minimum submissions', function () {
    $this->travelTo(now()->setDate(2026, 10, 27)->startOfDay());
    $f = trainingFixture();
    $requirement = trainingRequirement($f, [$f['s1']->id, $f['s2']->id, $f['s3']->id], ['target_count' => 3], ['mode' => 'quota', 'min_count' => 2]);
    recordTrainingSubmission($f, [$f['s3']->id => null], date: '2026-10-20');
    recordTrainingSubmission($f, [$f['s3']->id => null], date: '2026-10-21');

    $before = trainingSectionsResult($f, $requirement);
    recordTrainingSubmission($f, [$f['s1']->id => 'passed'], date: '2026-10-22');
    $after = trainingSectionsResult($f, $requirement);

    expect($before['rules']->sole()['count'])->toBe(1)
        ->and($before['satisfied'])->toBeFalse()
        ->and($after['rules']->sole()['count'])->toBe(2)
        ->and($after['satisfied'])->toBeTrue();
});

test('the progress page lists every section by state and shows pass fail scores', function () {
    $this->travelTo(now()->setDate(2026, 10, 27)->startOfDay());
    $f = trainingFixture();
    trainingRequirement($f, [$f['s1']->id, $f['s2']->id, $f['s3']->id], ['requires_passing' => true, 'label' => 'W101 observer sections']);
    $submission = recordTrainingSubmission($f, [$f['s1']->id => 'passed', $f['s2']->id => 'failed']);
    $submission->scores()->create(['certification_instrument_score_field_id' => $f['passFail']->id, 'value' => 1]);

    $this->actingAs($f['actor'])->get(route('certificate-enrollments.show', $f['enrollment']))
        ->assertOk()
        ->assertSee('2 of 3 sections recorded · 1 passed')
        ->assertSeeInOrder([
            'app-status--success">', 'bi-check-lg', 'Section 1', ': done',
            'app-status--warning">', 'Section 2 · needs a Pass',
            'app-status--neutral">', 'Section 3', ': not attempted',
        ], false)
        ->assertDontSee('Still missing')
        ->assertSee('Sections: Section 1 · Pass, Section 2 · Fail')
        ->assertSee('Overall: Pass');
});
