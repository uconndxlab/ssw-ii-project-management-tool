<?php

use App\Models\Activity;
use App\Models\ActivityCertificationCandidate;
use App\Models\CertificateAttestation;
use App\Models\CertificateAward;
use App\Models\CertificateEnrollment;
use App\Models\Program;

test('lifecycle scope agrees with badges including precedence and evidence', function () {
    $this->travelTo(now()->startOfDay());
    $cases = [];
    $cases['assigned'] = CertificateEnrollment::factory()->create();
    $cases['in_progress'] = CertificateEnrollment::factory()->create(['cycle' => 2, 'phase' => 'renewal']);
    $cases['revoked'] = CertificateEnrollment::factory()->create(['status' => 'revoked']);
    foreach (['certified', 'expired', 'endorsed'] as $key) {
        $cases[$key] = CertificateEnrollment::factory()->create();
        CertificateAward::factory()->create(['certificate_enrollment_id' => $cases[$key]->id, 'awarded_at' => $key === 'endorsed' ? null : now()->subYear(), 'expires_at' => $key === 'expired' ? today() : today()->addYear()]);
    }
    foreach ($cases as $key => $enrollment) {
        expect(strtolower($enrollment->lifecycleBadge()['label']))->toStartWith(str_replace('_', ' ', $key));
        expect(CertificateEnrollment::query()->lifecycleStatus($key)->pluck('id')->all())->toBe([$enrollment->id]);
        $enrollment->primeUnstarted(CertificateEnrollment::query()->whereKey($enrollment->id)->unstarted()->exists());
        expect(strtolower($enrollment->lifecycleBadge()['label']))->toStartWith(str_replace('_', ' ', $key));
    }
    $live = $cases['certified'];
    $live->update(['cycle' => 2, 'phase' => 'renewal']);
    CertificateAward::factory()->pending()->create(['certificate_enrollment_id' => $live->id, 'cycle' => 2]);
    expect(CertificateEnrollment::query()->lifecycleStatus('certified')->pluck('id'))->toContain($live->id);
    expect($live->fresh()->lifecycleBadge()['label'])->toStartWith('Certified');

    $started = CertificateEnrollment::factory()->create();
    $program = Program::factory()->create();
    $started->certificate->programs()->attach($program);
    $activity = Activity::factory()->create();
    $activity->programs()->attach($program);
    ActivityCertificationCandidate::factory()->create(['activity_id' => $activity->id, 'certification_candidate_id' => $started->certification_candidate_id]);
    expect($started->isUnstarted())->toBeFalse();
    expect(CertificateEnrollment::query()->lifecycleStatus('in_progress')->pluck('id'))->toContain($started->id);
});

test('unstarted query and badge agree for evidence boundaries', function (string $evidence, bool $expected) {
    $enrollment = CertificateEnrollment::factory()->create();
    $program = Program::factory()->create();
    $enrollment->certificate->programs()->attach($program);
    if ($evidence === 'attestation') {
        CertificateAttestation::factory()->create(['certificate_enrollment_id' => $enrollment->id]);
    } else {
        $activity = Activity::factory()->create();
        if ($evidence !== 'no_program') {
            $activity->programs()->attach($evidence === 'other_program' ? Program::factory()->create() : $program);
        }
        if ($evidence === 'all_programs') {
            $enrollment->certificate->update(['program_scope_mode' => 'all']);
        }
        ActivityCertificationCandidate::factory()->create(['activity_id' => $activity->id, 'certification_candidate_id' => $enrollment->certification_candidate_id]);
    }
    expect($enrollment->fresh()->isUnstarted())->toBe($expected)
        ->and(CertificateEnrollment::query()->whereKey($enrollment->id)->unstarted()->exists())->toBe($expected);
})->with(['attestation' => ['attestation', false], 'other program' => ['other_program', true], 'no program' => ['no_program', true], 'all programs' => ['all_programs', false]]);

test('latest issued award controls lifecycle even when older awards remain live', function () {
    $enrollment = CertificateEnrollment::factory()->create(['cycle' => 3, 'phase' => 'renewal']);
    CertificateAward::factory()->create(['certificate_enrollment_id' => $enrollment->id, 'cycle' => 1]);
    CertificateAward::factory()->create(['certificate_enrollment_id' => $enrollment->id, 'cycle' => 2, 'revoked_at' => now()]);
    CertificateAward::factory()->pending()->create(['certificate_enrollment_id' => $enrollment->id, 'cycle' => 3]);
    expect($enrollment->lifecycleBadge()['label'])->toBe('Endorsed');
    expect(CertificateEnrollment::query()->lifecycleStatus('endorsed')->pluck('id'))->toContain($enrollment->id);
    expect(CertificateEnrollment::query()->lifecycleStatus('certified')->exists())->toBeFalse();
});
