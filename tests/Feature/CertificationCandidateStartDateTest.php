<?php

use App\Models\Agreement;
use App\Models\AgreementCertificationCandidate;
use App\Models\CertificationCandidate;

test('a new candidate can have an optional start date', function (?string $startDate) {
    $admin = createSystemAdmin();
    [$project, $program] = createProjectWithProgram();

    $this->actingAs($admin)->post(route('agreements.store'), agreementStorePayload($project, $program, [
        'certification_candidates' => [[
            'name' => 'Candidate with optional date', 'email' => 'start@example.test', 'start_date' => $startDate,
        ]],
    ]))->assertSessionHasNoErrors()->assertRedirect();

    expect(CertificationCandidate::sole()->start_date?->format('Y-m-d'))->toBe($startDate);
})->with(['dated' => '2026-10-15', 'undated' => null]);

test('a candidate start date can be edited cleared or omitted', function (array $dateFields, ?string $expected, ?string $display) {
    $admin = createSystemAdmin();
    [$project, $program] = createProjectWithProgram();
    $agreement = Agreement::factory()->create();
    $agreement->programs()->attach($program);
    $candidate = CertificationCandidate::factory()->withoutEmail()->create(['start_date' => '2026-10-15']);
    $roster = AgreementCertificationCandidate::factory()->create([
        'agreement_id' => $agreement->id, 'certification_candidate_id' => $candidate->id,
    ]);

    $this->actingAs($admin)->get(route('agreements.edit', $agreement))
        ->assertOk()->assertSee('name="certification_candidates[0][start_date]"', false)
        ->assertSee('value="2026-10-15"', false);

    $this->put(route('agreements.update', $agreement), agreementStorePayload($project, $program, [
        'certification_candidates' => [[
            'id' => $roster->id, 'certification_candidate_id' => $candidate->id,
            ...$dateFields,
        ]],
    ]))->assertSessionHasNoErrors()->assertRedirect();

    expect($candidate->fresh()->start_date?->format('Y-m-d'))->toBe($expected);

    $show = $this->get(route('agreements.show', $agreement))->assertOk();
    $display === null ? $show->assertDontSee('Start Date: ') : $show->assertSee('Start Date: '.$display);
})->with([
    'edit' => [['start_date' => '2026-11-01'], '2026-11-01', 'Nov 1, 2026'],
    'clear' => [['start_date' => ''], null, null],
    'omit' => [[], '2026-10-15', 'Oct 15, 2026'],
]);

test('an invalid candidate start date is rejected', function () {
    $admin = createSystemAdmin();
    [$project, $program] = createProjectWithProgram();

    $this->actingAs($admin)->post(route('agreements.store'), agreementStorePayload($project, $program, [
        'certification_candidates' => [[
            'name' => 'Invalid date', 'email' => 'invalid@example.test', 'start_date' => 'not-a-date',
        ]],
    ]))->assertSessionHasErrors('certification_candidates.0.start_date');

    expect(CertificationCandidate::count())->toBe(0);
});

test('an existing candidate without a start date stays undated on an unrelated save', function () {
    $admin = createSystemAdmin();
    [$project, $program] = createProjectWithProgram();
    $agreement = Agreement::factory()->create();
    $agreement->programs()->attach($program);
    $candidate = CertificationCandidate::factory()->create();
    $roster = AgreementCertificationCandidate::factory()->create([
        'agreement_id' => $agreement->id, 'certification_candidate_id' => $candidate->id,
    ]);

    $this->actingAs($admin)->put(route('agreements.update', $agreement), agreementStorePayload($project, $program, [
        'certification_candidates' => [['id' => $roster->id, 'certification_candidate_id' => $candidate->id]],
    ]))->assertSessionHasNoErrors()->assertRedirect();

    expect($candidate->fresh()->start_date)->toBeNull();
});

test('candidate start date changes require authority over every linked agreement', function (?string $startDate, bool $allowed) {
    [$project, $program] = createProjectWithProgram();
    [, $otherProgram] = createProjectWithProgram();
    $admin = createProgramAdmin($program);
    $agreement = Agreement::factory()->create();
    $agreement->programs()->attach($program);
    $otherAgreement = Agreement::factory()->create();
    $otherAgreement->programs()->attach($otherProgram);
    $candidate = CertificationCandidate::factory()->create(['start_date' => '2026-10-15']);
    $roster = AgreementCertificationCandidate::factory()->create([
        'agreement_id' => $agreement->id, 'certification_candidate_id' => $candidate->id,
    ]);
    AgreementCertificationCandidate::factory()->create([
        'agreement_id' => $otherAgreement->id, 'certification_candidate_id' => $candidate->id,
    ]);

    $response = $this->actingAs($admin)->put(route('agreements.update', $agreement), agreementStorePayload($project, $program, [
        'certification_candidates' => [[
            'id' => $roster->id, 'certification_candidate_id' => $candidate->id, 'start_date' => $startDate,
        ]],
    ]));

    if ($allowed) {
        $response->assertSessionHasNoErrors()->assertRedirect();
    } else {
        $response->assertSessionHasErrors('certification_candidates.0.start_date');
    }

    expect($candidate->fresh()->start_date->format('Y-m-d'))->toBe('2026-10-15');
})->with([
    'change denied' => ['2026-11-01', false],
    'clear denied' => [null, false],
    'unchanged allowed' => ['2026-10-15', true],
]);
