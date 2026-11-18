<?php

use App\Models\Agreement;
use App\Models\Organization;

test('organization edit page explains log activity visibility only until an agreement is attached', function () {
    $admin = createSystemAdmin();
    $organization = Organization::factory()->create();
    $note = 'Organizations appear on Log Activity only after';

    $this->actingAs($admin)
        ->get(route('organizations.edit', $organization))
        ->assertOk()
        ->assertSee($note);

    $organization->agreements()->attach(Agreement::factory()->create());

    $this->actingAs($admin)
        ->get(route('organizations.edit', $organization))
        ->assertOk()
        ->assertDontSee($note);
});
