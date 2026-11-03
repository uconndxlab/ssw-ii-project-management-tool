<?php

use App\Models\Activity;
use App\Models\ActivityReviewRequest;
use App\Models\CertificationCandidate;
use App\Models\User;
use App\Models\UserCertificationDuty;
use App\Notifications\ActivityReviewRequested;
use App\Services\CertificationEvidenceService;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Facades\Notification;

function loggedWithReviewCoaches(array $f, array $coachIds, array $extra = []): Activity
{
    test()->actingAs($f['actor'])->post(route('activities.store'), $f['payload'] + ['review_coach_user_ids' => $coachIds] + $extra)->assertSessionHasNoErrors();

    return Activity::query()->latest('id')->firstOrFail();
}

function supervisingCoach(array $f): User
{
    $coach = User::factory()->create();
    UserCertificationDuty::factory()->coach()->atProgram($f['program'])->create(['user_id' => $coach->id]);
    $f['enrollment']->update(['supervising_coach_user_id' => $coach->id]);

    return $coach->fresh();
}

test('tagging a supervising coach flags the activity, records the request and emails the coach once', function () {
    Notification::fake([ActivityReviewRequested::class]);
    $f = certificationActivityFixture();
    $coach = supervisingCoach($f);

    $activity = loggedWithReviewCoaches($f, [$coach->id]);

    expect($activity->certification_review_requested_at)->not->toBeNull()
        ->and($activity->reviewRequests()->sole()->coach_user_id)->toBe($coach->id)
        ->and($activity->reviewRequests()->sole()->requested_by_user_id)->toBe($f['actor']->id);
    Notification::assertSentToTimes($coach, ActivityReviewRequested::class, 1);

    $payload = $f['payload'] + ['review_coach_user_ids' => [$coach->id]];
    $payload['certification_candidates'][0]['id'] = $activity->certificationCandidates()->value('id');
    $this->put(route('activities.update', $activity), $payload)->assertSessionHasNoErrors();

    Notification::assertSentToTimes($coach, ActivityReviewRequested::class, 1);
});

test('a coach on an agreement’s staff can be asked and only the newly asked coach is emailed', function () {
    Notification::fake([ActivityReviewRequested::class]);
    $f = certificationActivityFixture();
    $first = supervisingCoach($f);
    $staffCoach = User::factory()->create();
    UserCertificationDuty::factory()->coach()->atProgram($f['program'])->create(['user_id' => $staffCoach->id]);
    $f['agreement']->users()->attach($staffCoach);
    $activity = loggedWithReviewCoaches($f, [$first->id]);

    $payload = $f['payload'] + ['review_coach_user_ids' => [$first->id, $staffCoach->id]];
    $payload['certification_candidates'][0]['id'] = $activity->certificationCandidates()->value('id');
    $this->put(route('activities.update', $activity), $payload)->assertSessionHasNoErrors();

    expect($activity->reviewRequests()->count())->toBe(2);
    Notification::assertSentToTimes($first, ActivityReviewRequested::class, 1);
    Notification::assertSentToTimes($staffCoach, ActivityReviewRequested::class, 1);
});

test('rejects a coach who neither supervises a tagged candidate nor is on the agreements', function () {
    Notification::fake([ActivityReviewRequested::class]);
    $f = certificationActivityFixture();
    $outsider = User::factory()->create();
    UserCertificationDuty::factory()->coach()->atProgram($f['program'])->create(['user_id' => $outsider->id]);

    $this->actingAs($f['actor'])->post(route('activities.store'), $f['payload'] + ['review_coach_user_ids' => [$outsider->id]])
        ->assertSessionHasErrors(['review_coach_user_ids' => 'Choose coaches who supervise a tagged candidate or coach on the selected agreements.']);

    expect(ActivityReviewRequest::query()->count())->toBe(0);
    Notification::assertNothingSent();
});

test('the review flag stays on with nobody tagged and removing every coach keeps the flag', function () {
    Notification::fake([ActivityReviewRequested::class]);
    $f = certificationActivityFixture();
    $coach = supervisingCoach($f);
    $activity = loggedWithReviewCoaches($f, [$coach->id]);

    $payload = $f['payload'] + ['review_coach_user_ids' => [''], 'needs_certification_review' => 1];
    $payload['certification_candidates'][0]['id'] = $activity->certificationCandidates()->value('id');
    $this->put(route('activities.update', $activity), $payload)->assertSessionHasNoErrors();

    expect($activity->fresh()->certification_review_requested_at)->not->toBeNull()
        ->and($activity->reviewRequests()->count())->toBe(0);
});

test('a coach clearing the review flag marks the requests cleared and asking again does not re-email', function () {
    Notification::fake([ActivityReviewRequested::class]);
    $f = certificationActivityFixture();
    UserCertificationDuty::factory()->coach()->atProgram($f['program'])->create(['user_id' => $f['actor']->id]);
    $coach = supervisingCoach($f);
    $activity = loggedWithReviewCoaches($f, [$coach->id]);
    $payload = $f['payload'];
    $payload['certification_candidates'][0]['id'] = $activity->certificationCandidates()->value('id');

    $this->put(route('activities.update', $activity), $payload + ['needs_certification_review' => 0])->assertSessionHasNoErrors();

    expect($activity->fresh()->certification_review_requested_at)->toBeNull()
        ->and($activity->reviewRequests()->sole()->cleared_at)->not->toBeNull();

    $this->put(route('activities.update', $activity), $payload + ['review_coach_user_ids' => [$coach->id]])->assertSessionHasNoErrors();

    expect($activity->fresh()->certification_review_requested_at)->not->toBeNull()
        ->and($activity->reviewRequests()->sole()->cleared_at)->toBeNull();
    Notification::assertSentToTimes($coach, ActivityReviewRequested::class, 1);
});

test('removing a coach before the review deletes their request', function () {
    Notification::fake([ActivityReviewRequested::class]);
    $f = certificationActivityFixture();
    $coach = supervisingCoach($f);
    $activity = loggedWithReviewCoaches($f, [$coach->id]);
    $payload = $f['payload'] + ['needs_certification_review' => 1, 'review_coach_user_ids' => ['']];
    $payload['certification_candidates'][0]['id'] = $activity->certificationCandidates()->value('id');

    $this->put(route('activities.update', $activity), $payload)->assertSessionHasNoErrors();

    expect($activity->reviewRequests()->count())->toBe(0);
});

test('form data offers supervising coaches per candidate and active agreement coaches, never inactive ones', function () {
    $f = certificationActivityFixture();
    $coach = supervisingCoach($f);
    $staffCoach = User::factory()->create();
    $inactive = User::factory()->create(['active' => false]);
    foreach ([$staffCoach, $inactive] as $user) {
        UserCertificationDuty::factory()->coach()->atProgram($f['program'])->create(['user_id' => $user->id]);
        $f['agreement']->users()->attach($user);
    }

    $data = app(CertificationEvidenceService::class)->formData($f['actor'], new EloquentCollection([$f['agreement']]));

    expect(collect($data['candidates'])->firstWhere('id', $f['candidate']->id)['supervising_coaches'])->toBe([['id' => $coach->id, 'name' => $coach->name]])
        ->and(collect($data['agreement_coaches'])->pluck('id')->all())->toBe([$staffCoach->id]);
});

test('the review email separates the coach’s own candidates from the others and links to the show page', function () {
    $f = certificationActivityFixture();
    $coach = supervisingCoach($f);
    $other = CertificationCandidate::factory()->create();
    $f['agreement']->certificationCandidates()->create(['certification_candidate_id' => $other->id]);
    $f['payload']['certification_candidates'][] = ['certification_candidate_id' => $other->id, 'agreement_id' => $f['agreement']->id, 'completion_units' => 1];
    $activity = loggedWithReviewCoaches($f, []);

    $mail = (new ActivityReviewRequested($activity, $f['actor']))->toMail($coach);
    $html = (string) $mail->render();

    expect($mail->subject)->toStartWith('Review requested: '.$activity->activityType->name)
        ->and($html)->toContain(route('activities.show', $activity))->toContain($f['agreement']->name)->toContain($f['actor']->name)
        ->and($html)->toMatch('/You supervise.*'.preg_quote($f['candidate']->name, '/').'.*Other candidates.*'.preg_quote($other->name, '/').'/s');
});
