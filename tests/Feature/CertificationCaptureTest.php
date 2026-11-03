<?php

use App\Models\Activity;
use App\Models\CertificateRequirement;
use App\Models\CertificationCandidate;
use App\Models\CertificationRole;
use App\Models\CertificationTool;
use App\Models\CertificationToolDimensionOption;
use App\Models\CertificationToolSubmission;
use App\Models\Program;
use App\Models\UserCertificationDuty;

function logCertificationFixture(array $f, array $payload): Activity
{
    test()->actingAs($f['actor'])->post(route('activities.store'), $payload)->assertSessionHasNoErrors()->assertRedirect();

    return Activity::query()->latest('id')->firstOrFail();
}

test('training tags and review flag save without submissions and duplication copies both', function () {
    $f = certificationActivityFixture();
    $payload = $f['payload'] + ['needs_certification_review' => true];
    foreach (range(1, 5) as $index) {
        $candidate = CertificationCandidate::factory()->create();
        $f['agreement']->certificationCandidates()->create(['certification_candidate_id' => $candidate->id]);
        $payload['certification_candidates'][] = ['certification_candidate_id' => $candidate->id, 'agreement_id' => $f['agreement']->id, 'completion_units' => 1];
    }
    $activity = logCertificationFixture($f, $payload);
    expect($activity->certificationCandidates)->toHaveCount(6);
    expect($activity->certificationCandidates->first()->submissions)->toBeEmpty();
    expect($activity->certification_review_requested_at)->not->toBeNull();
    $this->post(route('activities.duplicate', $activity))->assertRedirect();
    $copy = Activity::query()->latest('id')->firstOrFail();
    expect($copy->certificationCandidates)->toHaveCount(6);
    expect($copy->certification_review_requested_at)->not->toBeNull();
});

test('duplication copies recorded submissions with their dimensions and scores', function () {
    $f = certificationActivityFixture();
    UserCertificationDuty::factory()->coach()->atProgram($f['program'])->create(['user_id' => $f['actor']->id]);
    $payload = $f['payload'];
    $payload['certification_candidates'][0]['submissions'] = [certificationSubmissionPayload($f)];
    $activity = logCertificationFixture($f, $payload);

    $this->post(route('activities.duplicate', $activity))->assertRedirect();

    $copy = Activity::query()->latest('id')->firstOrFail();
    $submission = $copy->certificationCandidates->sole()->submissions->sole();
    expect($submission->outcome->value)->toBe('passed')
        ->and($submission->recorded_by_user_id)->toBe($f['actor']->id)
        ->and($submission->dimensionValues->sole()->certification_tool_dimension_option_id)->toBe($f['option']->id)
        ->and($submission->scores->sole()->value)->toBe('5.0000')
        ->and($activity->certificationCandidates->sole()->submissions)->toHaveCount(1);
});

test('coach records multiple independent submissions with required dimensions and explicit outcomes', function () {
    $f = certificationActivityFixture();
    UserCertificationDuty::factory()->coach()->atProgram($f['program'])->create(['user_id' => $f['actor']->id]);
    $payload = $f['payload'];
    $payload['certification_candidates'][0]['submissions'] = [certificationSubmissionPayload($f), certificationSubmissionPayload($f, ['outcome' => 'not_passed', 'scores' => []])];
    $activity = logCertificationFixture($f, $payload);
    $tag = $activity->certificationCandidates->first();
    expect($tag->submissions)->toHaveCount(2);
    expect($tag->submissions->first()->scores->first()->value)->toBe('5.0000');
    expect($tag->submissions->first()->dimensionValues->first()->certification_tool_dimension_option_id)->toBe($f['option']->id);
    expect($tag->submissions->first()->recorded_by_user_id)->toBe($f['actor']->id);
    $payload['certification_candidates'][0]['id'] = $tag->id;
    $payload['certification_candidates'][0]['submissions'] = [['id' => $tag->submissions->first()->id, 'certification_tool_id' => $f['tool']->id, '_delete' => true]];
    $this->put(route('activities.update', $activity), $payload)->assertSessionHasNoErrors();
    expect($tag->submissions()->count())->toBe(1);
});

test('forged candidate or dimension selections roll back the whole activity', function (string $bad) {
    $f = certificationActivityFixture();
    UserCertificationDuty::factory()->coach()->atProgram($f['program'])->create(['user_id' => $f['actor']->id]);
    $payload = $f['payload'];
    $error = 'certification_candidates.0.certification_candidate_id';
    if ($bad === 'candidate') {
        $payload['certification_candidates'][0]['certification_candidate_id'] = CertificationCandidate::factory()->create()->id;
    } else {
        $submission = certificationSubmissionPayload($f);
        if ($bad === 'dimension') {
            $submission['dimensions'][$f['dimension']->id] = CertificationToolDimensionOption::factory()->create()->id;
        }
        if ($bad === 'missing dimension') {
            $submission['dimensions'] = [];
        }
        if ($bad === 'outcome') {
            unset($submission['outcome']);
        }
        $payload['certification_candidates'][0]['submissions'] = [$submission];
        $error = $bad === 'outcome' ? 'certification_candidates.0.submissions.0.outcome' : 'certification_candidates.0.submissions.0.dimensions.'.$f['dimension']->id;
    }
    $this->actingAs($f['actor'])->post(route('activities.store'), $payload)->assertSessionHasErrors($error);
    $this->assertDatabaseCount('activities', 0);
})->with(['candidate', 'dimension', 'missing dimension', 'outcome']);

test('admin without coach duty cannot submit evidence or clear review', function () {
    $f = certificationActivityFixture();
    $payload = $f['payload'];
    $payload['certification_candidates'][0]['submissions'] = [certificationSubmissionPayload($f)];
    $this->actingAs($f['actor'])->post(route('activities.store'), $payload)->assertSessionHasErrors('certification_candidates.0.submissions.0');
    $activity = logCertificationFixture($f, $f['payload'] + ['needs_certification_review' => true]);
    $payload = $f['payload'];
    $payload['certification_candidates'][0]['id'] = $activity->certificationCandidates->first()->id;
    $this->put(route('activities.update', $activity), array_merge($payload, ['needs_certification_review' => false]))->assertSessionHasErrors('needs_certification_review');
    expect($activity->fresh()->certification_review_requested_at)->not->toBeNull();
});

test('saved tags survive roster removal and omitted payloads never delete evidence', function () {
    $f = certificationActivityFixture();
    $activity = logCertificationFixture($f, $f['payload']);
    $f['roster']->delete();
    $payload = $f['payload'];
    $payload['certification_candidates'][0]['id'] = $activity->certificationCandidates->first()->id;
    $this->put(route('activities.update', $activity), $payload)->assertSessionHasNoErrors();
    unset($payload['certification_candidates']);
    $this->put(route('activities.update', $activity), $payload)->assertSessionHasNoErrors();
    expect($activity->certificationCandidates()->count())->toBe(1);
});

test('non coach cannot remove a candidate holding submissions or read scores in the editor', function () {
    $f = certificationActivityFixture();
    $activity = logCertificationFixture($f, $f['payload']);
    $tag = $activity->certificationCandidates->first();
    $submission = CertificationToolSubmission::factory()->create(['activity_certification_candidate_id' => $tag->id, 'certification_tool_id' => $f['tool']->id, 'notes' => 'private submission narrative']);
    $viewer = createProgramViewer($f['program']);
    $activity->participants()->attach($viewer);
    $this->actingAs($viewer)->get(route('activities.edit', $activity))->assertOk()->assertDontSee('private submission narrative');
    $payload = $f['payload'];
    $payload['certification_candidates'][0] += ['id' => $tag->id, '_delete' => true];
    $this->actingAs($f['actor'])->put(route('activities.update', $activity), $payload)->assertSessionHasErrors('certification_candidates.0._delete');
    expect($tag->fresh())->not->toBeNull();
    expect($submission->fresh())->not->toBeNull();
});

test('review filter restricts the activity list without granting edit access', function () {
    $f = certificationActivityFixture();
    $activity = logCertificationFixture($f, $f['payload'] + ['needs_certification_review' => true]);
    Activity::factory()->create();
    $this->get(route('activities.index', ['needs_certification_review' => 1]))->assertOk()->assertViewHas('activities', fn ($items) => $items->total() === 1);
    $viewer = createProgramViewer($f['program']);
    UserCertificationDuty::factory()->coach()->atProgram($f['program'])->create(['user_id' => $viewer->id]);
    $this->actingAs($viewer)->get(route('activities.edit', $activity))->assertForbidden();
});

test('saved submission identity cannot be moved and used definitions cannot be deleted', function () {
    $f = certificationActivityFixture();
    UserCertificationDuty::factory()->coach()->atProgram($f['program'])->create(['user_id' => $f['actor']->id]);
    $payload = $f['payload'];
    $payload['certification_candidates'][0]['submissions'] = [certificationSubmissionPayload($f)];
    $activity = logCertificationFixture($f, $payload);
    $tag = $activity->certificationCandidates->first();
    $sub = $tag->submissions->first();
    $other = CertificationTool::factory()->forProgram($f['program'])->create();
    $payload['certification_candidates'][0]['id'] = $tag->id;
    $payload['certification_candidates'][0]['submissions'][0]['id'] = $sub->id;
    $payload['certification_candidates'][0]['submissions'][0]['certification_tool_id'] = $other->id;
    $this->put(route('activities.update', $activity), $payload)->assertSessionHasErrors('certification_candidates.0.submissions.0.certification_tool_id');
    $toolPayload = [
        'name' => $f['tool']->name, 'active' => true, 'program_scope_mode' => 'specific',
        'project_ids' => [$f['project']->id], 'program_ids' => [$f['program']->id],
        'dimensions' => [], 'score_fields' => [],
    ];
    $this->put(route('certification-tools.update', $f['tool']), $toolPayload)->assertSessionHasErrors(['dimensions', 'score_fields']);
    $this->delete(route('certification-tools.destroy', $f['tool']))->assertRedirect();
    expect($f['tool']->fresh()->retired_at)->not->toBeNull();
    expect($sub->fresh())->not->toBeNull();
});

test('retired tools remain on saved submissions but reject new submissions', function () {
    $f = certificationActivityFixture();
    UserCertificationDuty::factory()->coach()->atProgram($f['program'])->create(['user_id' => $f['actor']->id]);
    $payload = $f['payload'];
    $payload['certification_candidates'][0]['submissions'] = [certificationSubmissionPayload($f)];
    $activity = logCertificationFixture($f, $payload);
    $tag = $activity->certificationCandidates->first();
    $f['tool']->update(['retired_at' => now(), 'active' => false]);
    $payload['certification_candidates'][0]['id'] = $tag->id;
    $payload['certification_candidates'][0]['submissions'][0]['id'] = $tag->submissions->first()->id;
    $this->put(route('activities.update', $activity), $payload)->assertSessionHasNoErrors();
    $payload['certification_candidates'][0]['submissions'][] = certificationSubmissionPayload($f);
    $this->put(route('activities.update', $activity), $payload)->assertSessionHasErrors('certification_candidates.0.submissions.1.certification_tool_id');
    expect($tag->submissions()->count())->toBe(1);
});

test('new roles must be required for the candidate classification and scope', function () {
    $f = certificationActivityFixture();
    $role = CertificationRole::factory()->create();
    $payload = $f['payload'];
    $payload['certification_candidates'][0]['certification_role_id'] = $role->id;
    $this->actingAs($f['actor'])->post(route('activities.store'), $payload)->assertSessionHasErrors('certification_candidates.0.certification_role_id');
    CertificateRequirement::factory()->for($f['certificate'])->activityCount()->create(['activity_type_id' => $f['type']->id, 'certification_role_id' => $role->id]);
    $activity = logCertificationFixture($f, $payload);
    expect($activity->certificationCandidates->first()->certification_role_id)->toBe($role->id);
});

test('evidence protects a started enrollment from the assignment removal shortcut', function () {
    $f = certificationActivityFixture();
    logCertificationFixture($f, $f['payload']);
    expect($f['enrollment']->isUnstarted())->toBeFalse();
    $payload = agreementStorePayload($f['project'], $f['program'], [
        'certification_candidates' => [[
            'id' => $f['roster']->id, 'certification_candidate_id' => $f['candidate']->id,
            'name' => $f['candidate']->name, 'email' => $f['candidate']->email,
            'assignments' => [['certificate_id' => $f['certificate']->id, '_delete' => true]],
        ]],
    ]);
    $this->put(route('agreements.update', $f['agreement']), $payload)->assertSessionHasErrors('certification_candidates.0.assignments.0.certificate_id');
    expect($f['enrollment']->fresh())->not->toBeNull();
});

test('saving an unchanged submission preserves its recorder and child evidence ids', function () {
    $f = certificationActivityFixture();
    UserCertificationDuty::factory()->coach()->atProgram($f['program'])->create(['user_id' => $f['actor']->id]);
    $payload = $f['payload'];
    $payload['certification_candidates'][0]['submissions'] = [certificationSubmissionPayload($f)];
    $activity = logCertificationFixture($f, $payload);
    $tag = $activity->certificationCandidates->first();
    $submission = $tag->submissions->first();
    $valueId = $submission->dimensionValues->first()->id;
    $secondCoach = createSystemAdmin();
    UserCertificationDuty::factory()->coach()->atProgram($f['program'])->create(['user_id' => $secondCoach->id]);
    $payload['certification_candidates'][0]['id'] = $tag->id;
    $payload['certification_candidates'][0]['submissions'][0]['id'] = $submission->id;
    $payload['certification_candidates'][0]['submissions'][0]['scores'][$f['score']->id] = '5.0000';
    $this->actingAs($secondCoach)->put(route('activities.update', $activity), $payload)->assertSessionHasNoErrors();
    expect($submission->fresh()->recorded_by_user_id)->toBe($f['actor']->id);
    expect($submission->fresh()->dimensionValues->first()->id)->toBe($valueId);
});

test('a coach duty must intersect the activity tool and candidate certificate', function () {
    $f = certificationActivityFixture();
    $otherProgram = Program::factory()->create();
    $f['certificate']->programs()->sync([$otherProgram->id]);
    UserCertificationDuty::factory()->coach()->atProgram($f['program'])->create(['user_id' => $f['actor']->id]);
    $payload = $f['payload'];
    $payload['certification_candidates'][0]['submissions'] = [certificationSubmissionPayload($f)];
    $this->actingAs($f['actor'])->post(route('activities.store'), $payload)->assertSessionHasErrors('certification_candidates.0.submissions.0');
});

test('save and duplicate reopens the create form with certification evidence that saves as a new copy', function () {
    $f = certificationActivityFixture();
    UserCertificationDuty::factory()->coach()->atProgram($f['program'])->create(['user_id' => $f['actor']->id]);
    $payload = $f['payload'];
    $payload['certification_candidates'][0]['submissions'] = [certificationSubmissionPayload($f)];
    $activity = logCertificationFixture($f, $payload);
    $tag = $activity->certificationCandidates->first();
    $payload['certification_candidates'][0]['id'] = $tag->id;
    $payload['certification_candidates'][0]['submissions'][0]['id'] = $tag->submissions->first()->id;

    $this->put(route('activities.update', $activity), $payload + ['save_and_duplicate' => 1])
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('activities.create'))
        ->assertSessionHasInput('engagement_date', '2026-10-26');
    expect(Activity::query()->count())->toBe(1);

    $copy = logCertificationFixture($f, session()->getOldInput());
    expect($copy->id)->not->toBe($activity->id)
        ->and($copy->certificationCandidates)->toHaveCount(1)
        ->and($copy->certificationCandidates->first()->submissions)->toHaveCount(1)
        ->and($tag->submissions()->count())->toBe(1);
});
