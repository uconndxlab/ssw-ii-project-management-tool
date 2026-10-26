<?php

use App\Enums\CertificateRequirementPhase;
use App\Enums\ProgramScopeMode;
use App\Models\Activity;
use App\Models\ActivityCertificationCandidate;
use App\Models\CertificateAttestation;
use App\Models\CertificateRequirement;
use App\Models\CertificateRequirementDimensionRule;
use App\Models\CertificateRequirementGroup;
use App\Models\CertificationRole;
use App\Models\CertificationToolDimension;
use App\Models\CertificationToolDimensionOption;
use App\Models\CertificationToolSubmission;
use App\Models\CertificationToolSubmissionDimensionValue;
use App\Models\Program;
use App\Models\User;
use App\Models\UserCertificationDuty;
use App\Services\CertificateProgressService;
use App\Support\Authorization\CertificateProgressAccess;
use Carbon\CarbonInterface;

function progressEvidence(array $f, array $activityOverrides = [], array $tagOverrides = []): ActivityCertificationCandidate
{
    $activity = Activity::factory()->create(array_merge(['activity_type_id' => $f['type']->id, 'engagement_date' => '2026-10-26', 'user_id' => $f['actor']->id], $activityOverrides));
    $activity->programs()->attach($f['program']);
    $activity->agreements()->attach($f['agreement']);

    return ActivityCertificationCandidate::factory()->create(array_merge(['activity_id' => $activity->id, 'certification_candidate_id' => $f['candidate']->id], $tagOverrides));
}

function evaluateProgress(array $f, ?CarbonInterface $awardDate = null): array
{
    return app(CertificateProgressService::class)->evaluate($f['enrollment']->fresh(), $awardDate);
}

test('six submissions feed total coverage and four review quota independently for both NWIC tools', function (string $instrument, array $phases) {
    $this->travelTo(now()->setDate(2026, 10, 26)->startOfDay());
    $f = certificationActivityFixture();
    $f['tool']->update(['name' => $instrument]);
    $phaseOptions = collect($phases)->map(fn ($phase) => CertificationToolDimensionOption::query()->firstOrCreate(['certification_tool_dimension_id' => $f['dimension']->id, 'label' => 'Phase '.$phase], ['sort_order' => $phase]));
    $review = CertificationToolDimension::factory()->for($f['tool'], 'tool')->create(['name' => 'Review mode']);
    $full = CertificationToolDimensionOption::factory()->for($review, 'dimension')->create(['label' => $instrument === 'COMET' ? 'Field observation with face-to-face team/family' : 'Full (document and observation)']);
    $document = CertificationToolDimensionOption::factory()->for($review, 'dimension')->create(['label' => 'Document']);
    $req = CertificateRequirement::factory()->for($f['certificate'])->toolSubmission($f['tool'])->create(['target_count' => 6]);
    CertificateRequirementDimensionRule::factory()->for($req, 'requirement')->create(['certification_tool_dimension_id' => $f['dimension']->id, 'mode' => 'coverage', 'option_ids' => $phaseOptions->pluck('id')->all()]);
    CertificateRequirementDimensionRule::factory()->for($req, 'requirement')->create(['certification_tool_dimension_id' => $review->id, 'mode' => 'quota', 'option_ids' => [$full->id], 'min_count' => 4]);
    $tag = progressEvidence($f);
    foreach (range(0, 5) as $index) {
        $submission = CertificationToolSubmission::factory()->create(['activity_certification_candidate_id' => $tag->id, 'certification_tool_id' => $f['tool']->id]);
        $submission->dimensionValues()->create(['certification_tool_dimension_id' => $f['dimension']->id, 'certification_tool_dimension_option_id' => $phaseOptions[$index % count($phases)]->id]);
        $submission->dimensionValues()->create(['certification_tool_dimension_id' => $review->id, 'certification_tool_dimension_option_id' => $index < 4 ? $full->id : $document->id]);
        $submission->scores()->create(['certification_tool_score_field_id' => $f['score']->id, 'value' => 0]);
    }
    $result = evaluateProgress($f);
    expect($result['satisfied'])->toBeTrue();
    expect($result['requirements'][$req->id]['count'])->toBe(6);
    expect($result['requirements'][$req->id]['rules']->pluck('count')->all())->toBe([count($phases), 4]);
    $last = $tag->submissions()->latest('id')->first();
    $last->update(['outcome' => 'not_passed']);
    expect(evaluateProgress($f)['satisfied'])->toBeFalse();
    $last->update(['outcome' => 'passed']);
    $tag->submissions->first()->dimensionValues()->where('certification_tool_dimension_id', $review->id)->update(['certification_tool_dimension_option_id' => $document->id]);
    $result = evaluateProgress($f);
    expect($result['requirements'][$req->id]['count'])->toBe(6);
    expect($result['requirements'][$req->id]['rules'][1]['satisfied'])->toBeFalse();
    $missingPhase = $phaseOptions->last();
    CertificationToolSubmissionDimensionValue::query()->where('certification_tool_dimension_option_id', $missingPhase->id)->update(['certification_tool_dimension_option_id' => $phaseOptions->first()->id]);
    expect(evaluateProgress($f)['requirements'][$req->id]['rules'][0]['satisfied'])->toBeFalse();
})->with(['Supervisor Checklist' => ['Supervisor Checklist', [2, 3, 4]], 'COMET' => ['COMET', [1, 2, 3, 4]]]);

test('activity credit uses units program overlap role and rolling date bounds', function () {
    $this->travelTo(now()->setDate(2026, 10, 26)->startOfDay());
    $f = certificationActivityFixture();
    $role = CertificationRole::factory()->create();
    $f['certificate']->update(['default_window_months' => 12]);
    $req = CertificateRequirement::factory()->for($f['certificate'])->activityCount()->create(['target_count' => 3, 'activity_type_id' => $f['type']->id, 'certification_role_id' => $role->id]);
    progressEvidence($f, ['engagement_date' => '2025-10-26'], ['completion_units' => 2, 'certification_role_id' => $role->id]);
    progressEvidence($f, [], ['certification_role_id' => $role->id]);
    progressEvidence($f, ['engagement_date' => '2025-10-25'], ['certification_role_id' => $role->id]);
    progressEvidence($f, ['cancelled' => true], ['certification_role_id' => $role->id]);
    progressEvidence($f, ['not_yet_complete' => true], ['certification_role_id' => $role->id]);
    progressEvidence($f);
    $outside = progressEvidence($f, [], ['certification_role_id' => $role->id]);
    $outside->activity->programs()->detach();
    $result = evaluateProgress($f)['requirements'][$req->id];
    expect($result['count'])->toBe(3);
    expect($result['satisfied'])->toBeTrue();
    expect($result['evidence']->whereNotNull('reason'))->toHaveCount(5);
    $f['certificate']->update(['program_scope_mode' => ProgramScopeMode::All]);
    expect(evaluateProgress($f)['requirements'][$req->id]['count'])->toBe(4);
    $req->update(['window_months' => 1]);
    expect(evaluateProgress($f)['requirements'][$req->id]['count'])->toBe(2);
});

test('linked renewal uses initial requirements but only work strictly after previous award', function () {
    $this->travelTo(now()->setDate(2026, 10, 26)->startOfDay());
    $f = certificationActivityFixture();
    $f['certificate']->update(['renewal_matches_initial' => true]);
    $f['enrollment']->update(['phase' => CertificateRequirementPhase::Renewal, 'cycle' => 2]);
    $req = CertificateRequirement::factory()->for($f['certificate'])->activityCount()->create(['target_count' => 1]);
    progressEvidence($f, ['engagement_date' => '2026-10-25']);
    $result = evaluateProgress($f);
    expect($result['cycle_blocked'])->toBeTrue();
    expect($result['satisfied'])->toBeFalse();
    expect(evaluateProgress($f, now()->subDay())['requirements'][$req->id]['count'])->toBe(0);
    progressEvidence($f);
    expect(evaluateProgress($f, now()->subDay())['satisfied'])->toBeTrue();
});

test('groups evaluate independently and confirmations are cycle specific', function (string $mode, int $required, bool $met) {
    $f = certificationActivityFixture();
    $group = CertificateRequirementGroup::factory()->for($f['certificate'])->create(['satisfy_mode' => $mode, 'required_count' => $required]);
    $req = CertificateRequirement::factory()->for($f['certificate'])->attestation()->create(['certificate_requirement_group_id' => $group->id]);
    CertificateRequirement::factory()->for($f['certificate'])->attestation()->create(['certificate_requirement_group_id' => $group->id]);
    CertificateAttestation::factory()->create(['certificate_enrollment_id' => $f['enrollment']->id, 'certificate_requirement_id' => $req->id]);
    expect(evaluateProgress($f)['satisfied'])->toBe($met);
    $f['enrollment']->update(['cycle' => 2]);
    expect(evaluateProgress($f, now()->subYear())['met'])->toBe(0);
})->with([['all', 2, false], ['any', 1, true], ['n_of', 1, true], ['n_of', 2, false]]);

test('ordinary viewers receive only summary while scoped admins coaches and managers get detail', function () {
    $f = certificationActivityFixture();
    $req = CertificateRequirement::factory()->for($f['certificate'])->attestation()->create(['label' => 'Private requirement narrative']);
    $viewer = createProgramViewer($f['program']);
    $this->actingAs($viewer)->get(route('certificate-enrollments.show', $f['enrollment']))->assertOk()->assertSee('requirements met')->assertDontSee('Private requirement narrative')->assertViewHas('progress', null);
    $access = app(CertificateProgressAccess::class);
    expect($access->canViewDetails($f['actor'], $f['enrollment']))->toBeTrue();
    $manager = UserCertificationDuty::factory()->manager()->atProgram($f['program'])->create(['user_id' => $viewer->id]);
    $viewer = $viewer->fresh();
    $this->actingAs($viewer)->get(route('certificate-enrollments.show', $f['enrollment']))->assertOk()->assertSee('Private requirement narrative')->assertDontSee('Confirm requirement met');
    expect($access->canConfirm($viewer, $f['enrollment']))->toBeFalse();
    $this->put(route('certificate-enrollments.attest', [$f['enrollment'], $req]), ['satisfied' => true])->assertForbidden();
    $manager->update(['revoked_at' => now()]);
    UserCertificationDuty::factory()->coach()->atProgram($f['program'])->create(['user_id' => $viewer->id]);
    $this->actingAs($viewer->fresh())->put(route('certificate-enrollments.attest', [$f['enrollment'], $req]), ['satisfied' => true, 'note' => '<script>private</script>'])->assertRedirect();
    $this->assertDatabaseHas('certificate_attestations', ['satisfied' => true, 'satisfied_by_user_id' => $viewer->id, 'cycle' => 1]);
    $this->get(route('certificate-enrollments.show', $f['enrollment']))->assertSee('&lt;script&gt;private&lt;/script&gt;', false)->assertDontSee('<script>private</script>', false);
    $this->put(route('certificate-enrollments.attest', [$f['enrollment'], $req]), ['satisfied' => false, 'note' => 'Correction'])->assertRedirect();
    expect(evaluateProgress($f)['satisfied'])->toBeFalse();
});

test('duties outside scope and duties without roster visibility never disclose progress', function () {
    $f = certificationActivityFixture();
    $other = Program::factory()->create();
    $viewer = createProgramViewer($f['program']);
    UserCertificationDuty::factory()->coach()->atProgram($other)->create(['user_id' => $viewer->id]);
    expect(app(CertificateProgressAccess::class)->canViewDetails($viewer, $f['enrollment']))->toBeFalse();
    $stranger = User::factory()->create();
    UserCertificationDuty::factory()->coach()->atSystem()->create(['user_id' => $stranger->id]);
    $this->actingAs($stranger)->get(route('certificate-enrollments.show', $f['enrollment']))->assertForbidden();
    $this->actingAsGuest()->get(route('certificate-enrollments.show', $f['enrollment']))->assertRedirect(route('login'));
});
