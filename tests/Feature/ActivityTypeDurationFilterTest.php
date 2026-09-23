<?php

use App\Models\ActivityType;
use App\Support\ActivityTypeDuration;

test('activity type duration filter drops empty string program ids and keeps numeric ids', function () {
    $type = ActivityType::factory()->create();
    $type->setRelation('programs', collect());

    $filtered = ActivityTypeDuration::filterActivityTypesInScope(
        collect([$type]),
        $type->contact_family_id,
        $type->id,
        ['', '12', 4]
    );

    expect($filtered)->toHaveCount(1)
        ->and($filtered->first()->id)->toBe($type->id);
});
