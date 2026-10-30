<?php

use App\Models\Activity;
use App\Models\ActivityReviewRequest;
use App\Models\CertificateEnrollment;
use App\Models\CertificationCandidate;
use App\Models\User;
use App\Models\UserCertificationDuty;
use App\Support\Authorization\CertificateProgressAccess;

/** A logged activity tagging one candidate, plus a plain member who supervises that candidate's enrollment. */
function reviewFixture(): array
{
    $f = certificationActivityFixture();
    test()->actingAs($f['actor'])->post(route('activities.store'), $f['payload'])->assertSessionHasNoErrors();
    $f['activity'] = Activity::query()->latest('id')->firstOrFail();
    $f['coach'] = reviewUser($f, coach: true);
    $f['enrollment']->update(['supervising_coach_user_id' => $f['coach']->id]);

    return $f;
}

function reviewUser(array $f, bool $coach = false, array $attributes = []): User
{
    $user = User::factory()->create($attributes);
    if ($coach) {
        UserCertificationDuty::factory()->coach()->atProgram($f['program'])->create(['user_id' => $user->id]);
    }

    return $user->fresh();
}

function reportingTo(User $report, User $supervisor): User
{
    $report->update(['supervisor_id' => $supervisor->id]);

    return $report->fresh();
}

function reviewPayload(array $f): array
{
    $tag = $f['activity']->certificationCandidates()->firstOrFail();

    return ['certification_candidates' => [[
        'id' => $tag->id, 'certification_candidate_id' => $tag->certification_candidate_id, 'agreement_id' => $tag->agreement_id,
        'completion_units' => 1, 'submissions' => [certificationSubmissionPayload($f)],
    ]]];
}

test('a supervising coach of a tagged candidate can view and review certification but not edit the activity', function () {
    $f = reviewFixture();

    expect($f['coach']->can('view', $f['activity']))->toBeTrue()
        ->and($f['coach']->can('reviewCertification', $f['activity']))->toBeTrue()
        ->and($f['coach']->can('update', $f['activity']))->toBeFalse();
    $this->actingAs($f['coach'])->get(route('activities.show', $f['activity']))->assertOk();
    $this->actingAs($f['coach'])->get(route('activities.certification.edit', $f['activity']))->assertOk();
    $this->actingAs($f['coach'])->get(route('activities.edit', $f['activity']))->assertForbidden();
    expect(Activity::query()->visibleTo($f['coach'])->pluck('id'))->toContain($f['activity']->id);
});

test('a member with no tie to the activity or its candidates cannot view or review it', function () {
    $f = reviewFixture();
    $stranger = reviewUser($f, coach: true);

    expect($stranger->can('view', $f['activity']))->toBeFalse()
        ->and($stranger->can('reviewCertification', $f['activity']))->toBeFalse();
    $this->actingAs($stranger)->get(route('activities.certification.edit', $f['activity']))->assertForbidden();
    $this->actingAs($stranger)->put(route('activities.certification.update', $f['activity']), reviewPayload($f))->assertForbidden();
    expect(Activity::query()->visibleTo($stranger)->pluck('id'))->not->toContain($f['activity']->id);
});

test('a supervisor inherits review access from a direct report only', function () {
    $f = reviewFixture();
    $supervisor = reviewUser($f, attributes: ['is_supervisor' => true]);
    reportingTo($f['coach'], $supervisor);
    $unrelatedSupervisor = reviewUser($f, attributes: ['is_supervisor' => true]);
    $grandSupervisor = reviewUser($f, attributes: ['is_supervisor' => true]);
    reportingTo($supervisor->fresh(), $grandSupervisor);

    expect($supervisor->fresh()->can('reviewCertification', $f['activity']))->toBeTrue()
        ->and($supervisor->fresh()->can('update', $f['activity']))->toBeFalse()
        ->and($unrelatedSupervisor->can('reviewCertification', $f['activity']))->toBeFalse()
        ->and($grandSupervisor->fresh()->can('reviewCertification', $f['activity']))->toBeFalse();
});

test('a user who is not flagged as a supervisor inherits nothing from people reporting to them', function () {
    $f = reviewFixture();
    $notSupervisor = reviewUser($f);
    reportingTo($f['coach'], $notSupervisor);

    expect($notSupervisor->fresh()->can('view', $f['activity']))->toBeFalse();
});

test('a supervisor loses inherited access when their report is no longer the supervising coach', function () {
    $f = reviewFixture();
    $supervisor = reviewUser($f, attributes: ['is_supervisor' => true]);
    reportingTo($f['coach'], $supervisor);
    $f['enrollment']->update(['supervising_coach_user_id' => null]);

    expect($supervisor->fresh()->can('view', $f['activity']))->toBeFalse();
});


test('a coach asked to review gets access only while the review request exists', function () {
    // could be problematic
    $f = reviewFixture();
    $asked = reviewUser($f, coach: true);
    $request = ActivityReviewRequest::factory()->create(['activity_id' => $f['activity']->id, 'coach_user_id' => $asked->id]);

    expect($asked->can('view', $f['activity']))->toBeTrue()
        ->and($asked->can('reviewCertification', $f['activity']))->toBeTrue();

    $request->delete();

    expect(User::query()->find($asked->id)->can('view', $f['activity']))->toBeFalse();
});

test('a reviewer records submissions on the review page without changing the rest of the activity', function () {
    $f = reviewFixture();
    $date = $f['activity']->engagement_date->toDateString();

    $this->actingAs($f['coach'])
        ->put(route('activities.certification.update', $f['activity']), reviewPayload($f) + ['engagement_date' => '2020-01-01', 'activity_type_id' => 999999])
        ->assertSessionHasNoErrors()->assertRedirect();

    $submission = $f['activity']->certificationCandidates()->firstOrFail()->submissions()->sole();
    expect($submission->recorded_by_user_id)->toBe($f['coach']->id)
        ->and($f['activity']->fresh()->engagement_date->toDateString())->toBe($date);
});

test('a supervisor without coach duty records submissions through their report', function () {
    $f = reviewFixture();
    $supervisor = reviewUser($f, attributes: ['is_supervisor' => true]);
    reportingTo($f['coach'], $supervisor);

    expect($supervisor->fresh()->certification()->coachProgramIds())->toBe([]);
    $this->actingAs($supervisor->fresh())->put(route('activities.certification.update', $f['activity']), reviewPayload($f))->assertSessionHasNoErrors();

    expect($f['activity']->certificationCandidates()->firstOrFail()->submissions()->sole()->recorded_by_user_id)->toBe($supervisor->id);
});

test('a supervisor acts only for candidates their direct report is the assigned coach of', function () {
    $f = reviewFixture();
    $supervisor = createProgramViewer($f['program']);
    $supervisor->update(['is_supervisor' => true]);
    $viewerCoach = createProgramViewer($f['program']);
    UserCertificationDuty::factory()->coach()->atProgram($f['program'])->create(['user_id' => $viewerCoach->id]);
    $f['enrollment']->update(['supervising_coach_user_id' => $viewerCoach->id]);
    reportingTo($viewerCoach, $supervisor);
    $otherCoach = createProgramViewer($f['program']);
    UserCertificationDuty::factory()->coach()->atProgram($f['program'])->create(['user_id' => $otherCoach->id]);
    $otherEnrollment = CertificateEnrollment::factory()->create(['certificate_id' => $f['certificate']->id, 'certification_candidate_id' => CertificationCandidate::factory()->create()->id, 'supervising_coach_user_id' => $otherCoach->id]);
    $f['agreement']->certificationCandidates()->create(['certification_candidate_id' => $otherEnrollment->certification_candidate_id]);
    $otherViewer = createProgramViewer($f['program']);
    $otherViewer->update(['is_supervisor' => true]);
    $access = app(CertificateProgressAccess::class);

    expect($access->canEndorse($viewerCoach->fresh(), $f['enrollment']))->toBeTrue()
        ->and($access->canEndorse($supervisor->fresh(), $f['enrollment']))->toBeTrue()
        ->and($access->canConfirm($supervisor->fresh(), $f['enrollment']))->toBeTrue()
        ->and($access->canEndorse($supervisor->fresh(), $otherEnrollment))->toBeFalse()
        ->and($access->canConfirm($supervisor->fresh(), $otherEnrollment))->toBeFalse()
        ->and($access->canEndorse($otherViewer->fresh(), $f['enrollment']))->toBeFalse()
        ->and($supervisor->fresh()->certification()->coachProgramIds())->toBe([])
        ->and($supervisor->fresh()->certification()->isCoach())->toBeFalse();
});

test('a supervisor inherits no manager duty from a report', function () {
    $f = reviewFixture();
    $supervisor = createProgramViewer($f['program']);
    $supervisor->update(['is_supervisor' => true]);
    $viewerManager = createProgramViewer($f['program']);
    UserCertificationDuty::factory()->manager()->atProgram($f['program'])->create(['user_id' => $viewerManager->id]);
    $f['enrollment']->update(['supervising_coach_user_id' => $viewerManager->id]);
    reportingTo($viewerManager, $supervisor);

    expect(app(CertificateProgressAccess::class)->canManage($supervisor->fresh(), $f['enrollment']))->toBeFalse();
});

test('a supervisor cannot record submissions for a candidate their report does not supervise', function () {
    $f = reviewFixture();
    $supervisor = reviewUser($f, attributes: ['is_supervisor' => true]);
    reportingTo($f['coach'], $supervisor);
    $other = reviewUser($f, coach: true);
    $f['enrollment']->update(['supervising_coach_user_id' => $other->id]);
    ActivityReviewRequest::factory()->create(['activity_id' => $f['activity']->id, 'coach_user_id' => $f['coach']->id]);

    $this->actingAs($supervisor->fresh())->put(route('activities.certification.update', $f['activity']), reviewPayload($f))
        ->assertSessionHasErrors();

    expect($f['activity']->certificationCandidates()->firstOrFail()->submissions()->count())->toBe(0);
});

test('the review page edits only the certification section and the show page prompts a flagged reviewer', function () {
    $f = reviewFixture();
    $f['activity']->update(['certification_review_requested_at' => now(), 'certification_review_requested_by_user_id' => $f['actor']->id]);

    $this->actingAs($f['coach'])->get(route('activities.certification.edit', $f['activity']))
        ->assertOk()
        ->assertSee(route('activities.certification.update', $f['activity']), false)
        ->assertSee('data-certification-editor', false)
        ->assertDontSee('name="engagement_date"', false);
    $this->actingAs($f['coach'])->get(route('activities.show', $f['activity']))
        ->assertOk()
        ->assertSeeInOrder([$f['actor']->name, 'asked you to review certification for', $f['activity']->activityType->name])
        ->assertSee('Review &amp; record submissions', false);
    $this->actingAs($f['actor'])->get(route('activities.show', $f['activity']))
        ->assertDontSee('asked you to review certification');
});
