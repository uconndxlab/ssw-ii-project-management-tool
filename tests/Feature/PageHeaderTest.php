<?php

use App\Models\Agreement;

test('create breadcrumbs ignore visited records while Cancel keeps its back target', function () {
    $admin = createSystemAdmin();
    $agreement = Agreement::factory()->create(['name' => 'Previously visited agreement']);
    $showUrl = route('agreements.show', $agreement);
    $this->actingAs($admin)->get($showUrl)->assertOk();

    $response = $this->get(route('agreements.create'));

    $response->assertOk()
        ->assertSee('<a href="'.route('agreements.index').'" class="text-decoration-none">Agreements</a>', false)
        ->assertSee('aria-current="page">Create Agreement</span>', false)
        ->assertDontSee('Previously visited agreement')
        ->assertSee('<a href="'.$showUrl.'" class="btn btn-sm btn-outline-secondary" id="save-bar-cancel">Cancel</a>', false);
});

test('direct create visits include the resource index without navigation history', function () {
    $response = $this->actingAs(createSystemAdmin())->get(route('programs.create'));

    $response->assertOk()
        ->assertSee('<a href="'.route('programs.index').'" class="text-decoration-none">Programs</a>', false)
        ->assertSee('aria-current="page">Create Program</span>', false);
});

test('edit breadcrumbs link the resource index and current record', function () {
    $agreement = Agreement::factory()->create(['name' => 'Current agreement']);

    $response = $this->actingAs(createSystemAdmin())->get(route('agreements.edit', $agreement));

    $response->assertOk()->assertSeeInOrder([
        '<a href="'.route('agreements.index').'" class="text-decoration-none">Agreements</a>',
        '<a href="'.route('agreements.show', $agreement).'" class="text-decoration-none">Current agreement</a>',
        'aria-current="page">Edit Agreement</span>',
    ], false);
});
