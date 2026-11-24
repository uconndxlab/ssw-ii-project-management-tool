<?php

dataset('empty table indexes', [
    'organizations' => ['organizations.index', 'organizations', 'Create Organization'],
    'agreements' => ['agreements.index', 'agreements', 'Create Agreement'],
]);

it('offers to clear filters when a search finds no records', function (string $indexRoute, string $plural, string $createLabel) {
    $this->actingAs(createSystemAdmin())
        ->get(route($indexRoute, ['search' => 'zzz-no-match', 'per_page' => 50, 'direction' => 'desc']))
        ->assertOk()
        ->assertSeeText("No {$plural} match your search or filters.")
        ->assertSeeHtml('href="'.route($indexRoute, ['per_page' => 50]).'" class="btn btn-sm btn-outline-secondary">Clear filters</a>')
        ->assertDontSeeHtml(">{$createLabel}</a>");
})->with('empty table indexes');

it('shows the original empty state when there is no search', function (string $indexRoute, string $plural, string $createLabel) {
    $this->actingAs(createSystemAdmin())
        ->get(route($indexRoute))
        ->assertOk()
        ->assertSeeText("No {$plural} found.")
        ->assertSeeHtml(">{$createLabel}</a>");
})->with('empty table indexes');
