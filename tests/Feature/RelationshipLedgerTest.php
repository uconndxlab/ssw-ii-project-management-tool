<?php

test('short ledgers stay open even when collapse is requested', function (int $count) {
    $this->blade('<x-relationship-scroll-panel title="People" :count="$count" collapsible collapsed>People</x-relationship-scroll-panel>', compact('count'))
        ->assertSee('aria-expanded="true"', false)
        ->assertSee('class="collapse show"', false);
})->with([1, 6]);

test('long ledgers can start collapsed', function () {
    $this->blade('<x-relationship-scroll-panel title="People" :count="7" collapsible collapsed>People</x-relationship-scroll-panel>')
        ->assertSee('aria-expanded="false"', false);
});

test('empty ledgers show an explanation without a disclosure control', function () {
    $this->blade('<x-relationship-scroll-panel title="People" :count="0" empty-message="No people assigned." collapsible />')
        ->assertSee('No people assigned.')
        ->assertDontSee('<button', false)
        ->assertSee('hidden', false);
});

test('ledger actions remain separate from the disclosure button', function () {
    $html = $this->blade('<x-relationship-scroll-panel title="People" title-href="/people" :count="2" collapsible><x-slot:headerActions><a href="/people/create">Add person</a></x-slot:headerActions></x-relationship-scroll-panel>');

    $html->assertSeeInOrder(['</button>', 'href="/people"', 'href="/people/create"'], false);
});

test('read-only records retain their metadata without gaining a link', function () {
    $this->blade('<x-relationship-ledger-row title="Restricted agreement" :meta-lines="$lines" :context-badges="[\'Recipient\']" />', ['lines' => [['label' => 'PO', 'value' => '2026-0042']]])
        ->assertSee('Restricted agreement')
        ->assertSee('Recipient')
        ->assertSee('2026-0042')
        ->assertDontSee('<a ', false);
});
