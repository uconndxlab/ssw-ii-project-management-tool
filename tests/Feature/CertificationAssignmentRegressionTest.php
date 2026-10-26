<?php

use App\Enums\CertificateEnrollmentStatus;
use App\Enums\CertificateRequirementPhase;
use App\Models\Agreement;
use App\Models\AgreementCertificationCandidate;
use App\Models\Certificate;
use App\Models\CertificateEnrollment;
use App\Models\CertificationCandidate;
use App\Models\User;
use App\Models\UserCertificationDuty;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

test('a retired certificate does not block saving its existing assignment', function () {
    $admin = createSystemAdmin();
    [$project, $program] = createProjectWithProgram();
    $agreement = Agreement::factory()->create();
    $agreement->programs()->attach($program);
    $candidate = CertificationCandidate::factory()->create();
    $roster = AgreementCertificationCandidate::factory()->create([
        'agreement_id' => $agreement->id, 'certification_candidate_id' => $candidate->id,
    ]);
    $certificate = Certificate::factory()->forProgram($program)->create(['active' => false, 'retired_at' => now()]);
    $enrollment = CertificateEnrollment::factory()->create([
        'certification_candidate_id' => $candidate->id, 'certificate_id' => $certificate->id,
        'sponsoring_agreement_id' => $agreement->id,
    ]);

    $this->actingAs($admin)->put(route('agreements.update', $agreement), agreementStorePayload($project, $program, [
        'certification_candidates' => [[
            'id' => $roster->id, 'certification_candidate_id' => $candidate->id,
            'name' => $candidate->name, 'email' => $candidate->email,
            'assignments' => [['certificate_id' => $certificate->id]],
        ]],
    ]))->assertSessionHasNoErrors()->assertRedirect();

    $this->assertModelExists($enrollment);
});

test('agreement edit hides enrollments from an unrelated program', function () {
    [$project, $program] = createProjectWithProgram();
    [, $otherProgram] = createProjectWithProgram();
    $admin = createProgramAdmin($program);
    $agreement = Agreement::factory()->create();
    $agreement->programs()->attach($program);
    $candidate = CertificationCandidate::factory()->create();
    AgreementCertificationCandidate::factory()->create([
        'agreement_id' => $agreement->id, 'certification_candidate_id' => $candidate->id,
    ]);
    $privateCertificate = Certificate::factory()->forProgram($otherProgram)->create(['name' => 'Private other-program credential']);
    CertificateEnrollment::factory()->create([
        'certification_candidate_id' => $candidate->id, 'certificate_id' => $privateCertificate->id,
    ]);

    $this->actingAs($admin)->get(route('agreements.edit', $agreement))
        ->assertOk()->assertDontSee($privateCertificate->name);
});

test('a scoped admin cannot delete an orphaned enrollment outside their program', function () {
    [$project, $program] = createProjectWithProgram();
    [, $otherProgram] = createProjectWithProgram();
    $admin = createProgramAdmin($program);
    $agreement = Agreement::factory()->create();
    $agreement->programs()->attach($program);
    $candidate = CertificationCandidate::factory()->create();
    $roster = AgreementCertificationCandidate::factory()->create([
        'agreement_id' => $agreement->id, 'certification_candidate_id' => $candidate->id,
    ]);
    $certificate = Certificate::factory()->forProgram($otherProgram)->create();
    $enrollment = CertificateEnrollment::factory()->create([
        'certification_candidate_id' => $candidate->id, 'certificate_id' => $certificate->id,
        'sponsoring_agreement_id' => null,
    ]);

    $this->actingAs($admin)->put(route('agreements.update', $agreement), agreementStorePayload($project, $program, [
        'certification_candidates' => [[
            'id' => $roster->id, 'certification_candidate_id' => $candidate->id,
            'name' => $candidate->name, 'email' => $candidate->email,
            'assignments' => [['certificate_id' => $certificate->id, '_delete' => '1']],
        ]],
    ]))->assertSessionHasErrors('certification_candidates.0.assignments.0.certificate_id');

    $this->assertModelExists($enrollment);
});

test('global enrollment removal requires authority over every linked agreement', function () {
    [$project, $program] = createProjectWithProgram();
    [, $otherProgram] = createProjectWithProgram();
    $admin = createProgramAdmin($program);
    $agreement = Agreement::factory()->create();
    $agreement->programs()->attach($program);
    $otherAgreement = Agreement::factory()->create();
    $otherAgreement->programs()->attach($otherProgram);
    $candidate = CertificationCandidate::factory()->create();
    $roster = AgreementCertificationCandidate::factory()->create([
        'agreement_id' => $agreement->id, 'certification_candidate_id' => $candidate->id,
    ]);
    AgreementCertificationCandidate::factory()->create([
        'agreement_id' => $otherAgreement->id, 'certification_candidate_id' => $candidate->id,
    ]);
    $certificate = Certificate::factory()->forProgram($program)->create();
    $enrollment = CertificateEnrollment::factory()->create([
        'certification_candidate_id' => $candidate->id, 'certificate_id' => $certificate->id,
        'sponsoring_agreement_id' => $agreement->id,
    ]);

    $this->actingAs($admin)->put(route('agreements.update', $agreement), agreementStorePayload($project, $program, [
        'certification_candidates' => [[
            'id' => $roster->id, 'certification_candidate_id' => $candidate->id,
            'name' => $candidate->name, 'email' => $candidate->email,
            'assignments' => [['certificate_id' => $certificate->id, '_delete' => '1']],
        ]],
    ]))->assertSessionHasErrors('certification_candidates.0.assignments.0.certificate_id');

    $this->assertModelExists($enrollment);
});

test('a coach duty in another agreement program cannot qualify for the selected certificate', function () {
    $admin = createSystemAdmin();
    [$project, $program] = createProjectWithProgram();
    [, $otherProgram] = createProjectWithProgram();
    $project->programs()->attach($otherProgram);
    $coach = createProgramViewer($program);
    UserCertificationDuty::factory()->coach()->atProgram($program)->create(['user_id' => $coach->id]);
    $certificate = Certificate::factory()->forProgram($otherProgram)->create();

    $this->actingAs($admin)->post(route('agreements.store'), agreementStorePayload($project, $program, [
        'program_ids' => [$program->id, $otherProgram->id],
        'certification_candidates' => [[
            'name' => 'Candidate', 'email' => 'candidate@example.test',
            'assignments' => [['certificate_id' => $certificate->id, 'coach_user_id' => $coach->id]],
        ]],
    ]))->assertSessionHasErrors('certification_candidates.0.assignments.0.coach_user_id');
});

test('a nullable candidate roster can be saved without a server error', function () {
    $admin = createSystemAdmin();
    [$project, $program] = createProjectWithProgram();

    $this->actingAs($admin)->post(route('agreements.store'), agreementStorePayload($project, $program, [
        'certification_candidates' => null,
    ]))->assertSessionHasNoErrors()->assertRedirect();
});

test('malformed candidate rows return validation errors', function ($rows, $error) {
    $admin = createSystemAdmin();
    [$project, $program] = createProjectWithProgram();

    $this->actingAs($admin)->post(route('agreements.store'), agreementStorePayload($project, $program, [
        'certification_candidates' => $rows,
    ]))->assertSessionHasErrors($error);
})->with([
    'scalar candidate row' => [['invalid'], 'certification_candidates.0'],
    'email without name' => [[['email' => 'candidate@example.test']], 'certification_candidates.0.name'],
    'array instead of name' => [[['name' => ['invalid']]], 'certification_candidates.0.name'],
    'array instead of email' => [[['name' => 'Candidate', 'email' => ['invalid']]], 'certification_candidates.0.email'],
]);

test('a roster id from another agreement is rejected for an ordinary update', function () {
    $admin = createSystemAdmin();
    [$project, $program] = createProjectWithProgram();
    $agreement = Agreement::factory()->create();
    $agreement->programs()->attach($program);
    $foreignRoster = AgreementCertificationCandidate::factory()->create();
    $candidate = $foreignRoster->candidate;

    $this->actingAs($admin)->put(route('agreements.update', $agreement), agreementStorePayload($project, $program, [
        'certification_candidates' => [[
            'id' => $foreignRoster->id, 'certification_candidate_id' => $candidate->id,
            'name' => $candidate->name, 'email' => $candidate->email,
        ]],
    ]))->assertSessionHasErrors('certification_candidates.0.id');

    expect($agreement->certificationCandidates()->count())->toBe(0);
});

test('a submitted email collision between two existing identities is rejected atomically', function () {
    $admin = createSystemAdmin();
    [$project, $program] = createProjectWithProgram();
    $agreement = Agreement::factory()->create();
    $agreement->programs()->attach($program);
    $first = AgreementCertificationCandidate::factory()->create(['agreement_id' => $agreement->id]);
    $second = AgreementCertificationCandidate::factory()->create(['agreement_id' => $agreement->id]);
    $originalEmails = [$first->candidate->email, $second->candidate->email];

    $this->actingAs($admin)->put(route('agreements.update', $agreement), agreementStorePayload($project, $program, [
        'certification_candidates' => collect([$first, $second])->map(fn ($roster) => [
            'id' => $roster->id, 'certification_candidate_id' => $roster->candidate->id,
            'name' => $roster->candidate->name, 'email' => 'shared@example.test',
        ])->all(),
    ]))->assertSessionHasErrors('certification_candidates.1.email');

    expect([$first->candidate->fresh()->email, $second->candidate->fresh()->email])->toBe($originalEmails);
});

test('new assignments cannot use inactive candidates', function () {
    $admin = createSystemAdmin();
    [$project, $program] = createProjectWithProgram();
    $candidate = CertificationCandidate::factory()->create(['active' => false]);

    $this->actingAs($admin)->post(route('agreements.store'), agreementStorePayload($project, $program, [
        'certification_candidates' => [['certification_candidate_id' => $candidate->id]],
    ]))->assertSessionHasErrors('certification_candidates.0.certification_candidate_id');
});

test('editing an agreement preserves an inactive roster candidate in its selector', function () {
    $admin = createSystemAdmin();
    [, $program] = createProjectWithProgram();
    $agreement = Agreement::factory()->create();
    $agreement->programs()->attach($program);
    $candidate = CertificationCandidate::factory()->create(['active' => false]);
    AgreementCertificationCandidate::factory()->create([
        'agreement_id' => $agreement->id, 'certification_candidate_id' => $candidate->id,
    ]);

    $this->actingAs($admin)->get(route('agreements.edit', $agreement))->assertOk()
        ->assertViewHas('certificationCandidateOptions', fn ($options) => $options->contains('id', $candidate->id));
});

test('a coach with system-wide visibility remains selectable without explicit program privileges', function () {
    $admin = createSystemAdmin();
    [, $program] = createProjectWithProgram();
    $agreement = Agreement::factory()->create();
    $agreement->programs()->attach($program);
    $coach = createSystemAdmin();
    UserCertificationDuty::factory()->coach()->atProgram($program)->create(['user_id' => $coach->id]);

    expect($coach->access()->viewProgramIds())->toBe([]);
    $this->actingAs($admin)->get(route('agreements.edit', $agreement))->assertOk()
        ->assertViewHas('eligibleCoaches', fn ($coaches) => $coaches->firstWhere('id', $coach->id)?->getAttribute('can_view_assignment_agreement') === true);
});

test('updating unrelated user fields preserves certification duties', function () {
    $admin = createSystemAdmin();
    $user = User::factory()->create();
    $duty = UserCertificationDuty::factory()->coach()->atSystem()->create(['user_id' => $user->id]);

    $this->actingAs($admin)->put(route('admin.users.update', $user), [
        'name' => $user->name, 'email' => $user->email, 'access_profile' => 'member',
        'active' => '1', 'program_scope_mode' => 'all',
    ])->assertSessionHasNoErrors();

    expect($duty->fresh()->revoked_at)->toBeNull();
});

test('a program admin can clear the last selected duty program without revoking other duties', function () {
    [$project, $program] = createProjectWithProgram();
    $admin = createProgramAdmin($program);
    $user = User::factory()->create();
    $user->programs()->attach($program);
    $coachDuty = UserCertificationDuty::factory()->coach()->atProgram($program)->create(['user_id' => $user->id]);
    $managerDuty = UserCertificationDuty::factory()->manager()->atProgram($program)->create(['user_id' => $user->id]);

    $this->actingAs($admin)->get(route('admin.users.edit', $user))->assertOk()
        ->assertSee('name="duty_coach_present"', false);
    $this->actingAs($admin)->put(route('admin.users.update', $user), [
        'name' => $user->name, 'email' => $user->email, 'access_profile' => 'member', 'active' => '1',
        'program_scope_mode' => 'specific', 'project_ids' => [$project->id], 'program_ids' => [$program->id],
        'duty_coach_present' => '1',
    ])->assertSessionHasNoErrors();

    expect($coachDuty->fresh()->revoked_at)->not->toBeNull()
        ->and($coachDuty->fresh()->revoked_by_user_id)->toBe($admin->id)
        ->and($managerDuty->fresh()->revoked_at)->toBeNull();
});

test('invalid duty inputs fail before changing a user', function () {
    $admin = createSystemAdmin();
    $user = User::factory()->create();

    $this->actingAs($admin)->put(route('admin.users.update', $user), [
        'name' => 'Must not be saved', 'email' => $user->email, 'access_profile' => 'member',
        'active' => '1', 'program_scope_mode' => 'all', 'duty_coach_program_ids' => 'invalid',
    ])->assertSessionHasErrors('duty_coach_program_ids');

    expect($user->fresh()->name)->toBe($user->name);
});

test('an authorized coach correction preserves the existing enrollment and its sponsorship', function () {
    $admin = createSystemAdmin();
    [$project, $program] = createProjectWithProgram();
    $agreement = Agreement::factory()->create();
    $agreement->programs()->attach($program);
    $roster = AgreementCertificationCandidate::factory()->create(['agreement_id' => $agreement->id]);
    $certificate = Certificate::factory()->forProgram($program)->create();
    $enrollment = CertificateEnrollment::factory()->create([
        'certification_candidate_id' => $roster->candidate->id, 'certificate_id' => $certificate->id,
        'sponsoring_agreement_id' => $agreement->id,
    ]);
    $coach = createProgramViewer($program);
    UserCertificationDuty::factory()->coach()->atProgram($program)->create(['user_id' => $coach->id]);

    $this->actingAs($admin)->put(route('agreements.update', $agreement), agreementStorePayload($project, $program, [
        'certification_candidates' => [[
            'id' => $roster->id, 'certification_candidate_id' => $roster->candidate->id,
            'assignments' => [[
                'enrollment_id' => $enrollment->id, 'certificate_id' => $certificate->id, 'coach_user_id' => $coach->id,
            ]],
        ]],
    ]))->assertSessionHasNoErrors();

    expect($enrollment->fresh()->supervising_coach_user_id)->toBe($coach->id)
        ->and($enrollment->fresh()->sponsoring_agreement_id)->toBe($agreement->id)
        ->and(CertificateEnrollment::count())->toBe(1);
});

test('coach correction cannot mutate an assignment beyond its initial unstarted cycle', function (array $attributes) {
    $admin = createSystemAdmin();
    [$project, $program] = createProjectWithProgram();
    $agreement = Agreement::factory()->create();
    $agreement->programs()->attach($program);
    $roster = AgreementCertificationCandidate::factory()->create(['agreement_id' => $agreement->id]);
    $certificate = Certificate::factory()->forProgram($program)->create();
    $enrollment = CertificateEnrollment::factory()->create(array_merge([
        'certification_candidate_id' => $roster->candidate->id, 'certificate_id' => $certificate->id,
        'sponsoring_agreement_id' => $agreement->id, 'supervising_coach_user_id' => User::factory(),
    ], $attributes));
    $coachId = $enrollment->supervising_coach_user_id;

    $this->actingAs($admin)->put(route('agreements.update', $agreement), agreementStorePayload($project, $program, [
        'certification_candidates' => [[
            'id' => $roster->id, 'certification_candidate_id' => $roster->candidate->id,
            'assignments' => [[
                'enrollment_id' => $enrollment->id, 'certificate_id' => $certificate->id, 'coach_user_id' => null,
            ]],
        ]],
    ]))->assertSessionHasErrors('certification_candidates.0.assignments.0.coach_user_id');

    expect($enrollment->fresh()->supervising_coach_user_id)->toBe($coachId);
})->with([
    'in progress' => [['status' => CertificateEnrollmentStatus::InProgress]],
    'renewal' => [['phase' => CertificateRequirementPhase::Renewal]],
    'later cycle' => [['cycle' => 2]],
]);

test('reusing an enrollment from a new roster row cannot silently clear its coach', function () {
    $admin = createSystemAdmin();
    [$project, $program] = createProjectWithProgram();
    $certificate = Certificate::factory()->forProgram($program)->create();
    $enrollment = CertificateEnrollment::factory()->create(['certificate_id' => $certificate->id, 'supervising_coach_user_id' => User::factory()]);
    $coachId = $enrollment->supervising_coach_user_id;

    $this->actingAs($admin)->post(route('agreements.store'), agreementStorePayload($project, $program, [
        'certification_candidates' => [[
            'certification_candidate_id' => $enrollment->certification_candidate_id,
            'assignments' => [['certificate_id' => $certificate->id, 'coach_user_id' => null]],
        ]],
    ]))->assertSessionHasNoErrors();

    expect($enrollment->fresh()->supervising_coach_user_id)->toBe($coachId);
});

test('an existing roster link cannot be reassigned to a different person', function () {
    $admin = createSystemAdmin();
    [$project, $program] = createProjectWithProgram();
    $agreement = Agreement::factory()->create();
    $agreement->programs()->attach($program);
    $roster = AgreementCertificationCandidate::factory()->create(['agreement_id' => $agreement->id]);
    $otherCandidate = CertificationCandidate::factory()->create();

    $this->actingAs($admin)->put(route('agreements.update', $agreement), agreementStorePayload($project, $program, [
        'certification_candidates' => [[
            'id' => $roster->id, 'certification_candidate_id' => $otherCandidate->id,
        ]],
    ]))->assertSessionHasErrors('certification_candidates.0.certification_candidate_id');

    expect($roster->fresh()->certification_candidate_id)->toBe($roster->certification_candidate_id);
});

test('legacy candidate migration preserves separate people and roster metadata', function () {
    $schema = 'cert_backfill_'.strtolower(Str::random(12));
    $searchPath = DB::selectOne("select current_setting('search_path') as path")->path;
    DB::statement("CREATE SCHEMA {$schema}");

    try {
        DB::statement("SET LOCAL search_path TO {$schema}");
        Schema::create('agreements', function ($table) {
            $table->id();
        });
        Schema::create('agreement_certification_candidates', function ($table) {
            $table->id();
            $table->foreignId('agreement_id')->constrained();
            $table->unsignedBigInteger('program_id')->nullable();
            $table->string('name');
            $table->text('notes')->nullable();
            $table->timestamps();
        });
        DB::table('agreements')->insert([['id' => 1], ['id' => 2]]);
        DB::table('agreement_certification_candidates')->insert([
            ['id' => 17, 'agreement_id' => 1, 'name' => 'Same name', 'program_id' => 8, 'notes' => 'Keep note'],
            ['id' => 21, 'agreement_id' => 1, 'name' => 'Same name', 'program_id' => null, 'notes' => null],
            ['id' => 35, 'agreement_id' => 2, 'name' => 'Another person', 'program_id' => 9, 'notes' => 'Other note'],
        ]);

        foreach ([
            '2026_10_23_120000_create_certification_candidates_table.php',
            '2026_10_23_120100_convert_agreement_certification_candidates_to_roster_links.php',
            '2026_10_23_120300_add_unique_roster_link_constraint_to_agreement_certification_candidates.php',
        ] as $file) {
            (require database_path('migrations/'.$file))->up();
        }

        $roster = DB::table('agreement_certification_candidates')->orderBy('id')->get();
        expect($roster->pluck('id')->all())->toBe([17, 21, 35])
            ->and($roster->pluck('sort_order')->all())->toBe([0, 1, 0])
            ->and($roster->pluck('program_id')->all())->toBe([8, null, 9])
            ->and($roster->pluck('notes')->all())->toBe(['Keep note', null, 'Other note'])
            ->and($roster->pluck('certification_candidate_id')->unique())->toHaveCount(3)
            ->and(DB::table('certification_candidates')->orderBy('id')->pluck('name')->all())
            ->toBe(['Same name', 'Same name', 'Another person'])
            ->and(DB::table('certification_candidates')->whereNotNull('email')->count())->toBe(0);
    } finally {
        DB::statement("select set_config('search_path', ?, true)", [$searchPath]);
        DB::statement("DROP SCHEMA {$schema} CASCADE");
    }
});
