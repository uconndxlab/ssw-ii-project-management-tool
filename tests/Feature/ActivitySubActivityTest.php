<?php

use App\Models\Activity;
use App\Models\ActivityType;
use App\Models\LoggingField;
use App\Models\SubActivity;

it('requires an available sub-activity on store', function () {
    $f = certificationActivityFixture();
    SubActivity::factory()->for($f['type'])->create();

    $this->actingAs($f['actor'])->post(route('activities.store'), $f['payload'])
        ->assertSessionHasErrors(['sub_activity_id' => 'Select a sub-activity for '.$f['type']->name.'.']);
    $this->assertDatabaseCount('activities', 0);
});

it('rejects a sub-activity from another type', function () {
    $f = certificationActivityFixture();
    $sub = SubActivity::factory()->create();

    $this->actingAs($f['actor'])->post(route('activities.store'), $f['payload'] + ['sub_activity_id' => $sub->id])
        ->assertSessionHasErrors(['sub_activity_id' => 'Selected sub-activity is not available for this activity type.']);
    $this->assertDatabaseCount('activities', 0);
});

it('rejects a sub-activity excluded by the agreements deliverables', function () {
    $f = certificationActivityFixture();
    $allowed = SubActivity::factory()->for($f['type'])->create();
    $excluded = SubActivity::factory()->for($f['type'])->create();
    $f['agreement']->deliverables()->update(['activity_type_id' => $f['type']->id, 'sub_activity_id' => $allowed->id]);

    $this->actingAs($f['actor'])->post(route('activities.store'), $f['payload'] + ['sub_activity_id' => $excluded->id])
        ->assertSessionHasErrors(['sub_activity_id' => 'Selected sub-activity is not available for this activity type.']);
    $this->assertDatabaseCount('activities', 0);
});

it('saves a selection and snapshots its effective duration', function (float $hours, float $expected) {
    $f = certificationActivityFixture();
    $f['type']->update(['duration_hours' => 2, 'duration_days' => 0]);
    $sub = SubActivity::factory()->for($f['type'])->create(['duration_hours' => $hours, 'duration_days' => 0]);

    $this->actingAs($f['actor'])->post(route('activities.store'), $f['payload'] + ['sub_activity_id' => $sub->id])
        ->assertSessionHasNoErrors()->assertRedirect();
    $activity = Activity::sole();
    expect($activity->sub_activity_id)->toBe($sub->id)
        ->and((float) $activity->allotted_duration_hours)->toBe($expected)
        ->and($activity->classificationLabel())->toBe($f['type']->name.' › '.$sub->name);
    $this->get(route('activities.show', $activity))->assertOk()->assertSee($sub->name);
    $this->get(route('activities.edit', $activity))->assertOk()->assertSee('id="sub_activity_id"', false);
})->with(['override' => [5.0, 5.0], 'inherit' => [0.0, 2.0]]);

it('permits no selection when the type offers none', function () {
    $f = certificationActivityFixture();

    $this->actingAs($f['actor'])->post(route('activities.store'), $f['payload'])->assertSessionHasNoErrors();
    expect(Activity::sole()->sub_activity_id)->toBeNull();
});

it('allows only activities predating the catalog to keep no sub-activity', function (bool $legacy) {
    $f = certificationActivityFixture();
    SubActivity::factory()->for($f['type'])->create(['created_at' => now()->subDay()]);
    $activity = Activity::factory()->for($f['type'])->for($f['actor'], 'user')->create(['created_at' => $legacy ? now()->subDays(2) : now()]);
    $activity->agreements()->attach($f['agreement']);

    $response = $this->actingAs($f['actor'])->put(route('activities.update', $activity), $f['payload']);
    if ($legacy) {
        $response->assertSessionHasNoErrors();
        expect($activity->fresh()->sub_activity_id)->toBeNull();
    } else {
        $response->assertSessionHasErrors('sub_activity_id');
    }
})->with([true, false]);

it('requires a selection after changing type and clears the old selection when none is offered', function (bool $offersSubActivity) {
    $f = certificationActivityFixture();
    $oldType = ActivityType::factory()->for($f['family'])->create();
    $oldSub = SubActivity::factory()->for($oldType)->create();
    $activity = Activity::factory()->for($oldType)->for($f['actor'], 'user')->create(['sub_activity_id' => $oldSub->id, 'created_at' => now()->subDays(5)]);
    $activity->agreements()->attach($f['agreement']);
    if ($offersSubActivity) {
        SubActivity::factory()->for($f['type'])->create();
    }

    $response = $this->actingAs($f['actor'])->put(route('activities.update', $activity), $f['payload']);
    if ($offersSubActivity) {
        $response->assertSessionHasErrors('sub_activity_id');
        expect($activity->fresh()->sub_activity_id)->toBe($oldSub->id);
    } else {
        $response->assertSessionHasNoErrors();
        expect($activity->fresh()->sub_activity_id)->toBeNull();
    }
})->with([true, false]);

it('resnapshots when only the sub-activity changes and removes the old answers', function () {
    $f = certificationActivityFixture();
    $old = SubActivity::factory()->for($f['type'])->create(['duration_hours' => 2]);
    $new = SubActivity::factory()->for($f['type'])->create(['duration_hours' => 4, 'duration_days' => 0]);
    $activity = Activity::factory()->for($f['type'])->for($f['actor'], 'user')->create(['sub_activity_id' => $old->id, 'allotted_duration_hours' => 2]);
    $field = LoggingField::factory()->create(['field_type' => 'text']);
    $activity->loggingFieldAnswers()->create(['logging_field_id' => $field->id, 'context_type' => 'sub_activity', 'context_id' => $old->id, 'value_text' => 'Old answer']);
    $activity->agreements()->attach($f['agreement']);

    $this->actingAs($f['actor'])->put(route('activities.update', $activity), $f['payload'] + ['sub_activity_id' => $new->id])->assertSessionHasNoErrors();
    expect((float) $activity->fresh()->allotted_duration_hours)->toBe(4.0);
    $this->assertDatabaseMissing('activity_logging_field_answers', ['activity_id' => $activity->id, 'context_id' => $old->id]);
});

it('validates required sub-activity fields and stores their context', function () {
    $f = certificationActivityFixture();
    $sub = SubActivity::factory()->for($f['type'])->create();
    $field = LoggingField::factory()->create(['field_type' => 'text']);
    $sub->subActivityLoggingFields()->attach($field, ['is_required' => true]);
    $payload = $f['payload'] + ['sub_activity_id' => $sub->id];
    $this->actingAs($f['actor'])->post(route('activities.store'), $payload)->assertSessionHasErrors('sub_activity_logging_values.'.$field->id);

    $this->post(route('activities.store'), $payload + ['sub_activity_logging_values' => [$field->id => 'Answer']])->assertSessionHasNoErrors();
    $this->assertDatabaseHas('activity_logging_field_answers', ['activity_id' => Activity::sole()->id, 'logging_field_id' => $field->id, 'context_type' => 'sub_activity', 'context_id' => $sub->id, 'value_text' => 'Answer']);
    $this->get(route('activities.show', Activity::sole()))->assertOk()->assertSee('Answer');
    $this->get(route('activities.edit', Activity::sole()))->assertOk()->assertSee('sub_activity_logging_values['.$field->id.']', false);
});

it('filters and searches activities by sub-activity in standalone and embedded lists', function (bool $embedded) {
    $f = certificationActivityFixture();
    $sub = SubActivity::factory()->for($f['type'])->create();
    $matching = Activity::factory()->for($f['type'])->for($f['actor'], 'user')->hasAttached($f['agreement'])->create(['sub_activity_id' => $sub->id]);
    Activity::factory()->for($f['type'])->for($f['actor'], 'user')->hasAttached($f['agreement'])->create();
    $filters = ['sub_activity_id' => $sub->id, 'search' => $sub->name];
    if ($embedded) {
        $filters += ['embedded' => 1, 'agreement_id' => $f['agreement']->id];
    }

    $this->actingAs($f['actor'])->get(route('activities.index', $filters))->assertOk()
        ->assertViewHas('activities', fn ($activities) => $activities->modelKeys() === [$matching->id]);
})->with([true, false]);

it('copies the sub-activity when duplicating', function () {
    $f = certificationActivityFixture();
    $sub = SubActivity::factory()->for($f['type'])->create();
    $activity = Activity::factory()->for($f['type'])->for($f['actor'], 'user')->create(['sub_activity_id' => $sub->id]);

    $this->actingAs($f['actor'])->post(route('activities.duplicate', $activity))->assertRedirect();
    expect(Activity::whereKeyNot($activity->id)->sole()->sub_activity_id)->toBe($sub->id);
});

it('opens an unsaved prefilled copy when duplicating needs a sub-activity choice', function () {
    $f = certificationActivityFixture();
    $activity = Activity::factory()->for($f['type'])->for($f['actor'], 'user')->hasAttached($f['agreement'])->create();
    SubActivity::factory()->for($f['type'])->create();

    $this->actingAs($f['actor'])->post(route('activities.duplicate', $activity))
        ->assertRedirect(route('activities.create', ['duplicate_of' => $activity->id]))
        ->assertSessionHas('warning', fn (string $message) => str_contains($message, 'choose one before the copy is saved'));
    expect(Activity::count())->toBe(1);

    $this->get(route('activities.create', ['duplicate_of' => $activity->id]))->assertOk()
        ->assertSee('action="'.route('activities.store').'"', false)
        ->assertSee($activity->engagement_date->format('Y-m-d'));
});
