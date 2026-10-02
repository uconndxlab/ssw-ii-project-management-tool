<?php

use App\Models\Activity;
use App\Models\ActivityType;
use App\Models\Agreement;

test('activities index sorts by agreement name on postgres', function () {
    $admin = createSystemAdmin();
    $activityType = ActivityType::factory()->create();

    $alpha = Agreement::factory()->create(['name' => 'Alpha Agreement']);
    $mid = Agreement::factory()->create(['name' => 'Mid Agreement']);
    $zulu = Agreement::factory()->create(['name' => 'Zulu Agreement']);

    $alphaActivity = Activity::factory()->create([
        'user_id' => $admin->id,
        'activity_type_id' => $activityType->id,
        'engagement_date' => '2026-01-15',
    ]);
    $alphaActivity->agreements()->attach([$alpha->id, $mid->id]);

    $zuluActivity = Activity::factory()->create([
        'user_id' => $admin->id,
        'activity_type_id' => $activityType->id,
        'engagement_date' => '2026-02-20',
    ]);
    $zuluActivity->agreements()->attach($zulu);

    $unlinked = Activity::factory()->create([
        'user_id' => $admin->id,
        'activity_type_id' => $activityType->id,
        'engagement_date' => '2026-03-01',
    ]);

    $ascending = $this->actingAs($admin)->get(route('activities.index', [
        'activity_type_id' => $activityType->id,
        'sort' => 'agreement',
        'direction' => 'asc',
    ]));

    $ascending->assertOk();
    $ascending->assertSeeInOrder([
        'Mar 01, 2026',
        'Jan 15, 2026',
        'Feb 20, 2026',
    ]);
    expect(substr_count($ascending->getContent(), 'Activity actions for Jan 15, 2026'))->toBe(1)
        ->and(substr_count($ascending->getContent(), 'Activity actions for Feb 20, 2026'))->toBe(1)
        ->and(substr_count($ascending->getContent(), 'Activity actions for Mar 01, 2026'))->toBe(1);

    $descending = $this->actingAs($admin)->get(route('activities.index', [
        'activity_type_id' => $activityType->id,
        'sort' => 'agreement',
        'direction' => 'desc',
    ]));

    $descending->assertOk();
    $descending->assertSeeInOrder([
        'Feb 20, 2026',
        'Jan 15, 2026',
        'Mar 01, 2026',
    ]);
});
