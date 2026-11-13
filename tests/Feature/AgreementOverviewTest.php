<?php

use App\Enums\DeliverableStatus;
use App\Models\Activity;
use App\Models\ActivityType;
use App\Models\Agreement;
use App\Models\AgreementCertificationCandidate;
use App\Models\AgreementDeliverable;
use App\Models\Certificate;
use App\Models\CertificateEnrollment;
use App\Models\ContactFamily;
use App\Models\Organization;
use App\Models\Team;
use App\Models\User;
use App\Support\AgreementDeliverableDisplay;
use App\Support\AgreementOverview;
use Illuminate\Support\Collection;

function overviewProgress(Agreement $agreement): Collection
{
    $agreement->load(['users', 'teams.users', 'deliverables.users', 'deliverables.teams', 'deliverables.contributions', 'deliverables.contactFamily', 'deliverables.activityType', 'deliverables.program']);

    return AgreementDeliverableDisplay::buildGroupedProgress($agreement);
}

it('counts active deliverables once and keeps partly unassigned items in their status bucket', function () {
    $this->travelTo(now()->setDate(2026, 7, 1));
    $agreement = Agreement::factory()->create(['start_date' => '2026-01-01', 'end_date' => '2026-12-31']);
    AgreementDeliverable::factory()->for($agreement)->completionByUser()->create(['target_quantity' => 10]);
    AgreementDeliverable::factory()->for($agreement)->create(['target_quantity' => null]);
    AgreementDeliverable::factory()->for($agreement)->retired()->create(['target_quantity' => 10]);
    $user = User::factory()->create();
    $agreement->users()->attach($user);
    $partial = AgreementDeliverable::factory()->for($agreement)->completionByUser()->create(['target_quantity' => 10]);
    $partial->users()->attach($user, ['target_quantity' => 5]);

    $summary = (new AgreementOverview($agreement, $user))->deliverableSummary(overviewProgress($agreement));

    expect($summary['total'])->toBe(3);
    expect(array_column($summary['buckets'], 'count', 'key'))->toBe(['off_track' => 1, 'no_assignees' => 1, 'no_target' => 1]);
    expect(array_sum(array_column($summary['buckets'], 'count')))->toBe(3);
    expect($summary['blocks'])->toBe([['tone' => 'danger', 'count' => 1], ['tone' => 'warning', 'count' => 1], ['tone' => 'neutral', 'count' => 1]]);
});

it('selects attention round robin but displays severity groups with most behind first', function () {
    $agreement = Agreement::factory()->create();
    $family = ContactFamily::factory()->create(['name' => 'Training']);
    $items = collect([
        ['Zulu', DeliverableStatus::OffTrack, 10],
        ['Alpha', DeliverableStatus::OffTrack, 10],
        ['Later', DeliverableStatus::OffTrack, 20],
        ['Warning', DeliverableStatus::NeedsAttention, 60],
        [null, null, 0],
        ['Assigned null', null, 0],
        ['No progress', DeliverableStatus::NoProgressMade, 0],
    ])->map(function (array $input) use ($agreement, $family) {
        [$name, $status, $percent] = $input;
        $deliverable = AgreementDeliverable::factory()->for($agreement)->for($family, 'contactFamily')->make([
            'id' => $percent + ($name === 'Alpha' ? 1 : 2),
        ]);
        $deliverable->setRelation('activityType', $name ? ActivityType::factory()->make(['name' => $name]) : null);
        $deliverable->activity_type_id = $name ? 1 : null;
        $deliverable->setRelation('contactFamily', $family);

        return ['deliverable' => $deliverable, 'status' => $status, 'percent' => $percent, 'has_target' => true, 'target' => 10, 'is_individual' => true, 'rollup_on_track' => ['total' => 0], 'allocation_notices' => ['unassigned_remainder' => $name === 'Assigned null' ? 5 : 10], 'unit_label' => 'Hours', 'metric_summary' => 'Time', 'counted_completed_value' => 1.5];
    });
    $groups = collect([['activity_groups' => collect([['program_groups' => collect([['deliverables' => $items]])]])]]);

    $summary = (new AgreementOverview($agreement, User::factory()->create()))->deliverableSummary($groups);

    expect(array_column($summary['attention'], 'title'))->toBe(['Alpha', 'Zulu', 'Warning', 'Any Training activity']);
    expect(array_column($summary['attention'], 'bucket'))->toBe(['off_track', 'off_track', 'needs_attention', 'no_assignees']);
    expect($summary['attention'][0]['meta'])->toBe('Training · Time · 1.5 of 10 hours');
    expect($summary['attention'][3]['meta'])->toBe('Training · Time · 10 hours unassigned');
});

it('explains entirely inapplicable statuses', function (?string $start, ?string $end, string $note) {
    $this->travelTo(now()->setDate(2026, 7, 1));
    $agreement = Agreement::factory()->create(['start_date' => $start, 'end_date' => $end]);
    AgreementDeliverable::factory()->for($agreement)->create(['target_quantity' => 5]);

    $summary = (new AgreementOverview($agreement, User::factory()->create()))->deliverableSummary(overviewProgress($agreement));

    expect($summary['note'])->toBe($note);
})->with([[null, null, 'Statuses need start and end dates.'], ['2027-01-01', '2027-12-31', 'Statuses start on Jan 1, 2027.']]);

it('stretches the monthly timeline and includes restricted activities in its total', function () {
    $this->travelTo(now()->setDate(2026, 7, 15));
    $agreement = Agreement::factory()->create(['start_date' => '2026-02-01', 'end_date' => '2026-07-31', 'extension_end_date' => '2026-08-31']);
    $user = User::factory()->create();
    Activity::factory()->for($user)->hasAttached($agreement)->create(['engagement_date' => '2026-01-01']);
    Activity::factory()->hasAttached($agreement)->create(['engagement_date' => '2026-07-15']);
    $overview = new AgreementOverview($agreement, $user);

    $timeline = $overview->activityTimeline();

    expect($timeline['total'])->toBe($agreement->activities()->count())->toBe(2);
    expect($timeline['granularity'])->toBe('month');
    expect(array_sum(array_column($timeline['buckets'], 'count')))->toBe(2);
    expect(array_column($timeline['buckets'], 'key'))->toBe(['2026-01', '2026-02', '2026-03', '2026-04', '2026-05', '2026-06', '2026-07', '2026-08']);
    expect($timeline['today_offset'])->toBe(195 / 243); // Jan 1 → Jul 15 of a Jan 1 → Sep 1 range
    expect($overview->restrictedActivityCount())->toBe(1)->toBe($agreement->activities()->count() - $agreement->activities()->visibleTo($user)->count());
});

it('uses monthly buckets through 24 months and quarterly buckets beyond', function (string $end, string $granularity, int $count) {
    $agreement = Agreement::factory()->create(['start_date' => '2025-01-01', 'end_date' => $end]);
    $timeline = (new AgreementOverview($agreement, User::factory()->create()))->activityTimeline();

    expect($timeline['granularity'])->toBe($granularity);
    expect($timeline['buckets'])->toHaveCount($count);
})->with([['2026-12-31', 'month', 24], ['2027-01-31', 'quarter', 9]]);

it('uses activity dates when agreement dates are missing and returns empty buckets without either', function () {
    $agreement = Agreement::factory()->create();
    $overview = new AgreementOverview($agreement, User::factory()->create());
    expect($overview->activityTimeline()['buckets'])->toBe([]);
    Activity::factory()->hasAttached($agreement)->create(['engagement_date' => '2026-06-01']);

    expect(array_column($overview->activityTimeline()['buckets'], 'key'))->toBe(['2026-06']);
});

it('deduplicates staff with PIs first and preserves all agreement team names', function () {
    $agreement = Agreement::factory()->create();
    $pi = User::factory()->create(['name' => 'Zulu']);
    $other = User::factory()->create(['name' => 'Alpha']);
    $teamOnly = User::factory()->create(['name' => 'Beta']);
    $agreement->users()->attach([$pi->id, $other->id]);
    $agreement->principalInvestigators()->attach($pi);
    foreach (['Zulu team', 'Alpha team'] as $name) {
        $team = Team::factory()->hasAttached($pi)->hasAttached($teamOnly)->create(['name' => $name]);
        $agreement->teams()->attach($team);
    }
    $agreement->load(['users', 'teams.users', 'principalInvestigators']);

    $staff = (new AgreementOverview($agreement, $other))->staff();

    expect($staff->pluck('user.id')->all())->toBe([$pi->id, $other->id, $teamOnly->id]);
    expect($staff[0]['teams'])->toBe(['Alpha team', 'Zulu team']);
    expect($staff[0]['is_pi'])->toBeTrue();
    expect($staff[1]['teams'])->toBe([]);
    expect($staff[2]['teams'])->toBe(['Alpha team', 'Zulu team']);
});

it('keeps the overview on the agreement window when deliverables are filtered', function (array $query) {
    $this->travelTo(now()->setDate(2026, 7, 1));
    $agreement = Agreement::factory()->create(['start_date' => '2026-01-01', 'end_date' => '2026-12-31']);
    AgreementDeliverable::factory()->for($agreement)->create(['target_quantity' => 20]);

    $this->actingAs(createSystemAdmin())->get(route('agreements.show', [$agreement, ...$query]))->assertOk()
        ->assertViewHas('overviewDeliverables', fn ($summary) => $summary['buckets'] === [['key' => 'off_track', 'label' => 'Off track', 'tone' => 'danger', 'count' => 1]])
        ->assertViewHas('deliverableGroups', fn ($groups) => $groups->first()['activity_groups']->first()['program_groups']->first()['deliverables']->first()['status'] === DeliverableStatus::OnTrack);
})->with([
    'future window' => [['from' => '2027-01-01', 'to' => '2027-12-31']],
    'only from' => [['from' => '2027-01-01']],
]);

it('classifies assigned users with no personal targets as having no scored assignees', function () {
    $agreement = Agreement::factory()->current()->create();
    $user = User::factory()->create();
    $agreement->users()->attach($user);
    $deliverable = AgreementDeliverable::factory()->for($agreement)->completionByUser()->create(['target_quantity' => 10]);
    $deliverable->users()->attach($user, ['target_quantity' => null]);

    $summary = (new AgreementOverview($agreement, $user))->deliverableSummary(overviewProgress($agreement));

    expect($summary['buckets'])->toBe([['key' => 'no_assignees', 'label' => 'No assignees', 'tone' => 'warning', 'count' => 1]]);
});

it('sorts organizations by payor then recipient then name ignoring case', function () {
    $agreement = Agreement::factory()->create();
    foreach ([['a other', []], ['Z recipient', ['recipient' => true]], ['z payor', ['payor_source' => true]], ['A payor', ['payor_source' => true, 'recipient' => true]]] as [$name, $pivot]) {
        $agreement->organizations()->attach(Organization::factory()->create(['name' => $name]), $pivot);
    }
    $agreement->load('organizations');

    expect((new AgreementOverview($agreement, User::factory()->create()))->organizations()->pluck('name')->all())->toBe(['A payor', 'z payor', 'Z recipient', 'a other']);
});

it('counts only invisible certificates applicable to the agreement scope', function () {
    [, $program] = createProjectWithProgram();
    [, $otherProgram] = createProjectWithProgram();
    [, $outsideProgram] = createProjectWithProgram();
    $viewer = createProgramViewer($program);
    $agreement = Agreement::factory()->hasAttached($program)->hasAttached($otherProgram)->create();
    $roster = AgreementCertificationCandidate::factory()->for($agreement)->create();
    $visible = Certificate::factory()->forProgram($program)->create();
    $hidden = Certificate::factory()->forProgram($otherProgram)->create();
    $outside = Certificate::factory()->forProgram($outsideProgram)->create();
    foreach ([$visible, $hidden, $outside] as $certificate) {
        CertificateEnrollment::factory()->create(['certification_candidate_id' => $roster->certification_candidate_id, 'certificate_id' => $certificate->id]);
    }

    $this->actingAs($viewer)->get(route('agreements.show', $agreement))->assertOk()
        ->assertViewHas('certificationRosterDisplay', fn ($rows) => $rows->sole()['restricted_count'] === 1 && $rows->sole()['enrollments']->pluck('certificate_id')->all() === [$visible->id])
        ->assertViewHas('overviewTimeline')->assertViewHas('overviewStaff')->assertViewHas('overviewOrganizations')->assertViewHas('restrictedActivityCount')->assertViewMissing('ytdTotals');
});
