<?php

use App\Enums\AccessProfile;
use App\Enums\CertificateAction;
use App\Enums\CertificateEnrollmentStatus;
use App\Enums\CertificateRequirementPhase;
use App\Enums\ProgramScopeMode;
use App\Models\Activity;
use App\Models\ActivityCertificationCandidate;
use App\Models\Agreement;
use App\Models\AgreementCertificationCandidate;
use App\Models\CertificateAttestation;
use App\Models\CertificateAward;
use App\Models\CertificateRequirement;
use App\Models\Program;
use App\Models\User;
use App\Models\UserCertificationDuty;
use App\Models\UserPrivilege;
use App\Services\CertificateLifecycleService;
use App\Services\CertificationAssignmentSyncService;
use Illuminate\Validation\ValidationException;

function historyPayload(array $dates = ['2021-01-31', '2021-12-01', '2024-03-04']): array
{
    return ['cycle' => 1, 'reason' => 'Migrated from the signed spreadsheet.', 'enrolled_at' => '2020-01-01', 'rows' => array_map(fn ($date) => ['awarded_at' => $date, 'expiry_overridden' => false, 'originally_awarded_by' => 'Previous manager'], $dates)];
}

function saveManualHistory(array $f, ?array $data = null)
{
    return test()->actingAs($f['actor'])->put(route('certificate-enrollments.history.update', $f['enrollment']), $data ?? historyPayload());
}

test('manual history chains early renewals and lapses and opens the next renewal cycle', function () {
    $f = certificationActivityFixture();
    $f['certificate']->update(['validity_months' => 12]);
    $this->actingAs($f['actor'])->get(route('certificate-enrollments.history.edit', $f['enrollment']))->assertOk()->assertSee('Record award history');

    saveManualHistory($f)->assertSessionHasNoErrors()->assertSessionHas('success');

    $awards = $f['enrollment']->awards()->reorder('cycle')->get();
    expect($awards->pluck('cycle')->all())->toBe([1, 2, 3]);
    expect($awards->pluck('phase')->all())->toBe([CertificateRequirementPhase::Initial, CertificateRequirementPhase::Renewal, CertificateRequirementPhase::Renewal]);
    expect($awards->map(fn ($award) => $award->expires_at->toDateString())->all())->toBe(['2022-01-31', '2023-01-31', '2025-03-04']);
    expect($awards->every(fn ($award) => $award->manual_award && $award->endorsed_by_user_id === null && $award->awarded_by_user_id === $f['actor']->id && $award->originally_awarded_by === 'Previous manager'))->toBeTrue();
    expect($awards->last()->isExpired())->toBeTrue();
    $enrollment = $f['enrollment']->fresh();
    expect([$enrollment->cycle, $enrollment->phase, $enrollment->status])->toBe([4, CertificateRequirementPhase::Renewal, CertificateEnrollmentStatus::InProgress]);
    expect($enrollment->actionLogs()->sole()->action)->toBe(CertificateAction::HistoryRecorded);
    expect(app(CertificateLifecycleService::class)->previousAwardDate($enrollment)->toDateString())->toBe('2024-03-04');
    $this->get(route('certificate-enrollments.show', $enrollment))->assertOk()->assertSee('Recorded by')->assertSee('Edit award history');
    $this->get(route('certificate-enrollments.awards.show', [$enrollment, $awards->first()]))->assertOk()->assertSee('Recorded manually')->assertSee('Migrated from the signed spreadsheet.');
});

test('an overridden expiry feeds the next award and edits recompute the chain', function () {
    $f = certificationActivityFixture();
    $f['certificate']->update(['validity_months' => 12]);
    $data = historyPayload(['2021-01-01', '2021-12-01']);
    $data['rows'][0] += ['expires_at' => '2023-06-01'];
    $data['rows'][0]['expiry_overridden'] = true;
    saveManualHistory($f, $data)->assertSessionHas('success');
    $awards = $f['enrollment']->awards()->reorder('cycle')->get();
    expect($awards[1]->expires_at->toDateString())->toBe('2024-06-01');
    $data['cycle'] = 3;
    foreach ($awards as $index => $award) {
        $data['rows'][$index]['id'] = $award->id;
    }
    $data['rows'][0]['expiry_overridden'] = false;
    $data['rows'][0]['awarded_at'] = '2021-02-01';
    saveManualHistory($f, $data)->assertSessionHas('success');
    expect($awards[1]->fresh()->expires_at->toDateString())->toBe('2023-02-01');
    $log = $f['enrollment']->actionLogs()->first();
    expect($log->action)->toBe(CertificateAction::HistoryEdited);
    expect($log->context['changes'][1]['awarded_at'])->toBe(['from' => '2021-01-01', 'to' => '2021-02-01']);
    $data['rows'][] = ['awarded_at' => '2025-01-01', 'expiry_overridden' => false];
    saveManualHistory($f, $data)->assertSessionHas('success');
    expect($f['enrollment']->fresh()->cycle)->toBe(4);
    $data['cycle'] = 4;
    array_pop($data['rows']);
    saveManualHistory($f, $data)->assertSessionHasErrors('rows');
    expect($f['enrollment']->awards()->count())->toBe(3);
});

test('history validates dates and reason without writing partial awards', function (string $failure, string $field) {
    $f = certificationActivityFixture();
    $f['certificate']->update(['validity_months' => 12]);
    $data = historyPayload();
    match ($failure) {
        'reason' => $data['reason'] = '',
        'future' => $data['rows'][0]['awarded_at'] = today()->addDay()->toDateString(),
        'order' => $data['rows'][1]['awarded_at'] = $data['rows'][0]['awarded_at'],
        'enrollment' => $data['enrolled_at'] = '2022-01-01',
        'expiry' => $data['rows'][0] = ['awarded_at' => '2021-01-31', 'expiry_overridden' => true, 'expires_at' => '2021-01-01'],
    };

    saveManualHistory($f, $data)->assertSessionHasErrors($field);
    expect($f['enrollment']->awards()->count())->toBe(0);
})->with([['reason', 'reason'], ['future', 'rows.0.awarded_at'], ['order', 'rows.1.awarded_at'], ['enrollment', 'enrolled_at'], ['expiry', 'rows.0.expires_at']]);

test('never expiring history accepts one award only', function () {
    $f = certificationActivityFixture();
    $f['certificate']->update(['validity_months' => null]);
    saveManualHistory($f)->assertSessionHasErrors('rows');
    saveManualHistory($f, historyPayload(['2021-01-01']))->assertSessionHas('success');
    expect($f['enrollment']->fresh()->status)->toBe(CertificateEnrollmentStatus::Awarded);
    expect($f['enrollment']->awards()->sole()->expires_at)->toBeNull();
});

test('in app awards endorsements and revocation block both history surfaces', function (string $state) {
    $f = certificationActivityFixture();
    if ($state === 'revoked') {
        $f['enrollment']->update(['status' => CertificateEnrollmentStatus::Revoked]);
    } else {
        CertificateAward::factory()->create(['certificate_enrollment_id' => $f['enrollment']->id, 'awarded_at' => $state === 'pending' ? null : now()]);
    }
    $count = $f['enrollment']->awards()->count();

    $this->actingAs($f['actor'])->get(route('certificate-enrollments.history.edit', $f['enrollment']))->assertOk()->assertSee('History unavailable')->assertDontSee('id="history-form"', false);
    saveManualHistory($f)->assertSessionHas('error');
    expect($f['enrollment']->awards()->count())->toBe($count);
})->with(['issued', 'pending', 'revoked']);

test('history requires full admin scope over every agreement', function (string $scope, bool $allowed) {
    $f = certificationActivityFixture();
    $f['certificate']->update(['validity_months' => 12]);
    $actor = match ($scope) {
        'system' => $f['actor'],
        'coach', 'manager' => createProgramViewer($f['program']),
        'project' => User::factory()->adminViewer()->create(),
        'input' => User::factory()->create(['access_profile' => AccessProfile::Input]),
        default => createProgramAdmin($f['program']),
    };
    if (in_array($scope, ['coach', 'manager'])) {
        UserCertificationDuty::factory()->{$scope}()->atProgram($f['program'])->create(['user_id' => $actor->id]);
    }
    if ($scope === 'project') {
        UserPrivilege::factory()->projectAdmin($f['project'])->create(['user_id' => $actor->id]);
    }
    if (in_array($scope, ['second', 'partial'])) {
        $outside = Program::factory()->create();
        if ($scope === 'second') {
            $agreement = Agreement::factory()->create();
            $agreement->programs()->sync([$outside->id]);
            AgreementCertificationCandidate::factory()->create(['agreement_id' => $agreement->id, 'certification_candidate_id' => $f['candidate']->id]);
        } else {
            $f['agreement']->programs()->attach($outside);
        }
    }
    if ($scope === 'sponsor') {
        $outside = Program::factory()->create();
        $sponsor = Agreement::factory()->create();
        $sponsor->programs()->attach($outside);
        $f['enrollment']->update(['sponsoring_agreement_id' => $sponsor->id]);
    }
    if ($scope === 'all') {
        $f['agreement']->update(['program_scope_mode' => ProgramScopeMode::All]);
    }
    $f['actor'] = $actor;
    $response = $this->actingAs($actor)->get(route('certificate-enrollments.history.edit', $f['enrollment']));
    if ($allowed) {
        $response->assertOk();
        saveManualHistory($f)->assertSessionHas('success');
    } else {
        $response->assertForbidden();
        saveManualHistory($f)->assertForbidden();
        expect($f['enrollment']->awards()->count())->toBe(0);
    }
})->with([['sponsor', false], ['input', false], ['project', true], ['system', true], ['program', true], ['partial', false], ['second', false], ['coach', false], ['manager', false], ['all', false]]);

test('history cannot be edited after an in app renewal endorsement', function () {
    $f = certificationActivityFixture();
    $f['certificate']->update(['validity_months' => 12]);
    saveManualHistory($f, historyPayload(['2021-01-01']))->assertSessionHas('success');
    CertificateAward::factory()->pending()->create(['certificate_enrollment_id' => $f['enrollment']->id, 'cycle' => 2, 'phase' => 'renewal']);
    $data = historyPayload(['2021-01-01']);
    $data['cycle'] = 2;
    $data['rows'][0]['id'] = $f['enrollment']->awards()->where('cycle', 1)->sole()->id;
    saveManualHistory($f, $data)->assertSessionHas('error');
    expect($f['enrollment']->actionLogs()->count())->toBe(1);
});

test('backfill establishes the renewal activity window without moving confirmations', function () {
    $f = certificationActivityFixture();
    $f['certificate']->update(['validity_months' => 12, 'renewal_matches_initial' => true]);
    $req = CertificateRequirement::factory()->for($f['certificate'])->activityCount()->create(['target_count' => 1]);
    $confirmation = CertificateAttestation::factory()->create(['certificate_enrollment_id' => $f['enrollment']->id, 'certificate_requirement_id' => $req->id, 'cycle' => 1]);
    $addEvidence = function (string $date) use ($f): void {
        $activity = Activity::factory()->create(['activity_type_id' => $f['type']->id, 'engagement_date' => $date, 'user_id' => $f['actor']->id]);
        $activity->programs()->attach($f['program']);
        $activity->agreements()->attach($f['agreement']);
        ActivityCertificationCandidate::factory()->create(['activity_id' => $activity->id, 'certification_candidate_id' => $f['candidate']->id]);
    };
    $addEvidence('2024-03-03');
    saveManualHistory($f)->assertSessionHas('success');
    $service = app(CertificateLifecycleService::class);
    expect($service->evaluate($f['enrollment']->fresh())['requirements'][$req->id]['count'])->toBe(0);
    $addEvidence('2024-03-05');
    expect($service->evaluate($f['enrollment']->fresh())['requirements'][$req->id]['count'])->toBe(1);
    expect($confirmation->fresh()->cycle)->toBe(1);
});

test('agreement sync changes manageable enrollment dates and ignores locked enrollment dates', function (bool $manageable) {
    $f = certificationActivityFixture();
    $actor = $manageable ? $f['actor'] : createProgramAdmin($f['program']);
    if (! $manageable) {
        $outside = Program::factory()->create();
        $second = Agreement::factory()->create();
        $second->programs()->attach($outside);
        AgreementCertificationCandidate::factory()->create(['agreement_id' => $second->id, 'certification_candidate_id' => $f['candidate']->id]);
    }
    $old = $f['enrollment']->enrolled_at->toDateString();
    $rows = [['id' => $f['roster']->id, 'certification_candidate_id' => $f['candidate']->id, 'name' => $f['candidate']->name, 'email' => $f['candidate']->email, 'assignments' => [['enrollment_id' => $f['enrollment']->id, 'certificate_id' => $f['certificate']->id, 'enrolled_at' => '2020-01-01']]]];
    app(CertificationAssignmentSyncService::class)->sync($f['agreement'], $actor, $rows);
    expect($f['enrollment']->fresh()->enrolled_at->toDateString())->toBe($manageable ? '2020-01-01' : $old);
})->with([true, false]);

test('an award page keeps every history reason that touched that award', function () {
    $f = certificationActivityFixture();
    $f['certificate']->update(['validity_months' => 12]);
    $data = historyPayload(['2021-01-01', '2023-06-01']);
    saveManualHistory($f, $data)->assertSessionHas('success');
    [$first, $second] = $f['enrollment']->awards()->reorder('cycle')->get()->all();
    $data = ['cycle' => 3, 'reason' => 'Corrected the initial date.', 'rows' => [
        ['id' => $first->id, 'awarded_at' => '2021-02-01', 'expiry_overridden' => false, 'originally_awarded_by' => 'Previous manager'],
        ['id' => $second->id, 'awarded_at' => '2023-06-01', 'expiry_overridden' => false, 'originally_awarded_by' => 'Previous manager'],
    ]];
    saveManualHistory($f, $data)->assertSessionHas('success');

    $this->get(route('certificate-enrollments.awards.show', [$f['enrollment'], $first]))
        ->assertSeeInOrder(['Migrated from the signed spreadsheet.', 'Corrected the initial date.']);
    $this->get(route('certificate-enrollments.awards.show', [$f['enrollment'], $second]))
        ->assertSee('Migrated from the signed spreadsheet.')->assertDontSee('Corrected the initial date.');
});

test('an unchanged enrollment date after the backfilled awards does not block saving', function () {
    $f = certificationActivityFixture();
    $f['certificate']->update(['validity_months' => 12]);
    $current = $f['enrollment']->enrolled_at->toDateString();

    saveManualHistory($f, ['enrolled_at' => $current] + historyPayload())->assertSessionHasNoErrors()->assertSessionHas('success');

    $rows = [['id' => $f['roster']->id, 'certification_candidate_id' => $f['candidate']->id, 'name' => $f['candidate']->name, 'email' => $f['candidate']->email, 'assignments' => [['enrollment_id' => $f['enrollment']->id, 'certificate_id' => $f['certificate']->id, 'enrolled_at' => $current]]]];
    app(CertificationAssignmentSyncService::class)->sync($f['agreement'], $f['actor'], $rows);
    expect($f['enrollment']->fresh()->enrolled_at->toDateString())->toBe($current);
});

test('agreement sync rejects enrollment dates after an award or in the future', function (string $date) {
    $f = certificationActivityFixture();
    CertificateAward::factory()->manual()->create(['certificate_enrollment_id' => $f['enrollment']->id, 'awarded_at' => '2021-01-01']);
    $old = $f['enrollment']->enrolled_at->toDateString();
    $rows = [['id' => $f['roster']->id, 'certification_candidate_id' => $f['candidate']->id, 'name' => $f['candidate']->name, 'email' => $f['candidate']->email, 'assignments' => [['enrollment_id' => $f['enrollment']->id, 'certificate_id' => $f['certificate']->id, 'enrolled_at' => $date]]]];
    expect(fn () => app(CertificationAssignmentSyncService::class)->sync($f['agreement'], $f['actor'], $rows))->toThrow(ValidationException::class);
    expect($f['enrollment']->fresh()->enrolled_at->toDateString())->toBe($old);
})->with(['2022-01-01', '2099-01-01']);

test('history rejects foreign award ids and stale cycles without changing saved rows', function () {
    $f = certificationActivityFixture();
    $f['certificate']->update(['validity_months' => 12]);
    saveManualHistory($f, historyPayload(['2021-01-01']))->assertSessionHas('success');
    $award = $f['enrollment']->awards()->sole();
    $data = historyPayload(['2021-02-01']);
    $data['cycle'] = 2;
    $data['rows'][0]['id'] = CertificateAward::factory()->manual()->create()->id;
    saveManualHistory($f, $data)->assertSessionHasErrors('rows.0.id');
    $data['rows'][0]['id'] = $award->id;
    $data['cycle'] = 1;
    saveManualHistory($f, $data)->assertSessionHas('error');
    expect($award->fresh()->awarded_at->toDateString())->toBe('2021-01-01');
    expect($f['enrollment']->actionLogs()->count())->toBe(1);
});
