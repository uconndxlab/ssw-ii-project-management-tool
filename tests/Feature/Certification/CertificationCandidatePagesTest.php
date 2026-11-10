<?php

use App\Enums\AccessProfile;
use App\Models\Activity;
use App\Models\ActivityCertificationCandidate;
use App\Models\Agreement;
use App\Models\AgreementCertificationCandidate;
use App\Models\Certificate;
use App\Models\CertificateAward;
use App\Models\CertificateEnrollment;
use App\Models\CertificationCandidate;
use App\Models\RecentRecord;
use App\Models\User;
use Illuminate\Support\Facades\DB;

function candidatePageFixture(): array
{
    [$project, $program] = createProjectWithProgram();
    $actor = User::factory()->create();
    $agreement = Agreement::factory()->create();
    $agreement->programs()->attach($program);
    $agreement->users()->attach($actor);
    $candidate = CertificationCandidate::factory()->create(['name' => 'Visible Candidate']);
    AgreementCertificationCandidate::factory()->create(['agreement_id' => $agreement->id, 'certification_candidate_id' => $candidate->id]);
    $certificate = Certificate::factory()->create(['name' => 'Visible Certificate']);
    $certificate->programs()->attach($program);
    $enrollment = CertificateEnrollment::factory()->create(['certification_candidate_id' => $candidate->id, 'certificate_id' => $certificate->id, 'supervising_coach_user_id' => $actor->id]);

    return compact('actor', 'agreement', 'candidate', 'certificate', 'enrollment', 'program', 'project');
}

test('candidate directory follows agreement membership and supervision', function (string $mode) {
    $f = candidatePageFixture();
    $unrelated = CertificationCandidate::factory()->create(['name' => 'Unrelated Candidate']);
    $actor = $f['actor'];
    if ($mode !== 'member') {
        $f['agreement']->users()->detach($actor);
    }
    if ($mode === 'supervisor') {
        $actor = User::factory()->create(['is_supervisor' => true]);
        $f['actor']->update(['supervisor_id' => $actor->id]);
    }
    $this->actingAs($actor)->get(route('certification-candidates.index'))->assertOk()->assertSee($f['candidate']->name)->assertDontSee($unrelated->name);
    $this->get(route('certification-candidates.show', $f['candidate']))->assertOk();
    $this->get(route('certification-candidates.index', ['supervised' => 1]))->assertOk()->assertSee($f['candidate']->name);
    $this->get(route('certification-candidates.edit', $f['candidate']))->assertForbidden();
    $this->get(route('certification-candidates.show', $unrelated))->assertForbidden();
})->with(['member', 'coach', 'supervisor']);

test('system admin sees unrostered candidates and input users cannot open candidate pages', function () {
    $candidate = CertificationCandidate::factory()->create();
    $this->actingAs(createSystemAdmin())->get(route('certification-candidates.index'))->assertOk()->assertSee($candidate->name);
    $this->get(route('certification-candidates.show', $candidate))->assertOk();
    expect(RecentRecord::query()->where('record_type', 'certification-candidate')->where('record_id', $candidate->id)->exists())->toBeTrue();
    $this->actingAs(User::factory()->create(['access_profile' => AccessProfile::Input]))->get(route('certification-candidates.index'))->assertForbidden();
    $this->get(route('certification-candidates.show', $candidate))->assertForbidden();
});

test('candidate pages reveal hidden counts without hidden names', function () {
    $f = candidatePageFixture();
    $f['actor'] = createProgramViewer($f['program']);
    $hiddenAgreement = Agreement::factory()->create(['name' => 'Secret Agreement Name']);
    AgreementCertificationCandidate::factory()->create(['agreement_id' => $hiddenAgreement->id, 'certification_candidate_id' => $f['candidate']->id]);
    $hiddenCertificate = Certificate::factory()->create(['name' => 'Secret Certificate Name', 'program_scope_mode' => 'none']);
    CertificateEnrollment::factory()->create(['certificate_id' => $hiddenCertificate->id, 'certification_candidate_id' => $f['candidate']->id]);
    foreach (['index', 'show'] as $page) {
        $this->actingAs($f['actor'])->get(route('certification-candidates.'.$page, $page === 'show' ? $f['candidate'] : []))->assertOk()
            ->assertSee('1 other certificates you can', false)->assertSee('1 other agreements you can', false)->assertSee('+1 other')
            ->assertDontSee($hiddenCertificate->name)->assertDontSee($hiddenAgreement->name);
    }
});

test('candidate filters select the matching visible person', function (string $filter) {
    $f = candidatePageFixture();
    $other = CertificationCandidate::factory()->create(['name' => 'Other Candidate']);
    $otherAgreement = Agreement::factory()->create();
    $otherAgreement->users()->attach($f['actor']);
    AgreementCertificationCandidate::factory()->create(['agreement_id' => $otherAgreement->id, 'certification_candidate_id' => $other->id]);
    $value = match ($filter) {
        'search' => $f['candidate']->email,
        'certificate_id' => $f['certificate']->id,
        'coach_id' => $f['actor']->id,
        'supervised' => 1,
        'program_id' => $f['program']->id,
        'project_id' => $f['project']->id,
    };
    $response = $this->actingAs($f['actor'])->get(route('certification-candidates.index', [$filter => $value]))->assertOk()->assertSee($f['candidate']->name);
    $response->assertDontSee($other->name);
})->with(['search', 'certificate_id', 'coach_id', 'supervised', 'program_id', 'project_id']);

test('inactive switch sorting and htmx table work', function () {
    $candidate = CertificationCandidate::factory()->create(['active' => false]);
    $this->actingAs(createSystemAdmin())->get(route('certification-candidates.index'))->assertOk()->assertDontSee($candidate->name);
    $this->get(route('certification-candidates.index', ['include_inactive' => 1, 'sort' => 'start_date', 'direction' => 'desc']))->assertOk()->assertSee($candidate->name);
    $this->get(route('certification-candidates.index'), ['HX-Request' => 'true'])->assertOk()->assertViewIs('certification-candidates.partials.table')->assertDontSee('<html', false);
});

test('editing shared details requires update access to every agreement', function (bool $shared) {
    $f = candidatePageFixture();
    $admin = createProgramAdmin($f['program']);
    if ($shared) {
        AgreementCertificationCandidate::factory()->create(['certification_candidate_id' => $f['candidate']->id]);
    }
    $response = $this->actingAs($admin)->get(route('certification-candidates.edit', $f['candidate']));
    $shared ? $response->assertForbidden() : $response->assertOk();
    $response = $this->put(route('certification-candidates.update', $f['candidate']), ['name' => 'Updated Person', 'email' => ' UPDATED@example.test ', 'active' => 1, 'phone' => '555-1234', 'start_date' => '2026-10-01', 'notes' => 'Shared details']);
    if ($shared) {
        $response->assertForbidden();
        expect($f['candidate']->fresh()->name)->toBe('Visible Candidate');
    } else {
        $response->assertSessionHasNoErrors()->assertRedirect(route('certification-candidates.show', $f['candidate']));
        expect($f['candidate']->fresh()->email)->toBe('updated@example.test');
    }
})->with([false, true]);

test('candidate email is required unique ignoring itself and normalized', function () {
    $candidate = CertificationCandidate::factory()->create();
    $other = CertificationCandidate::factory()->create();
    $this->actingAs(createSystemAdmin())->put(route('certification-candidates.update', $candidate), ['name' => 'Person', 'email' => strtoupper($other->email), 'active' => 1])->assertSessionHasErrors('email');
    $this->put(route('certification-candidates.update', $candidate), ['name' => 'Person', 'email' => '', 'active' => 1])->assertSessionHasErrors('email');
    $this->put(route('certification-candidates.update', $candidate), ['name' => 'Person', 'email' => strtoupper($candidate->email), 'active' => 1])->assertSessionHasNoErrors();
});

test('directory query count stays bounded when candidates are added', function () {
    $f = candidatePageFixture();
    $this->actingAs(createSystemAdmin());
    $count = function (): int {
        DB::enableQueryLog();
        DB::flushQueryLog();
        $this->get(route('certification-candidates.index'), ['HX-Request' => 'true'])->assertOk();
        $count = count(DB::getQueryLog());
        DB::disableQueryLog();

        return $count;
    };
    $before = $count();
    foreach (range(1, 8) as $index) {
        $candidate = CertificationCandidate::factory()->create();
        AgreementCertificationCandidate::factory()->create(['agreement_id' => $f['agreement']->id, 'certification_candidate_id' => $candidate->id]);
        CertificateEnrollment::factory()->create(['certificate_id' => $f['certificate']->id, 'certification_candidate_id' => $candidate->id]);
    }
    expect($count())->toBeLessThanOrEqual($before + 1);
});

test('candidate activity list includes only visible tagged activities', function () {
    $f = candidatePageFixture();
    $actor = createProgramViewer($f['program']);
    $visible = Activity::factory()->create(['engagement_date' => '2026-11-01']);
    $visible->programs()->attach($f['program']);
    $hidden = Activity::factory()->create(['engagement_date' => '2026-11-02']);
    foreach ([$visible, $hidden] as $activity) {
        ActivityCertificationCandidate::factory()->create(['activity_id' => $activity->id, 'certification_candidate_id' => $f['candidate']->id]);
    }
    $this->actingAs($actor)->get(route('certification-candidates.show', $f['candidate']))->assertOk()
        ->assertViewHas('recentActivities', fn ($activities) => $activities->modelKeys() === [$visible->id]);
});

test('candidate award history shows the revoked date but not the private reason', function () {
    $f = candidatePageFixture();
    CertificateAward::factory()->create([
        'certificate_enrollment_id' => $f['enrollment']->id,
        'revoked_at' => now(),
        'revoke_reason' => 'Private revocation reason',
    ]);

    $this->actingAs($f['actor'])->get(route('certification-candidates.show', $f['candidate']))
        ->assertOk()
        ->assertSee(now()->format('M j, Y'))
        ->assertDontSee('Private revocation reason');
});

test('candidate rows offer view to everyone and edit only to people who can edit', function () {
    $f = candidatePageFixture();

    $this->actingAs($f['actor'])->get(route('certification-candidates.index'))
        ->assertOk()->assertSee('View candidate')->assertDontSee('Edit candidate');

    $this->actingAs(createSystemAdmin())->get(route('certification-candidates.index'))
        ->assertOk()->assertSee('View candidate')->assertSee('Edit candidate');
});
