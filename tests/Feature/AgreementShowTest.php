<?php

use App\Models\Activity;
use App\Models\ActivityType;
use App\Models\Agreement;

it('shows agreement sections and reporting downloads to system admins', function () {
    $agreement = Agreement::factory()->create();

    $this->actingAs(createSystemAdmin())->get(route('agreements.show', $agreement))
        ->assertOk()
        ->assertSeeInOrder(['data-section-nav="overview"', 'data-section-nav="activity"', 'data-section-nav="deliverables"', 'data-section-nav="certification"', 'data-section-nav="reporting"'], false)
        ->assertSee('action="'.route('agreements.reports.store', $agreement).'"', false)
        ->assertSee('Download CSV')
        ->assertSee('Requested reports');
});

it('omits reporting navigation and downloads for agreement editors who are not system admins', function () {
    [, $program] = createProjectWithProgram();
    $agreement = Agreement::factory()->hasAttached($program)->create();
    $editor = createProgramAdmin($program);

    expect($editor->can('update', $agreement))->toBeTrue();

    $this->actingAs($editor)->get(route('agreements.show', $agreement))
        ->assertOk()
        ->assertSee('data-section-nav="activity"', false)
        ->assertDontSee('data-section-nav="reporting"', false)
        ->assertDontSee('id="section-reporting"', false)
        ->assertDontSee('Download CSV')
        ->assertViewMissing('reportRequests');
});

it('shows historical agreement sections when inactive and limits recent activity to five', function () {
    $agreement = Agreement::factory()->create(['active' => false]);
    Activity::factory()->count(6)->hasAttached($agreement)->create();

    $this->actingAs(createSystemAdmin())->get(route('agreements.show', $agreement))
        ->assertOk()
        ->assertSee('Activity logging is disabled for inactive agreements.')
        ->assertViewHas('recentActivities', fn ($activities) => $activities->count() === 5);
});

it('filters sorts and pages the embedded list within its agreement', function () {
    $agreement = Agreement::factory()->create();
    $type = ActivityType::factory()->create(['name' => 'Matching activity type']);
    $otherType = ActivityType::factory()->create(['name' => 'Unmatched activity type']);
    Activity::factory()->count(11)->for($type)->hasAttached($agreement)->create(['engagement_date' => '2026-01-01']);
    $latest = Activity::factory()->for($type)->hasAttached($agreement)->create(['engagement_date' => '2026-10-31']);
    Activity::factory()->for($otherType)->hasAttached($agreement)->create();
    Activity::factory()->for($type)->create(['engagement_date' => '2026-12-31']);
    $parameters = ['embedded' => 1, 'agreement_id' => $agreement->id, 'activity_type_id' => $type->id, 'sort' => 'date', 'direction' => 'desc', 'per_page' => 10];
    $this->actingAs(createSystemAdmin());

    $this->get(route('activities.index', $parameters), ['HX-Request' => 'true'])
        ->assertOk()
        ->assertViewHas('activities', fn ($activities) => $activities->total() === 12 && $activities->first()->id === $latest->id)
        ->assertSee('hx-target="#agreement-activities"', false)
        ->assertSee('data-table-filter-ignore="embedded,agreement_id"', false)
        ->assertDontSee('hx-push-url="true"', false)
        ->assertDontSee('Filter by agreement')
        ->assertDontSee('data-label="Agreement"', false);

    $this->get(route('activities.index', $parameters + ['page' => 2]), ['HX-Request' => 'true'])
        ->assertOk()
        ->assertViewHas('activities', fn ($activities) => $activities->count() === 2 && $activities->currentPage() === 2);
});

it('keeps embedded activity visibility identical to the activities index', function () {
    [, $program] = createProjectWithProgram();
    $agreement = Agreement::factory()->hasAttached($program)->create();
    $viewer = createProgramViewer($program);
    Activity::factory()->count(2)->hasAttached($agreement)->hasAttached($program)->create();
    $this->actingAs($viewer);
    $index = $this->get(route('activities.index', ['agreement_id' => $agreement->id]));
    $index->assertOk();
    $expectedIds = $index->viewData('activities')->pluck('id')->all();

    $this->get(route('activities.index', ['embedded' => 1, 'agreement_id' => $agreement->id]), ['HX-Request' => 'true'])
        ->assertOk()
        ->assertViewHas('activities', fn ($activities) => $activities->pluck('id')->all() === $expectedIds);
});

it('denies embedded access to agreements the actor cannot view', function () {
    [, $program] = createProjectWithProgram();
    $agreement = Agreement::factory()->create();

    $this->actingAs(createProgramViewer($program))
        ->get(route('activities.index', ['embedded' => 1, 'agreement_id' => $agreement->id]), ['HX-Request' => 'true'])
        ->assertForbidden();
});
