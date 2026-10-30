<?php

use App\Enums\CertificateEnrollmentStatus;
use App\Models\Agreement;
use App\Models\AgreementCertificationCandidate;
use App\Models\CertificateEnrollment;
use App\Models\CertificationCandidate;
use App\Models\Program;
use App\Models\User;

function reconcileLegacyCandidates(): void
{
    (require database_path('migrations/2026_10_25_131838_reconcile_legacy_certification_candidates.php'))->up();
}

test('legacy names reconcile to a shared person while all agreement links and assignments survive', function () {
    $first = CertificationCandidate::factory()->withoutEmail()->create(['name' => 'Lisa Anderson', 'notes' => 'First detail']);
    $copy = CertificationCandidate::factory()->withoutEmail()->create(['name' => ' lisa   ANDERSON ', 'notes' => 'Other detail']);
    $firstRoster = AgreementCertificationCandidate::factory()->create(['certification_candidate_id' => $first->id, 'notes' => 'Local A', 'sort_order' => 3]);
    $secondRoster = AgreementCertificationCandidate::factory()->create(['certification_candidate_id' => $copy->id, 'notes' => 'Local B', 'sort_order' => 5]);
    $enrollment = CertificateEnrollment::factory()->create([
        'certification_candidate_id' => $copy->id, 'sponsoring_agreement_id' => $secondRoster->agreement_id,
        'supervising_coach_user_id' => User::factory(),
    ]);
    $originalAssignment = $enrollment->only(['id', 'certificate_id', 'supervising_coach_user_id', 'sponsoring_agreement_id', 'enrolled_at']);
    $staffCount = User::count();

    reconcileLegacyCandidates();
    reconcileLegacyCandidates();

    expect(CertificationCandidate::count())->toBe(1)
        ->and($first->fresh()->name)->toBe('Lisa Anderson')
        ->and($first->fresh()->email)->toBeNull()
        ->and($first->fresh()->notes)->toBe("First detail\n\nOther detail")
        ->and($secondRoster->fresh()->certification_candidate_id)->toBe($first->id)
        ->and($firstRoster->fresh()->notes)->toBe('Local A')
        ->and($secondRoster->fresh()->notes)->toBe('Local B')
        ->and($secondRoster->fresh()->sort_order)->toBe(5)
        ->and($enrollment->fresh()->certification_candidate_id)->toBe($first->id)
        ->and($enrollment->fresh()->only(array_keys($originalAssignment)))->toEqual($originalAssignment)
        ->and(User::count())->toBe($staffCount);
});

test('duplicate roster placements combine notes without losing the known program', function () {
    $first = CertificationCandidate::factory()->withoutEmail()->create(['name' => 'Same person']);
    $copy = CertificationCandidate::factory()->withoutEmail()->create(['name' => 'Same person']);
    $agreement = Agreement::factory()->create();
    $program = Program::factory()->create();
    $link = AgreementCertificationCandidate::factory()->create(['agreement_id' => $agreement->id, 'certification_candidate_id' => $first->id, 'notes' => 'A', 'sort_order' => 4]);
    AgreementCertificationCandidate::factory()->create(['agreement_id' => $agreement->id, 'certification_candidate_id' => $copy->id, 'program_id' => $program->id, 'notes' => 'B', 'sort_order' => 2]);

    reconcileLegacyCandidates();

    expect(AgreementCertificationCandidate::count())->toBe(1)
        ->and($link->fresh()->notes)->toBe("A\n\nB")
        ->and($link->fresh()->program_id)->toBe($program->id)
        ->and($link->fresh()->sort_order)->toBe(2);
});

test('one known identity is retained and compatible unstarted assignments are reconciled', function () {
    $legacy = CertificationCandidate::factory()->withoutEmail()->create(['name' => 'Known person']);
    $known = CertificationCandidate::factory()->create(['name' => 'Known person', 'email' => 'known@example.test']);
    $assignment = CertificateEnrollment::factory()->create(['certification_candidate_id' => $legacy->id, 'notes' => 'A', 'enrolled_at' => '2026-01-01']);
    CertificateEnrollment::factory()->create(['certification_candidate_id' => $known->id, 'certificate_id' => $assignment->certificate_id, 'notes' => 'B', 'enrolled_at' => '2026-02-01']);

    reconcileLegacyCandidates();

    expect(CertificationCandidate::count())->toBe(1)
        ->and($known->fresh()->email)->toBe('known@example.test')
        ->and(CertificateEnrollment::count())->toBe(1)
        ->and($assignment->fresh()->certification_candidate_id)->toBe($known->id)
        ->and($assignment->fresh()->notes)->toBe("A\n\nB")
        ->and($assignment->fresh()->enrolled_at->toDateString())->toBe('2026-01-01');
});

test('separately identified people with equal names remain distinct', function () {
    CertificationCandidate::factory()->create(['name' => 'Same name', 'email' => 'one@example.test']);
    CertificationCandidate::factory()->create(['name' => 'Same name', 'email' => 'two@example.test']);

    reconcileLegacyCandidates();

    expect(CertificationCandidate::count())->toBe(2);
});

test('conflicting identities abort reconciliation atomically', function () {
    CertificationCandidate::factory()->withoutEmail()->count(2)->create(['name' => 'First mergeable group']);
    $legacy = CertificationCandidate::factory()->withoutEmail()->create(['name' => 'Conflicting person']);
    CertificationCandidate::factory()->create(['name' => 'Conflicting person', 'email' => 'one@example.test']);
    CertificationCandidate::factory()->create(['name' => 'Conflicting person', 'email' => 'two@example.test']);

    expect(fn () => reconcileLegacyCandidates())->toThrow(RuntimeException::class, 'conflicting email');
    expect(CertificationCandidate::count())->toBe(5)->and($legacy->fresh()->email)->toBeNull();
});

test('duplicate enrollments with lifecycle history are retained for review', function () {
    $first = CertificationCandidate::factory()->withoutEmail()->create(['name' => 'Started person']);
    $copy = CertificationCandidate::factory()->withoutEmail()->create(['name' => 'Started person']);
    $assignment = CertificateEnrollment::factory()->create(['certification_candidate_id' => $first->id]);
    CertificateEnrollment::factory()->create(['certification_candidate_id' => $copy->id, 'certificate_id' => $assignment->certificate_id, 'status' => CertificateEnrollmentStatus::InProgress]);

    expect(fn () => reconcileLegacyCandidates())->toThrow(RuntimeException::class, 'lifecycle history');
    expect(CertificationCandidate::count())->toBe(2)->and(CertificateEnrollment::count())->toBe(2);
});

test('conflicting roster programs are not silently discarded', function () {
    $people = CertificationCandidate::factory()->withoutEmail()->count(2)->create(['name' => 'Program conflict']);
    $agreement = Agreement::factory()->create();
    foreach ($people as $person) {
        AgreementCertificationCandidate::factory()->create([
            'agreement_id' => $agreement->id, 'certification_candidate_id' => $person->id, 'program_id' => Program::factory(),
        ]);
    }

    expect(fn () => reconcileLegacyCandidates())->toThrow(RuntimeException::class, 'conflicting roster programs');
    expect(CertificationCandidate::count())->toBe(2)->and(AgreementCertificationCandidate::count())->toBe(2);
});

test('blank names do not establish a shared identity', function () {
    CertificationCandidate::factory()->withoutEmail()->count(2)->create(['name' => ' ']);
    reconcileLegacyCandidates();
    expect(CertificationCandidate::count())->toBe(2);
});
