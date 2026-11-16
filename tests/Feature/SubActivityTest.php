<?php

use App\Models\Activity;
use App\Models\ActivityType;
use App\Models\AgreementDeliverable;
use App\Models\CertificateRequirement;
use App\Models\ContactFamily;
use App\Models\LoggingField;
use App\Models\Program;
use App\Models\SubActivity;
use App\Models\User;
use App\Support\ActivityTypeDuration;
use App\Support\SubActivityOptions;

test('admins create and update sub-activities with independent scope and logging fields', function () {
    $type = ActivityType::factory()->create();
    $field = LoggingField::factory()->create(['available_in_sub_activities' => true, 'program_scope_mode' => 'all']);
    $this->actingAs(createSystemAdmin())->post(route('sub-activities.store'), [
        'activity_type_id' => $type->id, 'name' => 'Practice', 'helper_text' => 'Try it',
        'duration_unit' => 'hours', 'duration_value' => 2.5, 'program_scope_mode' => 'all',
        'active' => true, 'sub_activity_logging_field_ids' => [$field->id],
    ])->assertRedirect(route('sub-activities.index'))->assertSessionHasNoErrors();
    $sub = SubActivity::query()->sole();
    expect($sub->duration_hours)->toBe('2.5')->and($sub->subActivityLoggingFields->modelKeys())->toBe([$field->id]);

    $this->put(route('sub-activities.update', $sub), [
        'activity_type_id' => $type->id, 'name' => 'Revised', 'duration_unit' => 'none',
        'program_scope_mode' => 'all', 'active' => false,
    ])->assertRedirect()->assertSessionHasNoErrors();
    expect($sub->fresh()->name)->toBe('Revised')->and($sub->fresh()->active)->toBeFalse()
        ->and($sub->fresh()->duration_hours)->toBe('0.0');
});

test('sub-activity names are unique within a type on create and update', function () {
    $sub = SubActivity::factory()->create(['name' => 'Practice']);
    $other = SubActivity::factory()->create(['activity_type_id' => $sub->activity_type_id]);
    $payload = ['name' => 'Practice', 'activity_type_id' => $sub->activity_type_id, 'duration_unit' => 'none', 'program_scope_mode' => 'all'];
    $this->actingAs(createSystemAdmin())->post(route('sub-activities.store'), $payload)->assertSessionHasErrors('name');
    $this->put(route('sub-activities.update', $other), $payload)->assertSessionHasErrors('name');
    $payload['activity_type_id'] = ActivityType::factory()->create()->id;
    $this->post(route('sub-activities.store'), $payload)->assertSessionHasNoErrors();
    expect(SubActivity::query()->where('name', 'Practice')->count())->toBe(2);
});

test('staff cannot administer sub-activities', function () {
    $sub = SubActivity::factory()->create();
    $this->actingAs(User::factory()->create())->get(route('sub-activities.index'))->assertForbidden();
    $this->post(route('sub-activities.store'), [])->assertForbidden();
    $this->put(route('sub-activities.update', $sub), [])->assertForbidden();
    $this->delete(route('sub-activities.destroy', $sub))->assertForbidden();
    $this->assertModelExists($sub);
});

test('used sub-activities cannot be deleted', function (string $model) {
    $sub = SubActivity::factory()->create();
    $model::factory()->create(['sub_activity_id' => $sub->id]);
    $this->actingAs(createSystemAdmin())->delete(route('sub-activities.destroy', $sub))->assertSessionHas('error');
    $this->assertModelExists($sub);
})->with([Activity::class, AgreementDeliverable::class, CertificateRequirement::class]);

test('used sub-activities cannot be moved to another type', function () {
    $sub = SubActivity::factory()->create();
    Activity::factory()->create(['activity_type_id' => $sub->activity_type_id, 'sub_activity_id' => $sub->id]);
    $payload = ['name' => $sub->name, 'activity_type_id' => ActivityType::factory()->create()->id, 'duration_unit' => 'none', 'program_scope_mode' => 'all'];

    $this->actingAs(createSystemAdmin())->put(route('sub-activities.update', $sub), $payload)->assertSessionHasErrors('activity_type_id');
    expect($sub->fresh()->activity_type_id)->toBe($sub->activity_type_id);
});

test('types cannot be deleted while used by deliverables requirements or sub-activities', function (string $model) {
    $type = ActivityType::factory()->create();
    $model::factory()->create(['activity_type_id' => $type->id]);
    $this->actingAs(createSystemAdmin())->delete(route('activity-types.destroy', $type))->assertSessionHas('error');
    $this->assertModelExists($type);
})->with([Activity::class, AgreementDeliverable::class, CertificateRequirement::class, SubActivity::class]);

test('families cannot be deleted while used by deliverables or requirements', function (string $model) {
    $family = ContactFamily::factory()->create();
    $model::factory()->create(['contact_family_id' => $family->id]);
    $this->actingAs(createSystemAdmin())->delete(route('contact-families.destroy', $family))->assertSessionHas('error');
    $this->assertModelExists($family);
})->with([ActivityType::class, AgreementDeliverable::class, CertificateRequirement::class]);

test('unused sub-activities can be deleted', function () {
    $sub = SubActivity::factory()->create();
    $this->actingAs(createSystemAdmin())->delete(route('sub-activities.destroy', $sub))->assertSessionHas('success');
    $this->assertModelMissing($sub);
});

test('options exclude inactive records and retain only a saved value belonging to the type', function () {
    $sub = SubActivity::factory()->create();
    $inactive = SubActivity::factory()->inactive()->create(['activity_type_id' => $sub->activity_type_id]);
    $foreign = SubActivity::factory()->create();
    expect(SubActivityOptions::availableFor($sub->activity_type_id)->modelKeys())->toBe([$sub->id]);
    expect(SubActivityOptions::availableFor($sub->activity_type_id, [], $inactive->id)->modelKeys())->toContain($inactive->id);
    expect(SubActivityOptions::availableFor($sub->activity_type_id, [], $foreign->id)->modelKeys())->toBe([$sub->id]);
    $sub->activityType->update(['active' => false]);
    expect(SubActivityOptions::availableFor($sub->activity_type_id, [], $sub->id))->toBeEmpty();
    expect(SubActivityOptions::availableFor(999999))->toBeEmpty();
});

test('agreement options mirror family type and sub-level deliverables', function (string $level) {
    $type = ActivityType::factory()->create();
    $one = SubActivity::factory()->create(['activity_type_id' => $type->id, 'sort_order' => 1]);
    $two = SubActivity::factory()->create(['activity_type_id' => $type->id, 'sort_order' => 2]);
    $deliverable = AgreementDeliverable::factory()->create([
        'contact_family_id' => $type->contact_family_id,
        'activity_type_id' => $level === 'family' ? null : $type->id,
        'sub_activity_id' => $level === 'sub' ? $one->id : null,
    ]);
    expect(SubActivityOptions::availableFor($type->id, [$deliverable->agreement_id])->modelKeys())
        ->toBe($level === 'sub' ? [$one->id] : [$one->id, $two->id]);
    expect(SubActivityOptions::availableFor($type->id, [$deliverable->agreement_id], $two->id)->modelKeys())->toBe([$one->id, $two->id]);
    $deliverable->update(['retired_at' => now()]);
    expect(SubActivityOptions::availableFor($type->id, [$deliverable->agreement_id]))->toBeEmpty();
})->with(['family', 'type', 'sub']);

test('effective duration overrides or inherits the type', function () {
    $type = ActivityType::factory()->withHourDuration(2)->make();
    $sub = SubActivity::factory()->withDayDuration(1)->make();
    expect(ActivityTypeDuration::effective($type, $sub)->formatLabel())->toBe('1 day')
        ->and(ActivityTypeDuration::effectiveSource($type, $sub))->toBe('sub_activity')
        ->and(ActivityTypeDuration::snapshotForSelection($type, $sub))->toBe(['allotted_duration_hours' => null, 'allotted_duration_days' => 1.0]);
    $sub->duration_days = 0;
    expect(ActivityTypeDuration::effective($type, $sub)->formatLabel())->toBe('2 hours')
        ->and(ActivityTypeDuration::effectiveSource($type, $sub))->toBe('activity_type')
        ->and(ActivityTypeDuration::effective(null, null)->hasDuration())->toBeFalse()
        ->and(ActivityTypeDuration::effectiveSource(null, null))->toBeNull();
});

test('by-type options return effective duration source and escaped helper text', function () {
    $type = ActivityType::factory()->withHourDuration(2)->create();
    $inherited = SubActivity::factory()->create(['activity_type_id' => $type->id, 'helper_text' => '<hello>']);
    $override = SubActivity::factory()->withDayDuration(1)->create(['activity_type_id' => $type->id]);
    $this->actingAs(createSystemAdmin())->get(route('sub-activities.by-type', ['activity_type_id' => $type->id, 'sub_activity_id' => $inherited->id]))
        ->assertOk()->assertSee('data-duration-hours="2"', false)->assertSee('data-duration-days="1"', false)
        ->assertSee('data-duration-source="activity_type"', false)->assertSee('data-duration-source="sub_activity"', false)
        ->assertSee('data-helper-text="&lt;hello&gt;"', false)->assertSee(' selected', false);
});

test('only enabled logging fields appear in the sub-activity picker', function () {
    $shown = LoggingField::factory()->create(['available_in_sub_activities' => true]);
    $hidden = LoggingField::factory()->forActivities()->create();
    $this->actingAs(createSystemAdmin())->get(route('sub-activities.create'))->assertOk()->assertSee($shown->name)->assertDontSee($hidden->name);
    $this->get(route('sub-activities.index'))->assertOk();
});

test('sub-activity admin scope is independent of its parent and protects other programs', function () {
    [$project, $program] = createProjectWithProgram();
    $other = Program::factory()->create();
    $type = ActivityType::factory()->create(['program_scope_mode' => 'none']);
    $sub = SubActivity::factory()->create(['activity_type_id' => $type->id, 'program_scope_mode' => 'specific']);
    $sub->programs()->attach($program);
    $hidden = SubActivity::factory()->create(['program_scope_mode' => 'specific']);
    $hidden->programs()->attach($other);
    $this->actingAs(createProgramAdmin($program))->get(route('sub-activities.index'))->assertOk()->assertSee($sub->name)->assertDontSee($hidden->name);
    $this->get(route('sub-activities.edit', $sub))->assertOk();
    $this->put(route('sub-activities.update', $sub), [
        'name' => $sub->name, 'activity_type_id' => $type->id, 'duration_unit' => 'none',
        'program_scope_mode' => 'specific', 'project_ids' => [$project->id], 'program_ids' => [$program->id, $other->id],
    ])->assertSessionHasErrors('program_ids');
    expect($sub->fresh()->programs->modelKeys())->toBe([$program->id]);
});

test('logging field availability changes detach sub-activities and usage blocks deletion', function () {
    $sub = SubActivity::factory()->create();
    $field = LoggingField::factory()->create(['available_in_sub_activities' => true]);
    $sub->subActivityLoggingFields()->attach($field);
    $this->actingAs(createSystemAdmin())->get(route('logging-fields.show', $field))->assertOk()->assertSee($sub->name);
    $this->delete(route('logging-fields.destroy', $field))->assertSessionHas('error');
    $this->assertModelExists($field);
    $this->put(route('logging-fields.update', $field), [
        'name' => $field->name, 'field_type' => 'text', 'program_scope_mode' => 'all', 'is_active' => true,
        'available_in_sub_activities' => false,
    ])->assertSessionHasNoErrors();
    expect($sub->fresh()->subActivityLoggingFields)->toBeEmpty();
});

test('sub-activity index filters and sorts effective durations and displays inherited status', function () {
    $type = ActivityType::factory()->inactive()->withHourDuration(2)->create();
    $inherited = SubActivity::factory()->create(['activity_type_id' => $type->id]);
    $override = SubActivity::factory()->withHourDuration(1)->create(['activity_type_id' => $type->id]);
    $excluded = SubActivity::factory()->create();
    $this->actingAs(createSystemAdmin())->get(route('sub-activities.index', ['activity_type_id' => $type->id, 'sort' => 'duration_hours']))
        ->assertOk()->assertSeeInOrder([$override->name, $inherited->name])->assertSee('2 hours (from type)')->assertSee('Type inactive')->assertDontSee($excluded->name);
    $this->get(route('sub-activities.edit', $inherited))->assertOk()->assertSee('data-duration-label="2 hours"', false);
});
