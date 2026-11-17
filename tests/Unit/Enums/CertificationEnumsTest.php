<?php

use App\Enums\CertificateDimensionRuleMode;
use App\Enums\CertificateEnrollmentStatus;
use App\Enums\CertificateGroupSatisfyMode;
use App\Enums\CertificateRequirementKind;
use App\Enums\CertificateRequirementPhase;
use App\Enums\CertificateTagOutcome;
use App\Enums\CertificationDuty;
use App\Enums\CertificationInstrumentScoreUnit;

test('certificate requirement kind has expected cases and labels', function () {
    expect(CertificateRequirementKind::values())->toBe([
        'activity_count',
        'tool_submission',
        'training_sections',
        'attestation',
    ]);

    expect(CertificateRequirementKind::ActivityCount->label())->toBe('Activity work');
    expect(CertificateRequirementKind::ToolSubmission->label())->toBe('Tool submissions');
    expect(CertificateRequirementKind::TrainingSections->label())->toBe('Training sections');
    expect(CertificateRequirementKind::Attestation->label())->toBe('Coach confirmation');
});

test('certificate requirement phase has expected cases and labels', function () {
    expect(CertificateRequirementPhase::values())->toBe(['initial', 'renewal']);
    expect(CertificateRequirementPhase::Initial->label())->toBe('Initial Certification');
    expect(CertificateRequirementPhase::Renewal->label())->toBe('Recertification');
});

test('certificate dimension rule mode has expected cases labels and descriptions', function () {
    expect(CertificateDimensionRuleMode::values())->toBe(['coverage', 'quota']);
    expect(CertificateDimensionRuleMode::Coverage->label())->toBe('Must Cover Each');
    expect(CertificateDimensionRuleMode::Quota->label())->toBe('Minimum Count');
    expect(CertificateDimensionRuleMode::Coverage->description())->toContain('phases 2-4');
    expect(CertificateDimensionRuleMode::Quota->description())->toContain('4 of 6');
});

test('certificate group satisfy mode has expected cases and labels', function () {
    expect(CertificateGroupSatisfyMode::values())->toBe(['all', 'any', 'n_of']);
    expect(CertificateGroupSatisfyMode::All->label())->toBe('All Requirements');
    expect(CertificateGroupSatisfyMode::Any->label())->toBe('Any One Requirement');
    expect(CertificateGroupSatisfyMode::NOf->label())->toBe('A Set Number');
});

test('certification tool score unit has expected cases labels and suffixes', function () {
    expect(CertificationInstrumentScoreUnit::values())->toBe(['percent', 'points', 'number', 'pass_fail']);
    expect(CertificationInstrumentScoreUnit::Percent->label())->toBe('Percentage');
    expect(CertificationInstrumentScoreUnit::Points->label())->toBe('Points');
    expect(CertificationInstrumentScoreUnit::Number->label())->toBe('Number');
    expect(CertificationInstrumentScoreUnit::Percent->suffix())->toBe('%');
    expect(CertificationInstrumentScoreUnit::Points->suffix())->toBe('pts');
    expect(CertificationInstrumentScoreUnit::Number->suffix())->toBeNull();
    expect(CertificationInstrumentScoreUnit::PassFail->suffix())->toBeNull();
    expect(CertificationInstrumentScoreUnit::PassFail->format('1.0000'))->toBe('Pass');
    expect(CertificationInstrumentScoreUnit::PassFail->format('0.0000'))->toBe('Fail');
    expect(CertificationInstrumentScoreUnit::Percent->format('85.5000'))->toBe('85.5%');
});

test('certification duty has expected cases and labels', function () {
    expect(CertificationDuty::values())->toBe(['coach', 'manager']);
    expect(CertificationDuty::Coach->label())->toBe('National Coach');
    expect(CertificationDuty::Manager->label())->toBe('Certification Manager');
});

test('certificate enrollment status has expected cases labels icons and tones', function () {
    expect(CertificateEnrollmentStatus::values())->toBe([
        'not_started',
        'in_progress',
        'requirements_met',
        'endorsed',
        'awarded',
        'expired',
        'revoked',
    ]);

    expect(CertificateEnrollmentStatus::NotStarted->label())->toBe('Not Started');
    expect(CertificateEnrollmentStatus::Awarded->label())->toBe('Awarded');
    expect(CertificateEnrollmentStatus::InProgress->icon())->toBe('arrow-right-circle-fill');
    expect(CertificateEnrollmentStatus::Awarded->icon())->toBe('award-fill');
    expect(CertificateEnrollmentStatus::Revoked->tone())->toBe('danger');
    expect(CertificateEnrollmentStatus::Awarded->tone())->toBe('success');
});

test('certificate tag outcome has expected cases labels and tones', function () {
    expect(CertificateTagOutcome::values())->toBe(['passed', 'not_passed', 'not_scored']);
    expect(CertificateTagOutcome::Passed->label())->toBe('Passed');
    expect(CertificateTagOutcome::NotPassed->label())->toBe('Did Not Pass');
    expect(CertificateTagOutcome::NotScored->label())->toBe('Not Scored');
    expect(CertificateTagOutcome::Passed->tone())->toBe('success');
    expect(CertificateTagOutcome::NotPassed->tone())->toBe('danger');
});
