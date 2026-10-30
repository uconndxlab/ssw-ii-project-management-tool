<?php

test('a long related-record list shows one chip and a "+N" button that lists every record', function () {
    $html = $this->blade(
        '<x-table-badge-list kind="state" :items="$items" href-key="href" />',
        ['items' => [
            ['name' => 'Alabama', 'href' => '/states/1'],
            ['name' => 'Alaska', 'href' => null],
            ['name' => 'Arizona', 'href' => '/states/3'],
        ]]
    );

    $html->assertSee('+2', false)
        ->assertSee('aria-label="Show all 3 states"', false)
        ->assertSee('<a href="/states/1" class="badge app-chip"', false)
        ->assertSee('<a class="dropdown-item" href="/states/3">Arizona</a>', false);
});

test('records the user cannot open are plain text, never links', function () {
    $html = $this->blade(
        '<x-table-badge-list kind="state" :items="$items" href-key="href" />',
        ['items' => [
            ['name' => 'Alabama', 'href' => '/states/1'],
            ['name' => 'Alaska', 'href' => null],
        ]]
    );

    $html->assertSee('<span class="dropdown-item-text">Alaska</span>', false)
        ->assertDontSee('/states/2', false);
});

test('a single related record has no "+N" button', function () {
    $html = $this->blade(
        '<x-table-badge-list kind="state" :items="$items" href-key="href" />',
        ['items' => [['name' => 'Alabama', 'href' => '/states/1']]]
    );

    $html->assertDontSee('app-more-btn', false);
});
