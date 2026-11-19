<?php

use App\Models\Organization;

test('search matches names regardless of letter case', function () {
    Organization::factory()->create(['name' => 'Riverbend Clinic']);
    Organization::factory()->create(['name' => 'Lakeside Center']);

    $this->actingAs(createSystemAdmin())
        ->get(route('search', ['q' => 'rIVERBEND']))
        ->assertOk()
        ->assertSee('Riverbend Clinic')
        ->assertDontSee('Lakeside Center');
});
