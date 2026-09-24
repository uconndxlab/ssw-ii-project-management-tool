<?php

use App\Models\Activity;

test('duplicating an activity persists a second row', function () {
    $admin = createSystemAdmin();
    $activity = Activity::factory()->create(['user_id' => $admin->id]);

    $this->actingAs($admin)
        ->post(route('activities.duplicate', $activity))
        ->assertRedirect();

    expect(Activity::query()->count())->toBe(2);

    $copy = Activity::query()->whereKeyNot($activity->id)->first();

    expect($copy)->not->toBeNull()
        ->and((int) $copy->user_id)->toBe($admin->id)
        ->and((int) $copy->activity_type_id)->toBe((int) $activity->activity_type_id);
});
