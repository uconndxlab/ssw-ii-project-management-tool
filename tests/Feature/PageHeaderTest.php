<?php

use App\Models\Agreement;
use App\Models\User;

test('create breadcrumbs ignore visited records and Cancel returns to the index', function () {
    $admin = createSystemAdmin();
    $agreement = Agreement::factory()->create(['name' => 'Previously visited agreement']);
    $showUrl = route('agreements.show', $agreement);
    $this->actingAs($admin)->get($showUrl)->assertOk();

    $response = $this->get(route('agreements.create'));

    $response->assertOk()
        ->assertSee('<a href="'.route('agreements.index').'" class="text-decoration-none">Agreements</a>', false)
        ->assertSee('aria-current="page">Create Agreement</span>', false)
        ->assertDontSee('Previously visited agreement')
        ->assertSee('<a href="'.route('agreements.index').'" class="btn btn-sm btn-outline-secondary" id="save-bar-cancel">Cancel</a>', false);
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

test('Cancel on a create form keeps the filters of the index the user came from', function () {
    $filteredIndex = route('agreements.index', ['sort' => 'name']);

    $this->actingAs(createSystemAdmin())->get($filteredIndex)->assertOk();

    $this->get(route('agreements.create'))
        ->assertOk()
        ->assertSee('<a href="'.$filteredIndex.'" class="btn btn-sm btn-outline-secondary" id="save-bar-cancel">Cancel</a>', false);
});

test('Cancel on an edit form returns to the record', function () {
    $agreement = Agreement::factory()->create();

    $this->actingAs(createSystemAdmin())->get(route('dashboard'))->assertOk();

    $this->get(route('agreements.edit', $agreement))
        ->assertOk()
        ->assertSee('<a href="'.route('agreements.show', $agreement).'" class="btn btn-sm btn-outline-secondary" id="save-bar-cancel">Cancel</a>', false);
});

test('Cancel ignores a previous page from another site', function () {
    $this->actingAs(createSystemAdmin())
        ->get(route('programs.create'), ['Referer' => 'https://evil.example'.parse_url(route('programs.index'), PHP_URL_PATH)])
        ->assertOk()
        ->assertSee('<a href="'.route('programs.index').'" class="btn btn-sm btn-outline-secondary" id="save-bar-cancel">Cancel</a>', false);
});

test('Cancel on the profile form returns to the profile', function () {
    $this->actingAs(User::factory()->create())
        ->get(route('profile.edit'))
        ->assertOk()
        ->assertSee('<a href="'.route('profile').'" class="btn btn-sm btn-outline-secondary" id="save-bar-cancel">Cancel</a>', false);
});
