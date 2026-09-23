<?php

use App\Models\Activity;
use App\Models\Agreement;
use App\Models\Organization;

test('organization show counts this year activities', function () {
    $organization = Organization::factory()->create();
    $agreement = Agreement::factory()->current()->create();
    $agreement->organizations()->attach($organization->id, [
        'payor_source' => false,
        'recipient' => false,
    ]);

    $thisYear = Activity::factory()->thisYear()->create();
    $lastYear = Activity::factory()->create([
        'engagement_date' => now()->subYear(),
    ]);
    $thisYear->agreements()->attach($agreement);
    $lastYear->agreements()->attach($agreement);

    $this->actingAs(createSystemAdmin())
        ->get(route('organizations.show', $organization))
        ->assertOk()
        ->assertSee('1', false);
});
