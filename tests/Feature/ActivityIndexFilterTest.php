<?php

use App\Models\Activity;
use App\Models\ActivityType;
use App\Models\Agreement;
use App\Models\User;

test('activities filter by their own projects and programs', function (string $filter) {
    $admin = createSystemAdmin();
    [$project, $program] = createProjectWithProgram();
    [$otherProject, $otherProgram] = createProjectWithProgram();
    $type = ActivityType::factory()->create();
    $type->programs()->attach($program);
    $agreement = Agreement::factory()->create();
    $agreement->programs()->attach($program);

    $matching = Activity::factory()->for($admin, 'user')->for($type)->create();
    $matching->programs()->attach([$otherProgram->id, $program->id]);
    $unrelated = Activity::factory()->for($admin, 'user')->for($type)->create();
    $unrelated->programs()->attach($otherProgram);
    $unrelated->agreements()->attach($agreement);
    $unscoped = Activity::factory()->for($admin, 'user')->for($type)->create();

    $filters = [
        'project_id' => $project->id,
        'program_id' => $program->id,
    ];
    if ($filter !== 'both') {
        $filters = [$filter => $filters[$filter]];
    }

    $response = $this->actingAs($admin)->withHeader('HX-Request', 'true')->get(route('activities.index', $filters));

    $response->assertOk()->assertViewHas('activities', fn ($activities) => $activities->modelKeys() === [$matching->id]);
    $response->assertSee('data-label="Project"', false)
        ->assertSee('data-label="Program"', false)
        ->assertSee($project->name)
        ->assertSee($otherProject->name)
        ->assertSee($program->name)
        ->assertSee($otherProgram->name);
    $displayed = $response->viewData('activities')->first();
    expect($displayed->relationLoaded('programs'))->toBeTrue()
        ->and($displayed->programs->every(fn ($item) => $item->relationLoaded('projects')))->toBeTrue();
})->with(['project_id', 'program_id', 'both']);

test('activity scope filters combine with existing filters sorting and pagination', function () {
    $admin = createSystemAdmin();
    [$project, $program] = createProjectWithProgram();
    $type = ActivityType::factory()->create();
    $agreement = Agreement::factory()->create(['name' => 'Filter target agreement']);
    $activities = Activity::factory()->count(2)->for($admin, 'user')->for($type)
        ->sequence(['engagement_date' => '2026-01-01'], ['engagement_date' => '2026-02-01'])
        ->create();
    foreach ($activities as $activity) {
        $activity->programs()->attach($program);
        $activity->agreements()->attach($agreement);
    }
    $excluded = Activity::factory()->for($admin, 'user')->create();
    $excluded->programs()->attach($program);
    $filters = [
        'project_id' => $project->id,
        'program_id' => $program->id,
        'activity_type_id' => $type->id,
        'agreement_id' => $agreement->id,
        'search' => 'Filter target',
        'sort' => 'date',
        'direction' => 'asc',
        'per_page' => 10,
    ];

    $response = $this->actingAs($admin)->get(route('activities.index', $filters));

    $response->assertOk()->assertViewHas('activities', fn ($page) => $page->modelKeys() === $activities->modelKeys());
    $nextPage = $response->viewData('activities')->url(2);
    parse_str(parse_url($nextPage, PHP_URL_QUERY), $query);
    expect($query)->toMatchArray(array_map(strval(...), $filters));
    $response->assertSee('value="'.$project->id.'" selected', false)
        ->assertSee('value="'.$program->id.'" selected', false);

    $this->actingAs($admin)->withHeader('HX-Request', 'true')
        ->get(route('activities.index', [...$filters, 'partial' => 'filters']))
        ->assertOk()->assertSee('name="project_id"', false)->assertSee('name="program_id"', false)
        ->assertSee('id="activities-table"', false);
});

test('activity scope filters do not reveal other users activities', function (string $filter) {
    $user = User::factory()->create();
    [$project, $program] = createProjectWithProgram();
    $visible = Activity::factory()->for($user)->create();
    $visible->programs()->attach($program);
    $hidden = Activity::factory()->create();
    $hidden->programs()->attach($program);
    $filters = ['project_id' => $project->id, 'program_id' => $program->id];
    if ($filter !== 'both') {
        $filters = [$filter => $filters[$filter]];
    }

    $this->actingAs($user)->get(route('activities.index'))
        ->assertOk()->assertViewHas('activities', fn ($page) => $page->modelKeys() === [$visible->id]);
    $this->actingAs($user)->get(route('activities.index', $filters))
        ->assertOk()->assertViewHas('activities', fn ($page) => $page->modelKeys() === [$visible->id]);
})->with(['project_id', 'program_id', 'both']);

test('project and program filters both narrow the activity list', function () {
    $admin = createSystemAdmin();
    [$project, $program] = createProjectWithProgram();
    [, $otherProgram] = createProjectWithProgram();
    $projectOnly = Activity::factory()->for($admin, 'user')->create();
    $projectOnly->programs()->attach($program);
    $programOnly = Activity::factory()->for($admin, 'user')->create();
    $programOnly->programs()->attach($otherProgram);
    $both = Activity::factory()->for($admin, 'user')->create();
    $both->programs()->attach([$program->id, $otherProgram->id]);

    $this->actingAs($admin)->get(route('activities.index', [
        'project_id' => $project->id,
        'program_id' => $otherProgram->id,
    ]))->assertOk()->assertViewHas('activities', fn ($page) => $page->modelKeys() === [$both->id]);
});
