<?php

use App\Models\Agreement;
use App\Models\AgreementCertificationCandidate;
use App\Models\Certificate;
use App\Models\CertificateEnrollment;
use App\Models\CertificationCandidate;
use App\Models\User;
use App\Models\UserCertificationDuty;

test('agreement store creates a new candidate and a not-started enrollment', function () {
    $admin = createSystemAdmin();
    [$project, $program] = createProjectWithProgram();
    $certificate = Certificate::factory()->forProgram($program)->create();

    $payload = agreementStorePayload($project, $program, [
        'certification_candidates' => [
            0 => [
                'name' => 'Jane Doe',
                'email' => 'jane@example.test',
                'assignments' => [
                    0 => ['certificate_id' => $certificate->id],
                ],
            ],
        ],
    ]);

    $this->actingAs($admin)->post(route('agreements.store'), $payload)->assertSessionHasNoErrors();

    $candidate = CertificationCandidate::where('email', 'jane@example.test')->first();
    expect($candidate)->not->toBeNull()->and($candidate->name)->toBe('Jane Doe');

    $agreement = Agreement::firstOrFail();
    expect($agreement->certificationCandidates)->toHaveCount(1);

    $enrollment = CertificateEnrollment::where('certification_candidate_id', $candidate->id)->first();
    expect($enrollment)->not->toBeNull()
        ->and($enrollment->certificate_id)->toBe($certificate->id)
        ->and($enrollment->status->value)->toBe('not_started')
        ->and($enrollment->sponsoring_agreement_id)->toBe($agreement->id);
});

test('selecting an existing candidate on a second agreement reuses the same global enrollment', function () {
    $admin = createSystemAdmin();
    [$project, $program] = createProjectWithProgram();
    $certificate = Certificate::factory()->forProgram($program)->create();
    $candidate = CertificationCandidate::factory()->create(['email' => 'reuse@example.test']);

    $firstAgreement = Agreement::factory()->create();
    $firstAgreement->programs()->attach($program->id);
    $firstAgreement->certificationCandidates()->create(['certification_candidate_id' => $candidate->id, 'sort_order' => 0]);
    $enrollment = CertificateEnrollment::factory()->create([
        'certification_candidate_id' => $candidate->id,
        'certificate_id' => $certificate->id,
        'sponsoring_agreement_id' => $firstAgreement->id,
    ]);

    $payload = agreementStorePayload($project, $program, [
        'certification_candidates' => [
            0 => [
                'certification_candidate_id' => $candidate->id,
                'name' => $candidate->name,
                'email' => $candidate->email,
                'assignments' => [
                    0 => ['certificate_id' => $certificate->id],
                ],
            ],
        ],
    ]);

    $this->actingAs($admin)->post(route('agreements.store'), $payload)->assertSessionHasNoErrors();

    expect(CertificateEnrollment::where('certification_candidate_id', $candidate->id)->count())->toBe(1);
    $enrollment->refresh();
    expect($enrollment->sponsoring_agreement_id)->toBe($firstAgreement->id);
});

test('duplicate email across new candidate rows is rejected', function () {
    $admin = createSystemAdmin();
    [$project, $program] = createProjectWithProgram();
    CertificationCandidate::factory()->create(['email' => 'taken@example.test']);

    $payload = agreementStorePayload($project, $program, [
        'certification_candidates' => [
            0 => ['name' => 'New Person', 'email' => 'taken@example.test'],
        ],
    ]);

    $this->actingAs($admin)->post(route('agreements.store'), $payload)
        ->assertSessionHasErrors('certification_candidates.0.email');

    expect(Agreement::count())->toBe(0);
    expect(CertificationCandidate::count())->toBe(1);
});

test('completing a migrated candidate profile sets a valid email', function () {
    $admin = createSystemAdmin();
    [$project, $program] = createProjectWithProgram();
    $agreement = Agreement::factory()->create();
    $agreement->programs()->attach($program->id);
    $candidate = CertificationCandidate::factory()->withoutEmail()->create(['name' => 'Legacy Row']);
    $roster = $agreement->certificationCandidates()->create(['certification_candidate_id' => $candidate->id, 'sort_order' => 0]);

    $payload = agreementStorePayload($project, $program, [
        'certification_candidates' => [
            0 => [
                'id' => $roster->id,
                'certification_candidate_id' => $candidate->id,
                'name' => 'Legacy Row',
                'email' => 'legacy@example.test',
            ],
        ],
    ]);

    $this->actingAs($admin)->put(route('agreements.update', $agreement), $payload)->assertSessionHasNoErrors();

    $candidate->refresh();
    expect($candidate->email)->toBe('legacy@example.test');
});

test('an unrelated agreement save does not force a migrated candidate to acquire an email', function () {
    $admin = createSystemAdmin();
    [$project, $program] = createProjectWithProgram();
    $agreement = Agreement::factory()->create();
    $agreement->programs()->attach($program->id);
    $candidate = CertificationCandidate::factory()->withoutEmail()->create(['name' => 'Legacy Row']);
    $roster = $agreement->certificationCandidates()->create(['certification_candidate_id' => $candidate->id, 'sort_order' => 0]);

    $payload = agreementStorePayload($project, $program, [
        'name' => 'Renamed Agreement',
        'certification_candidates' => [
            0 => [
                'id' => $roster->id,
                'certification_candidate_id' => $candidate->id,
                'name' => 'Legacy Row',
                'email' => '',
            ],
        ],
    ]);

    $this->actingAs($admin)->put(route('agreements.update', $agreement), $payload)->assertSessionHasNoErrors();

    $candidate->refresh();
    expect($candidate->email)->toBeNull();
});

test('assigning an ineligible certificate id is rejected', function () {
    $admin = createSystemAdmin();
    [$project, $program] = createProjectWithProgram();
    [, $otherProgram] = createProjectWithProgram();
    $outOfScopeCertificate = Certificate::factory()->forProgram($otherProgram)->create();

    $payload = agreementStorePayload($project, $program, [
        'certification_candidates' => [
            0 => [
                'name' => 'Jane Doe',
                'email' => 'jane2@example.test',
                'assignments' => [
                    0 => ['certificate_id' => $outOfScopeCertificate->id],
                ],
            ],
        ],
    ]);

    $this->actingAs($admin)->post(route('agreements.store'), $payload)
        ->assertSessionHasErrors('certification_candidates.0.assignments.0.certificate_id');

    expect(Agreement::count())->toBe(0);
});

test('assigning an ineligible coach is rejected and an eligible coach is accepted', function () {
    $admin = createSystemAdmin();
    [$project, $program] = createProjectWithProgram();
    $certificate = Certificate::factory()->forProgram($program)->create();
    $ineligibleCoach = User::factory()->create();
    $eligibleCoach = createProgramViewer($program);
    UserCertificationDuty::factory()->coach()->atProgram($program)->create([
        'user_id' => $eligibleCoach->id,
    ]);

    $badPayload = agreementStorePayload($project, $program, [
        'certification_candidates' => [
            0 => [
                'name' => 'Jane Doe',
                'email' => 'jane3@example.test',
                'assignments' => [
                    0 => ['certificate_id' => $certificate->id, 'coach_user_id' => $ineligibleCoach->id],
                ],
            ],
        ],
    ]);

    $this->actingAs($admin)->post(route('agreements.store'), $badPayload)
        ->assertSessionHasErrors('certification_candidates.0.assignments.0.coach_user_id');

    $goodPayload = agreementStorePayload($project, $program, [
        'certification_candidates' => [
            0 => [
                'name' => 'Jane Doe',
                'email' => 'jane3@example.test',
                'assignments' => [
                    0 => ['certificate_id' => $certificate->id, 'coach_user_id' => $eligibleCoach->id],
                ],
            ],
        ],
    ]);

    $this->actingAs($admin)->post(route('agreements.store'), $goodPayload)->assertSessionHasNoErrors();

    $enrollment = CertificateEnrollment::where('certificate_id', $certificate->id)->first();
    expect($enrollment->supervising_coach_user_id)->toBe($eligibleCoach->id);
});

test('removing a not-started assignment deletes the enrollment but keeps the roster link and candidate', function () {
    $admin = createSystemAdmin();
    [$project, $program] = createProjectWithProgram();
    $certificate = Certificate::factory()->forProgram($program)->create();
    $agreement = Agreement::factory()->create();
    $agreement->programs()->attach($program->id);
    $candidate = CertificationCandidate::factory()->create();
    $roster = $agreement->certificationCandidates()->create(['certification_candidate_id' => $candidate->id, 'sort_order' => 0]);
    $enrollment = CertificateEnrollment::factory()->create([
        'certification_candidate_id' => $candidate->id,
        'certificate_id' => $certificate->id,
        'sponsoring_agreement_id' => $agreement->id,
    ]);

    $payload = agreementStorePayload($project, $program, [
        'certification_candidates' => [
            0 => [
                'id' => $roster->id,
                'certification_candidate_id' => $candidate->id,
                'name' => $candidate->name,
                'email' => $candidate->email,
                'assignments' => [
                    0 => ['certificate_id' => $certificate->id, '_delete' => '1'],
                ],
            ],
        ],
    ]);

    $this->actingAs($admin)->put(route('agreements.update', $agreement), $payload)->assertSessionHasNoErrors();

    expect(CertificateEnrollment::find($enrollment->id))->toBeNull();
    expect(AgreementCertificationCandidate::find($roster->id))->not->toBeNull();
    expect(CertificationCandidate::find($candidate->id))->not->toBeNull();
});

test('removing a roster row detaches the agreement only and preserves the candidate and enrollments', function () {
    $admin = createSystemAdmin();
    [$project, $program] = createProjectWithProgram();
    $certificate = Certificate::factory()->forProgram($program)->create();
    $agreement = Agreement::factory()->create();
    $agreement->programs()->attach($program->id);
    $candidate = CertificationCandidate::factory()->create();
    $roster = $agreement->certificationCandidates()->create(['certification_candidate_id' => $candidate->id, 'sort_order' => 0]);
    $enrollment = CertificateEnrollment::factory()->create([
        'certification_candidate_id' => $candidate->id,
        'certificate_id' => $certificate->id,
        'sponsoring_agreement_id' => $agreement->id,
    ]);

    $payload = agreementStorePayload($project, $program, [
        'certification_candidates' => [
            0 => ['id' => $roster->id, '_delete' => '1'],
        ],
    ]);

    $this->actingAs($admin)->put(route('agreements.update', $agreement), $payload)->assertSessionHasNoErrors();

    expect(AgreementCertificationCandidate::find($roster->id))->toBeNull();
    expect(CertificationCandidate::find($candidate->id))->not->toBeNull();
    expect(CertificateEnrollment::find($enrollment->id))->not->toBeNull();
});

test('deleting an agreement preserves sponsored enrollments with sponsorship cleared', function () {
    $admin = createSystemAdmin();
    $agreement = Agreement::factory()->create();
    $candidate = CertificationCandidate::factory()->create();
    $enrollment = CertificateEnrollment::factory()->create([
        'certification_candidate_id' => $candidate->id,
        'sponsoring_agreement_id' => $agreement->id,
    ]);

    $this->actingAs($admin)->delete(route('agreements.destroy', $agreement))->assertRedirect();

    $enrollment->refresh();
    expect($enrollment->sponsoring_agreement_id)->toBeNull();
    expect(CertificationCandidate::find($candidate->id))->not->toBeNull();
});

test('duplicating an agreement reuses the candidate id without duplicating the enrollment', function () {
    $admin = createSystemAdmin();
    [$project, $program] = createProjectWithProgram();
    $certificate = Certificate::factory()->forProgram($program)->create();
    $agreement = Agreement::factory()->current()->create();
    $agreement->programs()->attach($program->id);
    $candidate = CertificationCandidate::factory()->create();
    $agreement->certificationCandidates()->create(['certification_candidate_id' => $candidate->id, 'sort_order' => 0]);
    CertificateEnrollment::factory()->create([
        'certification_candidate_id' => $candidate->id,
        'certificate_id' => $certificate->id,
        'sponsoring_agreement_id' => $agreement->id,
    ]);

    $this->actingAs($admin)->post(route('agreements.duplicate', $agreement))->assertRedirect();

    $copy = Agreement::query()->whereKeyNot($agreement->id)->firstOrFail();
    expect($copy->certificationCandidates)->toHaveCount(1);
    expect($copy->certificationCandidates->first()->certification_candidate_id)->toBe($candidate->id);
    expect(CertificateEnrollment::where('certification_candidate_id', $candidate->id)->count())->toBe(1);
});

test('a candidate not reachable through any visible agreement cannot be selected', function () {
    [$project, $program] = createProjectWithProgram();
    $scopedAdmin = createProgramAdmin($program);

    $unreachableCandidate = CertificationCandidate::factory()->create();

    $payload = agreementStorePayload($project, $program, [
        'certification_candidates' => [
            0 => [
                'certification_candidate_id' => $unreachableCandidate->id,
                'name' => $unreachableCandidate->name,
                'email' => $unreachableCandidate->email,
            ],
        ],
    ]);

    $this->actingAs($scopedAdmin)->post(route('agreements.store'), $payload)
        ->assertSessionHasErrors('certification_candidates.0.certification_candidate_id');
});

test('repeated identical submissions do not duplicate the roster link or enrollment', function () {
    $admin = createSystemAdmin();
    [$project, $program] = createProjectWithProgram();
    $certificate = Certificate::factory()->forProgram($program)->create();
    $agreement = Agreement::factory()->create();
    $agreement->programs()->attach($program->id);
    $candidate = CertificationCandidate::factory()->create();
    $roster = $agreement->certificationCandidates()->create(['certification_candidate_id' => $candidate->id, 'sort_order' => 0]);

    $payload = agreementStorePayload($project, $program, [
        'certification_candidates' => [
            0 => [
                'id' => $roster->id,
                'certification_candidate_id' => $candidate->id,
                'name' => $candidate->name,
                'email' => $candidate->email,
                'assignments' => [
                    0 => ['certificate_id' => $certificate->id],
                ],
            ],
        ],
    ]);

    $this->actingAs($admin)->put(route('agreements.update', $agreement), $payload)->assertSessionHasNoErrors();
    $this->actingAs($admin)->put(route('agreements.update', $agreement), $payload)->assertSessionHasNoErrors();

    expect($agreement->fresh()->certificationCandidates)->toHaveCount(1);
    expect(CertificateEnrollment::where('certification_candidate_id', $candidate->id)->count())->toBe(1);
});

test('agreement create, edit, and show pages render with candidates, enrollments, and an empty state', function () {
    $admin = createSystemAdmin();
    [$project, $program] = createProjectWithProgram();
    $certificate = Certificate::factory()->forProgram($program)->create();
    $agreementWithCandidates = Agreement::factory()->create();
    $agreementWithCandidates->programs()->attach($program->id);
    $candidate = CertificationCandidate::factory()->create();
    $agreementWithCandidates->certificationCandidates()->create(['certification_candidate_id' => $candidate->id, 'sort_order' => 0]);
    CertificateEnrollment::factory()->create([
        'certification_candidate_id' => $candidate->id,
        'certificate_id' => $certificate->id,
        'sponsoring_agreement_id' => $agreementWithCandidates->id,
    ]);
    $emptyAgreement = Agreement::factory()->create();

    $this->actingAs($admin)->get(route('agreements.create'))->assertOk();
    $this->actingAs($admin)->get(route('agreements.edit', $agreementWithCandidates))->assertOk();
    $this->actingAs($admin)->get(route('agreements.show', $agreementWithCandidates))
        ->assertOk()
        ->assertSee($candidate->name)
        ->assertSee($certificate->name);
    $this->actingAs($admin)->get(route('agreements.show', $emptyAgreement))->assertOk();
});
