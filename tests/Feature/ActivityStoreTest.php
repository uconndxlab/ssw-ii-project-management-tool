<?php

use App\Models\Activity;
use App\Models\ActivityType;
use App\Models\Agreement;
use App\Models\AgreementDeliverable;
use App\Models\ContactFamily;
use App\Models\Organization;
use App\Models\State;

test('logging an activity persists date type and completion count', function () {
    [$project, $program] = createProjectWithProgram();
    $state = State::factory()->create();
    $organization = Organization::factory()->create();
    $organization->states()->attach($state);
    $contactFamily = ContactFamily::factory()->create();
    $activityType = ActivityType::factory()->for($contactFamily)->create();
    $agreement = Agreement::factory()->current()->create();
    $agreement->programs()->attach($program);
    $agreement->states()->attach($state);
    $agreement->organizations()->attach($organization->id, [
        'payor_source' => false,
        'recipient' => false,
    ]);
    AgreementDeliverable::factory()->for($agreement)->completionByContact()->create([
        'contact_family_id' => $contactFamily->id,
        'program_id' => $program->id,
    ]);

    $this->actingAs(createSystemAdmin())
        ->post(route('activities.store'), [
            'agreement_ids' => [$agreement->id],
            'state_ids' => [$state->id],
            'organization_ids' => [$organization->id],
            'project_ids' => [$project->id],
            'program_ids' => [$program->id],
            'engagement_date' => '2026-03-15',
            'contact_family_id' => $contactFamily->id,
            'activity_type_id' => $activityType->id,
            'completion_count' => 3,
        ])
        ->assertRedirect(route('activities.index'));

    $activity = Activity::query()->first();

    expect($activity)->not->toBeNull()
        ->and($activity->engagement_date->toDateString())->toBe('2026-03-15')
        ->and((int) $activity->activity_type_id)->toBe($activityType->id)
        ->and((int) $activity->completion_count)->toBe(3);
});
