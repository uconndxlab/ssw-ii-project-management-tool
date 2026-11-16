<?php

use App\Enums\ProgramScopeMode;
use App\Models\Activity;
use App\Models\ActivityType;
use App\Models\Agreement;
use App\Models\AgreementDeliverable;
use App\Models\ContactFamily;
use App\Models\Program;
use App\Models\State;
use App\Models\SubActivity;
use App\Models\User;
use App\Services\DeliverableContributionService;
use App\Support\ActivityTypeDuration;
use App\Support\DeliverableHistoryScope;

it('family and type deliverables count every activity of the type; a sub-activity deliverable counts only that sub-activity', function (bool $withSubActivity) {
    $agreement = Agreement::factory()->current()->create();
    $family = ContactFamily::factory()->create();
    $type = ActivityType::factory()->for($family)->create();
    $sub = SubActivity::factory()->for($type)->create();
    $other = SubActivity::factory()->for($type)->create();
    $deliverables = collect([
        ['activity_type_id' => null, 'sub_activity_id' => null],
        ['activity_type_id' => $type->id, 'sub_activity_id' => null],
        ['activity_type_id' => $type->id, 'sub_activity_id' => $sub->id],
        ['activity_type_id' => $type->id, 'sub_activity_id' => $other->id],
    ])->map(fn (array $selection) => AgreementDeliverable::factory()->for($agreement)->completionByContact()->create($selection + ['contact_family_id' => $family->id]));
    $activity = Activity::factory()->for($type)->create(['sub_activity_id' => $withSubActivity ? $sub->id : null]);
    $activity->agreements()->attach($agreement);

    app(DeliverableContributionService::class)->syncForActivity($activity);

    $histories = $agreement->agreementActivityHistories()->get();
    expect($histories)->not->toBeEmpty();
    expect($histories->pluck('sub_activity_id')->unique()->all())->toBe([$withSubActivity ? $sub->id : null]);
    foreach ($deliverables as $index => $deliverable) {
        $matches = $index < 2 || ($index === 2 && $withSubActivity);
        expect($deliverable->contributions()->exists())->toBe($matches);
        expect(DeliverableHistoryScope::hasMatchingHistory($histories, $deliverable))->toBe($matches);
    }
})->with([true, false]);

it('saves a sub-activity deliverable in the sub-activity\'s own duration unit and keeps it once inactive', function () {
    $admin = createSystemAdmin();
    [$project, $program] = createProjectWithProgram();
    $member = User::factory()->create();
    $member->programs()->attach($program);
    $family = ContactFamily::factory()->create();
    $type = ActivityType::factory()->for($family)->create(['duration_days' => 1, 'duration_hours' => 0]);
    $sub = SubActivity::factory()->for($type)->create(['duration_days' => 0, 'duration_hours' => 2]);

    $this->actingAs($admin)->get(route('agreements.create'))->assertOk()->assertSee('Any sub-activity');

    $this->post(route('agreements.store'), [
        'name' => 'Sub-activity Agreement',
        'program_scope_mode' => ProgramScopeMode::Specific->value,
        'project_ids' => [$project->id],
        'program_ids' => [$program->id],
        'state_ids' => [State::factory()->create()->id],
        'user_ids' => [$member->id],
        'principal_investigator_ids' => [$member->id],
        'time_tracking_mode' => 'none',
        'require_payor' => 0,
        'require_payee' => 0,
        'start_date' => now()->toDateString(),
        'end_date' => now()->addYear()->toDateString(),
        'deliverables' => [[
            'contact_family_id' => $family->id,
            'activity_type_id' => $type->id,
            'sub_activity_id' => $sub->id,
            'metric_type' => 'time',
            'time_basis' => 'allotted',
            'contribution_basis' => 'contact',
            'target_quantity' => 4,
        ]],
    ])->assertSessionHasNoErrors()->assertRedirect();

    $this->assertDatabaseHas('agreement_deliverables', ['sub_activity_id' => $sub->id, 'allotted_time_unit' => 'hours']);
    $sub->update(['active' => false]);
    $agreement = Agreement::query()->where('name', 'Sub-activity Agreement')->sole();
    $this->get(route('agreements.edit', $agreement))->assertOk()->assertSee($sub->name);
});

it('rejects deliverable sub-activities outside their selected classification or programs', function (string $invalidSelection) {
    $admin = createSystemAdmin();
    [$project, $program] = createProjectWithProgram();
    $member = User::factory()->create();
    $member->programs()->attach($program);
    $family = ContactFamily::factory()->create();
    $type = ActivityType::factory()->for($family)->create();
    $sub = SubActivity::factory()->for($type)->create();
    if ($invalidSelection === 'type') {
        $sub->update(['activity_type_id' => ActivityType::factory()->create()->id]);
    }
    if ($invalidSelection === 'program') {
        $sub->update(['program_scope_mode' => ProgramScopeMode::Specific]);
        $sub->programs()->attach(Program::factory()->create());
    }

    $response = $this->actingAs($admin)->post(route('agreements.store'), [
        'name' => 'Invalid Selection',
        'program_scope_mode' => ProgramScopeMode::Specific->value,
        'project_ids' => [$project->id],
        'program_ids' => [$program->id],
        'state_ids' => [State::factory()->create()->id],
        'user_ids' => [$member->id],
        'principal_investigator_ids' => [$member->id],
        'time_tracking_mode' => 'none',
        'require_payor' => 0,
        'require_payee' => 0,
        'start_date' => now()->toDateString(),
        'end_date' => now()->addYear()->toDateString(),
        'deliverables' => [[
            'contact_family_id' => $family->id,
            'activity_type_id' => $invalidSelection === 'missing type' ? null : $type->id,
            'sub_activity_id' => $sub->id,
            'metric_type' => 'completion',
            'contribution_basis' => 'contact',
            'target_quantity' => 4,
        ]],
    ]);

    $response->assertSessionHasErrors($invalidSelection === 'program'
        ? ['deliverables' => 'Deliverable sub-activities must be global or match one of the selected programs.']
        : ['deliverables.0.sub_activity_id' => 'Deliverable sub-activities must belong to the selected activity type.']);
    $this->assertDatabaseCount('agreement_deliverables', 0);
    $this->assertDatabaseCount('agreements', 0);
})->with(['type', 'program', 'missing type']);

it('deliverable unit options: a type offers days and hours when its sub-activities use hours, a sub-activity uses its own or inherited unit, and out-of-program sub-activities are ignored', function () {
    $type = ActivityType::factory()->create(['duration_days' => 1, 'duration_hours' => 0]);
    $sub = SubActivity::factory()->for($type)->create(['duration_days' => 0, 'duration_hours' => 2]);
    $types = ActivityTypeDuration::filterActivityTypesInScope(collect([$type]), $type->contact_family_id, $type->id, []);

    expect(ActivityTypeDuration::resolveAllottedUnitsForSelection($type->contact_family_id, $type->id, $types)['is_mixed'])->toBeTrue();
    expect(ActivityTypeDuration::resolveAllottedUnitsForSelection($type->contact_family_id, null, $types)['allowed_units'])->toBe(['days', 'hours']);
    expect(ActivityTypeDuration::normalizeAllottedTimeUnit($type->contact_family_id, $type->id, $types, null, $sub->id))->toBe('hours');
    $sub->update(['duration_hours' => 0]);
    $types = ActivityTypeDuration::filterActivityTypesInScope(collect([$type->fresh()]), $type->contact_family_id, $type->id, []);
    expect(ActivityTypeDuration::normalizeAllottedTimeUnit($type->contact_family_id, $type->id, $types, null, $sub->id))->toBe('days');
    $sub->update(['duration_hours' => 2, 'program_scope_mode' => ProgramScopeMode::Specific]);
    $sub->programs()->attach(Program::factory()->create());
    $types = ActivityTypeDuration::filterActivityTypesInScope(collect([$type->fresh()]), $type->contact_family_id, $type->id, []);
    expect(ActivityTypeDuration::resolveAllottedUnitsForSelection($type->contact_family_id, $type->id, $types)['allowed_units'])->toBe(['days']);
});

it('copies a deliverable sub-activity when duplicating an agreement', function () {
    $admin = createSystemAdmin();
    $agreement = Agreement::factory()->current()->create();
    $sub = SubActivity::factory()->create();
    AgreementDeliverable::factory()->for($agreement)->completionByContact()->create([
        'contact_family_id' => $sub->activityType->contact_family_id,
        'activity_type_id' => $sub->activity_type_id,
        'sub_activity_id' => $sub->id,
    ]);

    $this->actingAs($admin)->post(route('agreements.duplicate', $agreement))->assertRedirect();

    $copy = Agreement::query()->whereKeyNot($agreement->id)->sole();
    expect($copy->deliverables()->sole()->sub_activity_id)->toBe($sub->id);
});
