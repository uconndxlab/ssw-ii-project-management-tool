<?php

use App\Models\Activity;
use App\Models\Agreement;
use App\Models\Organization;
use App\Models\User;

test('organization show counts this year activities with zero hours and participants', function () {
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
        ->assertSee('1', false)
        ->assertSeeInOrder(['Hours', '0.0', 'Participants', '0']);
});

test('member dashboard shows zero ytd hours for an activity with no duration', function () {
    $user = User::factory()->create();
    Activity::factory()->thisYear()->create(['user_id' => $user->id]);

    $this->actingAs($user)
        ->get('/')
        ->assertOk()
        ->assertSee('My total hours ytd', false)
        ->assertSee('0', false);
});
