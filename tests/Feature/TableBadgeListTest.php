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

test('hidden records count toward "+N" and the total but are never named', function () {
    $this->blade(
        '<x-table-badge-list kind="agreement" :items="$items" :hidden-count="2" />',
        ['items' => [['name' => 'Kansas MRSS']]]
    )->assertSee('+2', false)
        ->assertSee('aria-label="Show all 3 agreements, 2 hidden"', false)
        ->assertSee('<span>Agreements</span><span>3</span>', false)
        ->assertSee('2 hidden');

    $this->blade('<x-table-badge-list kind="agreement" :items="[]" :hidden-count="2" />')
        ->assertSee('app-hidden-pill', false)
        ->assertSee('2 hidden')
        ->assertDontSee('—');
});
