<?php

use App\Models\CertificationInstrument;
use App\Models\CertificationInstrumentSubmission;
use App\Models\UserCertificationDuty;

/**
 * The capture fixture with a coach actor and its score field as Points out of 3.
 *
 * @return array<string, mixed>
 */
function pointsOutOfThreeFixture(): array
{
    $f = certificationActivityFixture();
    UserCertificationDuty::factory()->coach()->atProgram($f['program'])->create(['user_id' => $f['actor']->id]);
    $f['score']->update(['name' => 'Rapport', 'unit' => 'points', 'max_points' => 3]);

    return $f;
}

/** @return array<string, mixed> */
function pointsToolUpdatePayload(array $f, array $scoreField): array
{
    return [
        'name' => $f['tool']->name, 'type' => 'tool', 'active' => '1', 'program_scope_mode' => 'specific',
        'project_ids' => [$f['project']->id], 'program_ids' => [$f['program']->id],
        'dimensions' => [['id' => $f['dimension']->id, 'name' => 'Phase', 'options' => [['id' => $f['option']->id, 'label' => 'Phase 2']]]],
        'score_fields' => [['id' => $f['score']->id, 'name' => 'Rapport'] + $scoreField],
    ];
}

test('an admin sets points out of on a points score field and other units drop it', function () {
    $admin = createSystemAdmin();

    $this->actingAs($admin)->post(route('certification-instruments.store'), toolStorePayload([
        'score_fields' => [
            ['name' => 'Rapport', 'unit' => 'points', 'max_points' => '3'],
            ['name' => 'Open ended', 'unit' => 'points', 'max_points' => ''],
            ['name' => 'Match', 'unit' => 'percent', 'max_points' => '3'],
        ],
    ]))->assertSessionHasNoErrors();

    expect(CertificationInstrument::query()->where('name', 'COMET')->sole()->scoreFields->pluck('max_points', 'name')->all())
        ->toBe(['Rapport' => 3, 'Open ended' => null, 'Match' => null]);
});

test('a coach records only whole points from zero to the max', function (string $value) {
    $f = pointsOutOfThreeFixture();
    $payload = $f['payload'];
    $payload['certification_candidates'][0]['submissions'] = [certificationSubmissionPayload($f, ['scores' => [$f['score']->id => $value]])];

    $this->actingAs($f['actor'])->post(route('activities.store'), $payload)
        ->assertSessionHasErrors(['certification_candidates.0.submissions.0.scores.'.$f['score']->id => 'Enter a whole number from 0 to 3 for Rapport, or leave it blank.']);

    expect(CertificationInstrumentSubmission::query()->count())->toBe(0);
})->with(['above the max' => '4', 'fraction' => '2.5', 'negative' => '-1']);

test('a coach records a score at the max', function () {
    $f = pointsOutOfThreeFixture();
    $payload = $f['payload'];
    $payload['certification_candidates'][0]['submissions'] = [certificationSubmissionPayload($f, ['scores' => [$f['score']->id => '3']])];

    $this->actingAs($f['actor'])->post(route('activities.store'), $payload)->assertSessionHasNoErrors();

    expect(CertificationInstrumentSubmission::query()->sole()->scores->sole()->value)->toBe('3.0000');
});

test('points out of cannot drop below a recorded score', function () {
    $f = certificationActivityFixture();
    UserCertificationDuty::factory()->coach()->atProgram($f['program'])->create(['user_id' => $f['actor']->id]);
    $f['score']->update(['name' => 'Rapport', 'unit' => 'points']);
    $payload = $f['payload'];
    $payload['certification_candidates'][0]['submissions'] = [certificationSubmissionPayload($f, ['scores' => [$f['score']->id => '5']])];
    $this->actingAs($f['actor'])->post(route('activities.store'), $payload)->assertSessionHasNoErrors();

    $this->put(route('certification-instruments.update', $f['tool']), pointsToolUpdatePayload($f, ['unit' => 'points', 'max_points' => '3']))
        ->assertSessionHasErrors(['score_fields.0.max_points' => "Rapport can't be out of 3: a recorded submission already scored 5 on it. Use a higher number, or edit that submission first."]);
    expect($f['score']->fresh()->max_points)->toBeNull();

    $this->put(route('certification-instruments.update', $f['tool']), pointsToolUpdatePayload($f, ['unit' => 'points', 'max_points' => '5']))
        ->assertSessionHasNoErrors();
    expect($f['score']->fresh()->max_points)->toBe(5);
});
