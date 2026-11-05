<?php

use App\Models\Activity;
use App\Models\ActivityType;
use App\Models\Agreement;
use App\Models\Organization;
use App\Models\Program;
use App\Models\State;
use App\Models\User;

test('program viewer does not see activities on an agreement outside their programs just by being staff on it', function () {
    $viewer = createProgramViewer(Program::factory()->create());
    $agreement = Agreement::factory()->current()->create();
    $agreement->programs()->attach(Program::factory()->create());
    $agreement->users()->attach($viewer);
    $activity = Activity::factory()->create();
    $activity->agreements()->attach($agreement);

    expect($viewer->can('view', $activity))->toBeFalse();
});

test('program viewer sees activities in scope through the agreement or the activity program, and ones they logged', function () {
    $program = Program::factory()->create();
    $viewer = createProgramViewer($program);
    $agreement = Agreement::factory()->current()->create();
    $agreement->programs()->attach($program);
    $onAgreement = Activity::factory()->create();
    $onAgreement->agreements()->attach($agreement);
    $taggedProgram = Activity::factory()->create();
    $taggedProgram->programs()->attach($program);
    $logged = Activity::factory()->create(['user_id' => $viewer->id]);

    expect($viewer->can('view', $onAgreement))->toBeTrue()
        ->and($viewer->can('view', $taggedProgram))->toBeTrue()
        ->and($viewer->can('view', $logged))->toBeTrue();
});

test('recent activity tables list only activities the user can see while totals still count all', function (string $page) {
    [$project, $program] = createProjectWithProgram();
    $organization = Organization::factory()->create();
    $state = State::factory()->create();
    $member = User::factory()->create();
    $agreement = Agreement::factory()->current()->create();
    $agreement->programs()->attach($program);
    $agreement->organizations()->attach($organization);
    $agreement->states()->attach($state);
    $agreement->users()->attach($member);
    $program->users()->attach($member);
    $organization->users()->attach($member);

    foreach (['My own visit' => $member, 'Hidden visit' => User::factory()->create()] as $name => $logger) {
        $activity = Activity::factory()->for($logger)->for(ActivityType::factory()->create(['name' => $name]))->create(['engagement_date' => now()]);
        $activity->agreements()->attach($agreement);
        $activity->programs()->attach($program);
        $activity->organizations()->attach($organization);
        $activity->states()->attach($state);
    }

    $url = match ($page) {
        'agreement' => route('agreements.show', $agreement),
        'organization' => route('organizations.show', $organization),
        'state' => route('states.show', $state),
        'program' => route('programs.show', $program),
        'project' => route('projects.show', $project),
    };

    $response = $this->actingAs($member)->get($url)->assertOk()->assertSee('My own visit')->assertDontSee('Hidden visit');

    if ($page === 'program') {
        $response->assertSee('<dt>Activities</dt><dd>2</dd>', false);
    }
})->with(['agreement', 'organization', 'state', 'program', 'project']);

test('user page lists only the recent activities the viewer can see', function () {
    $program = Program::factory()->create();
    $viewer = createProgramViewer($program);
    $person = User::factory()->create();
    $program->users()->attach($person);

    foreach (['In scope visit' => $program, 'Out of scope visit' => Program::factory()->create()] as $name => $activityProgram) {
        $activity = Activity::factory()->for(ActivityType::factory()->create(['name' => $name]))->create();
        $activity->programs()->attach($activityProgram);
        $activity->participants()->attach($person);
    }

    $this->actingAs($viewer)->get(route('users.show', $person))
        ->assertOk()
        ->assertSee('In scope visit')
        ->assertDontSee('Out of scope visit');
});
