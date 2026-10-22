<?php

use App\Models\ContactFamily;
use App\Models\State;
use Illuminate\Pagination\LengthAwarePaginator;

test('every listing applies the selected page size', function (string $route, string $records) {
    $this->actingAs(createSystemAdmin())
        ->get(route($route, ['per_page' => 10]))
        ->assertOk()
        ->assertViewHas($records, fn (LengthAwarePaginator $paginator) => $paginator->perPage() === 10)
        ->assertSee('aria-label="Rows per page"', false);
})->with([
    ['agreements.index', 'agreements'],
    ['activities.index', 'activities'],
    ['organizations.index', 'organizations'],
    ['states.index', 'states'],
    ['projects.index', 'projects'],
    ['programs.index', 'programs'],
    ['teams.index', 'teams'],
    ['logging-fields.index', 'loggingFields'],
    ['admin.users.index', 'users'],
    ['supervisees.index', 'users'],
    ['activity-types.index', 'activityTypes'],
    ['contact-families.index', 'contactFamilies'],
    ['certification-tools.index', 'certificationTools'],
    ['certificates.index', 'certificates'],
]);

test('page sizes are limited to the supported choices', function (mixed $size, int $expected) {
    $this->actingAs(createSystemAdmin())
        ->get(route('states.index', ['per_page' => $size]))
        ->assertOk()
        ->assertViewHas('states', fn (LengthAwarePaginator $paginator) => $paginator->perPage() === $expected);
})->with([
    'missing' => [null, 25],
    'twenty-five' => [25, 25],
    'fifty' => [50, 50],
    'one hundred' => [100, 100],
    'zero' => [0, 25],
    'negative' => [-10, 25],
    'excessive' => [10000, 25],
    'text' => ['invalid', 25],
    'array' => [[10], 25],
]);

test('pagination returns the selected slice and retains search and sorting', function () {
    State::factory()->count(11)->sequence(fn ($sequence) => ['name' => 'Pagination state '.$sequence->index])->create();

    $this->actingAs(createSystemAdmin())
        ->get(route('states.index', ['search' => 'Pagination state', 'sort' => 'name', 'direction' => 'desc', 'per_page' => 10, 'page' => 2]))
        ->assertOk()
        ->assertViewHas('states', fn (LengthAwarePaginator $paginator) => $paginator->count() === 1 && $paginator->total() === 11 && $paginator->currentPage() === 2)
        ->assertSee('name="search" value="Pagination state"', false)
        ->assertSee('name="direction" value="desc"', false)
        ->assertSee('name="page" value="1"', false)
        ->assertSee('per_page=10', false);
});

test('activity families paginate instead of loading the entire list', function () {
    ContactFamily::factory()->count(26)->create();

    $this->actingAs(createSystemAdmin())
        ->get(route('contact-families.index'))
        ->assertOk()
        ->assertViewHas('contactFamilies', fn (LengthAwarePaginator $paginator) => $paginator->count() === 25 && $paginator->total() === 26)
        ->assertSee('26 total');
});

test('an empty listing keeps its page-size control and shows a zero range', function () {
    $this->actingAs(createSystemAdmin())
        ->get(route('states.index'))
        ->assertOk()
        ->assertSee('aria-label="Rows per page"', false)
        ->assertSee('Showing 0–0');
});
