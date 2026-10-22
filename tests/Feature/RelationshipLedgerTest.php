<?php

use App\Models\Organization;
use App\Models\OrganizationContact;
use App\Models\State;

test('summary ledgers honor their closed default regardless of size', function (int $count) {
    $this->blade('<x-relationship-scroll-panel title="People" :count="$count" collapsible collapsed>People</x-relationship-scroll-panel>', compact('count'))
        ->assertSee('aria-expanded="false"', false)
        ->assertDontSee('class="collapse show"', false);
})->with([1, 6, 7]);

test('wider relationship ledgers remain open by default', function (int $count) {
    $this->blade('<x-relationship-scroll-panel title="People" :count="$count" collapsible>People</x-relationship-scroll-panel>', compact('count'))
        ->assertSee('aria-expanded="true"', false);
})->with([1, 7]);

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

test('organization quick facts lead with YTD totals and related summaries start closed', function () {
    $organization = Organization::factory()->create(['po_number' => '987654']);
    OrganizationContact::factory()->for($organization)->create();
    $organization->states()->attach(State::factory()->create());
    $organization->users()->attach(createSystemAdmin());

    $response = $this->actingAs(createSystemAdmin())->get(route('organizations.show', $organization))->assertOk();
    $response->assertSeeInOrder(['PO number', '987654', 'Year to date', 'Contacts', 'States', 'Associated users']);

    $dom = new DOMDocument;
    libxml_use_internal_errors(true);
    $dom->loadHTML($response->getContent());
    libxml_clear_errors();
    $xpath = new DOMXPath($dom);

    expect($xpath->query('//*[@data-record-summary]//button[@data-bs-toggle="collapse"][@aria-expanded="false"]')->length)->toBe(3)
        ->and($xpath->query('//*[@data-record-summary]//button[@aria-expanded="true"]')->length)->toBe(0);
});
