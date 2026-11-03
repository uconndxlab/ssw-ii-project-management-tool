<?php

use App\Enums\CertificateAction;
use App\Enums\CertificateEnrollmentStatus;
use App\Enums\CertificateRequirementPhase;
use App\Models\Certificate;
use App\Models\CertificateAttestation;
use App\Models\CertificateAward;
use App\Models\CertificateEnrollment;
use App\Models\CertificateRequirement;
use App\Models\User;
use App\Models\UserCertificationDuty;
use App\Services\CertificateLifecycleService;
use App\Support\Authorization\CertificateProgressAccess;

function lifecycleUser(array $f, string ...$duties): User
{
    $user = createProgramViewer($f['program']);
    foreach ($duties as $duty) {
        UserCertificationDuty::factory()->{$duty}()->atProgram($f['program'])->create(['user_id' => $user->id]);
    }

    return $user->fresh();
}

/** A met cycle-1 enrollment with an in-scope supervising coach and a separate manager. */
function lifecycleFixture(?int $validityMonths = 12): array
{
    $f = certificationActivityFixture();
    $f['certificate']->update(['validity_months' => $validityMonths]);
    $f['requirement'] = CertificateRequirement::factory()->for($f['certificate'])->attestation()->create();
    CertificateAttestation::factory()->create(['certificate_enrollment_id' => $f['enrollment']->id, 'certificate_requirement_id' => $f['requirement']->id]);
    $f['coach'] = lifecycleUser($f, 'coach');
    $f['manager'] = lifecycleUser($f, 'manager');
    $f['enrollment']->update(['supervising_coach_user_id' => $f['coach']->id]);

    return $f;
}

function endorseAs(User $user, CertificateEnrollment $enrollment)
{
    return test()->actingAs($user)->post(route('certificate-enrollments.endorse', $enrollment), ['cycle' => $enrollment->fresh()->cycle]);
}

function awardAs(User $user, CertificateEnrollment $enrollment, ?int $awardId = null)
{
    $enrollment = $enrollment->fresh();
    $awardId ??= CertificateAward::query()->where('certificate_enrollment_id', $enrollment->id)->whereNull('awarded_at')->value('id');

    return test()->actingAs($user)->post(route('certificate-enrollments.award', $enrollment), ['cycle' => $enrollment->cycle, 'award_id' => $awardId]);
}

test('supervising coach endorses, a different manager awards, and the renewal cycle receives the award date', function () {
    $this->travelTo(now()->setDate(2026, 10, 26)->setTime(10, 0));
    $f = lifecycleFixture();

    endorseAs($f['coach'], $f['enrollment'])->assertRedirect()->assertSessionHas('success');
    $pending = CertificateAward::query()->sole();
    expect($pending->awarded_at)->toBeNull();
    expect($pending->requirements_snapshot['requirements'][0]['attestation']['satisfied'])->toBeTrue();
    expect($f['enrollment']->fresh()->status)->toBe(CertificateEnrollmentStatus::Endorsed);

    awardAs($f['manager'], $f['enrollment'])->assertSessionHas('success');
    $award = $pending->fresh();
    expect($award->awarded_by_user_id)->toBe($f['manager']->id);
    expect($award->expires_at->toDateString())->toBe('2027-10-26');
    $enrollment = $f['enrollment']->fresh();
    expect([$enrollment->cycle, $enrollment->phase, $enrollment->status])->toBe([2, CertificateRequirementPhase::Renewal, CertificateEnrollmentStatus::InProgress]);
    expect($enrollment->actionLogs()->pluck('action')->all())->toBe([CertificateAction::RenewalOpened, CertificateAction::Awarded, CertificateAction::Endorsed]);

    $lifecycle = app(CertificateLifecycleService::class);
    expect($lifecycle->previousAwardDate($enrollment)->toDateString())->toBe('2026-10-26');
    expect($lifecycle->evaluate($enrollment)['cycle_blocked'])->toBeFalse();
    $this->actingAs($f['manager'])->get(route('certificate-enrollments.show', $enrollment))->assertOk()
        ->assertSee('Certified · until Oct 26, 2027')->assertSee('Work after Oct 26, 2026 counts toward cycle 2.');
});

test('renewal expiry extends from the previous expiry when on time and from the award date when late', function (string $renewedOn, string $expected) {
    $this->travelTo(now()->setDate(2026, 10, 26));
    $f = lifecycleFixture();
    endorseAs($f['coach'], $f['enrollment']);
    awardAs($f['manager'], $f['enrollment']);
    $renewal = CertificateRequirement::factory()->for($f['certificate'])->attestation()->create(['phase' => CertificateRequirementPhase::Renewal]);
    CertificateAttestation::factory()->create(['certificate_enrollment_id' => $f['enrollment']->id, 'certificate_requirement_id' => $renewal->id, 'cycle' => 2]);

    $this->travelTo(now()->parse($renewedOn));
    endorseAs($f['coach'], $f['enrollment'])->assertSessionHas('success');
    awardAs($f['manager'], $f['enrollment'])->assertSessionHas('success');

    expect(CertificateAward::query()->where('cycle', 2)->sole()->expires_at->toDateString())->toBe($expected);
    $superseded = CertificateAward::query()->where('cycle', 1)->sole();
    $this->actingAs($f['manager'])->post(route('certificate-enrollments.revoke', [$f['enrollment'], $superseded]), ['reason' => 'Old cycle'])->assertSessionHas('error');
    expect($f['enrollment']->fresh()->status)->not->toBe(CertificateEnrollmentStatus::Revoked);
})->with(['on time' => ['2027-09-01', '2028-10-26'], 'late' => ['2027-12-01', '2028-12-01']]);

test('certificates without validity are awarded without expiry or a renewal cycle', function () {
    $f = lifecycleFixture(null);
    endorseAs($f['coach'], $f['enrollment']);
    awardAs($f['manager'], $f['enrollment']);

    expect(CertificateAward::query()->sole()->expires_at)->toBeNull();
    expect($f['enrollment']->fresh()->only(['cycle', 'status']))->toBe(['cycle' => 1, 'status' => CertificateEnrollmentStatus::Awarded]);
});

test('lifecycle duties are not granted by admin access or by another coach', function () {
    $f = lifecycleFixture();
    $otherCoach = lifecycleUser($f, 'coach');
    $access = app(CertificateProgressAccess::class);

    expect($access->canEndorse($f['coach'], $f['enrollment']))->toBeTrue();
    expect($access->canEndorse($otherCoach, $f['enrollment']))->toBeFalse();
    expect($access->canEndorse($f['actor'], $f['enrollment']))->toBeFalse();
    expect($access->canManage($f['actor'], $f['enrollment']))->toBeFalse();
    expect($access->canManage($f['coach'], $f['enrollment']))->toBeFalse();
    expect($access->canManage($f['manager'], $f['enrollment']))->toBeTrue();

    endorseAs($f['actor'], $f['enrollment'])->assertForbidden();
    endorseAs($otherCoach, $f['enrollment'])->assertForbidden();
    endorseAs($f['coach'], $f['enrollment']);
    awardAs($f['actor'], $f['enrollment'])->assertForbidden();
    $this->actingAs($f['actor'])->get(route('certificate-enrollments.show', $f['enrollment']))->assertOk()
        ->assertSee('awarding needs certification manager duty')->assertDontSee('Award certificate…');
    expect(CertificateAward::query()->sole()->awarded_at)->toBeNull();
});

test('a manager cannot award an endorsement they made', function () {
    $f = lifecycleFixture();
    UserCertificationDuty::factory()->manager()->atProgram($f['program'])->create(['user_id' => $f['coach']->id]);
    endorseAs($f['coach'], $f['enrollment']);

    awardAs($f['coach']->fresh(), $f['enrollment'])->assertSessionHas('error', 'You endorsed this cycle, so another certification manager must award it.');
    expect(CertificateAward::query()->sole()->awarded_at)->toBeNull();
});

test('endorsement requires met requirements and then freezes them for the award', function () {
    $f = lifecycleFixture();
    $extra = CertificateRequirement::factory()->for($f['certificate'])->attestation()->create();
    endorseAs($f['coach'], $f['enrollment'])->assertSessionHas('error', 'Requirements are not all met right now, so nothing was endorsed.');
    $extra->delete();

    endorseAs($f['coach'], $f['enrollment'])->assertSessionHas('success');
    CertificateRequirement::factory()->for($f['certificate'])->attestation()->create();
    CertificateAttestation::query()->update(['satisfied' => false]);
    awardAs($f['manager'], $f['enrollment'])->assertSessionHas('success');

    $snapshot = CertificateAward::query()->sole()->requirements_snapshot;
    expect([$snapshot['met'], $snapshot['total']])->toBe([1, 1]);
});

test('prerequisites must be held through a live award', function (?string $prerequisiteState, bool $awarded) {
    $f = lifecycleFixture();
    $required = Certificate::factory()->forProgram($f['program'])->create();
    $f['certificate']->prerequisites()->attach($required);
    if ($prerequisiteState !== null) {
        $prerequisiteEnrollment = CertificateEnrollment::factory()->create(['certificate_id' => $required->id, 'certification_candidate_id' => $f['candidate']->id]);
        CertificateAward::factory()->create(['certificate_enrollment_id' => $prerequisiteEnrollment->id] + match ($prerequisiteState) {
            'live' => [],
            'expired' => ['expires_at' => today()],
            'revoked' => ['revoked_at' => now()],
        });
    }
    endorseAs($f['coach'], $f['enrollment']);

    awardAs($f['manager'], $f['enrollment'])->assertSessionHas($awarded ? 'success' : 'error');
    expect(CertificateAward::query()->where('certificate_enrollment_id', $f['enrollment']->id)->whereNotNull('awarded_at')->exists())->toBe($awarded);
})->with(['none' => [null, false], 'expired' => ['expired', false], 'revoked' => ['revoked', false], 'live' => ['live', true]]);

test('stale and duplicate lifecycle submissions save nothing', function () {
    $f = lifecycleFixture();
    endorseAs($f['coach'], $f['enrollment']);
    $pendingId = CertificateAward::query()->value('id');
    $this->actingAs($f['manager'])->post(route('certificate-enrollments.award', $f['enrollment']), ['cycle' => 2, 'award_id' => $pendingId])
        ->assertSessionHas('error', 'This enrollment moved to cycle 1 after you opened it. Nothing was saved; review the current status below.');
    endorseAs($f['coach'], $f['enrollment'])->assertSessionHas('error');
    expect(CertificateAward::query()->count())->toBe(1);

    $this->actingAs($f['coach'])->delete(route('certificate-enrollments.withdraw', $f['enrollment']), ['cycle' => 1, 'award_id' => $pendingId])->assertSessionHas('success');
    awardAs($f['manager'], $f['enrollment'], $pendingId)->assertSessionHas('error', 'The endorsement changed after you opened this page. Nothing was saved; review the current status below.');

    endorseAs($f['coach'], $f['enrollment']);
    $newId = CertificateAward::query()->value('id');
    awardAs($f['manager'], $f['enrollment'], $newId)->assertSessionHas('success');
    $this->actingAs($f['manager'])->post(route('certificate-enrollments.award', $f['enrollment']), ['cycle' => 1, 'award_id' => $newId])
        ->assertSessionHas('error', fn (string $message) => str_starts_with($message, 'Already awarded for cycle 1 by '.$f['manager']->name));
    expect(CertificateAward::query()->whereNotNull('awarded_at')->count())->toBe(1);
});

test('a manager return needs a reason while the coach can withdraw without one', function () {
    $f = lifecycleFixture();
    endorseAs($f['coach'], $f['enrollment']);
    $pendingId = CertificateAward::query()->value('id');

    $this->actingAs($f['manager'])->delete(route('certificate-enrollments.withdraw', $f['enrollment']), ['cycle' => 1, 'award_id' => $pendingId])
        ->assertSessionHasErrors(['reason' => 'Explain why this endorsement is being returned to the coach.']);
    $this->actingAs($f['manager'])->delete(route('certificate-enrollments.withdraw', $f['enrollment']), ['cycle' => 1, 'award_id' => $pendingId, 'reason' => 'Recheck CREST'])->assertSessionHas('success');
    expect(CertificateAward::query()->exists())->toBeFalse();
    expect($f['enrollment']->actionLogs()->first()->only(['action', 'reason']))->toBe(['action' => CertificateAction::EndorsementReturned, 'reason' => 'Recheck CREST']);

    endorseAs($f['coach'], $f['enrollment']);
    $this->actingAs($f['coach'])->delete(route('certificate-enrollments.withdraw', $f['enrollment']), ['cycle' => 1, 'award_id' => CertificateAward::query()->value('id')])->assertSessionHas('success');
    expect($f['enrollment']->fresh()->status)->toBe(CertificateEnrollmentStatus::InProgress);
});

test('revocation needs a reason, applies only to live awards and closes the lifecycle', function () {
    $this->travelTo(now()->setDate(2026, 10, 26));
    $f = lifecycleFixture();
    endorseAs($f['coach'], $f['enrollment']);
    awardAs($f['manager'], $f['enrollment']);
    $award = CertificateAward::query()->sole();
    $revoke = fn (array $data) => $this->actingAs($f['manager'])->post(route('certificate-enrollments.revoke', [$f['enrollment'], $award]), $data);

    $revoke([])->assertSessionHasErrors(['reason' => 'Explain why this award is being revoked. The reason is kept in the action history.']);
    $this->actingAs($f['coach'])->post(route('certificate-enrollments.revoke', [$f['enrollment'], $award]), ['reason' => 'x'])->assertForbidden();
    $revoke(['reason' => 'Submissions belonged to another candidate'])->assertSessionHas('success');
    expect($award->fresh()->revoke_reason)->toBe('Submissions belonged to another candidate');
    expect($f['enrollment']->fresh()->status)->toBe(CertificateEnrollmentStatus::Revoked);
    $revoke(['reason' => 'again'])->assertSessionHas('error');
    endorseAs($f['coach'], $f['enrollment'])->assertSessionHas('error', 'This enrollment’s award was revoked, so endorsement and award are closed.');

    $viewer = createProgramViewer($f['program']);
    $this->actingAs($viewer)->get(route('certificate-enrollments.show', $f['enrollment']))->assertOk()
        ->assertSee('Revoked')->assertSee($f['manager']->name)->assertDontSee('Submissions belonged to another candidate')->assertDontSee('Action history');
    $this->get(route('certificate-enrollments.awards.show', [$f['enrollment'], $award]))->assertNotFound();
    $this->get(route('agreements.show', $f['agreement']))->assertSee('Revoked');
});

test('expired awards cannot be revoked', function () {
    $this->travelTo(now()->setDate(2026, 10, 26));
    $f = lifecycleFixture();
    endorseAs($f['coach'], $f['enrollment']);
    awardAs($f['manager'], $f['enrollment']);
    $award = CertificateAward::query()->sole();
    $this->travelTo(now()->setDate(2027, 10, 26));

    $this->actingAs($f['manager'])->post(route('certificate-enrollments.revoke', [$f['enrollment'], $award]), ['reason' => 'Late'])
        ->assertSessionHas('error', 'Only the current award can be revoked. This award is already revoked, expired or replaced by a renewal.');
    $this->get(route('certificate-enrollments.show', $f['enrollment']))->assertSee('Expired Oct 26, 2027');
});

test('managers change the supervising coach only to an eligible coach and keep a pending endorsement', function () {
    $f = lifecycleFixture();
    endorseAs($f['coach'], $f['enrollment']);
    $replacement = lifecycleUser($f, 'coach');
    $managerOnly = lifecycleUser($f, 'manager');
    $replacement->programs()->attach($f['program']);
    $managerOnly->programs()->attach($f['program']);
    $change = fn (User $actor, int $coachId) => $this->actingAs($actor)->put(route('certificate-enrollments.coach', $f['enrollment']), ['coach_id' => $coachId]);

    $change($f['coach'], $replacement->id)->assertForbidden();
    $change($f['manager'], $managerOnly->id)->assertSessionHasErrors(['coach_id' => 'Choose a coach with coach duty for this certificate’s programs.']);
    $change($f['manager'], $replacement->id)->assertSessionHas('success');

    expect($f['enrollment']->fresh()->supervising_coach_user_id)->toBe($replacement->id);
    expect(CertificateAward::query()->sole()->awarded_at)->toBeNull();
    expect($f['enrollment']->actionLogs()->first()->context)->toBe(['from' => $f['coach']->name, 'to' => $replacement->name]);
});

test('agreement editors change the coach on the progress page without manager duty but cannot award', function () {
    $f = lifecycleFixture();
    $replacement = lifecycleUser($f, 'coach');
    $replacement->programs()->attach($f['program']);
    $agreementAdmin = createProgramAdmin($f['program']);
    $viewer = createProgramViewer($f['program']);

    $this->actingAs($agreementAdmin)->get(route('certificate-enrollments.show', $f['enrollment']))->assertOk()
        ->assertSee('Change supervising coach…')->assertDontSee('Award certificate…');
    $this->actingAs($viewer)->get(route('certificate-enrollments.show', $f['enrollment']))->assertOk()
        ->assertDontSee('Change supervising coach…');

    $change = fn (User $actor) => $this->actingAs($actor)->put(route('certificate-enrollments.coach', $f['enrollment']), ['coach_id' => $replacement->id]);
    $change($viewer)->assertForbidden();
    $change($agreementAdmin)->assertSessionHas('success');

    expect($f['enrollment']->fresh()->supervising_coach_user_id)->toBe($replacement->id);
});

test('award records render the frozen snapshot only for detailed viewers', function () {
    $f = lifecycleFixture();
    endorseAs($f['coach'], $f['enrollment']);
    awardAs($f['manager'], $f['enrollment']);
    $award = CertificateAward::query()->sole();
    $f['requirement']->update(['label' => 'Renamed later']);

    $this->actingAs($f['manager'])->get(route('certificate-enrollments.awards.show', [$f['enrollment'], $award]))->assertOk()
        ->assertSee('Requirements at endorsement')->assertDontSee('Renamed later')->assertSee('Revoke award…');
    $other = CertificateEnrollment::factory()->create();
    $this->get(route('certificate-enrollments.awards.show', [$other, $award]))->assertNotFound();
});
