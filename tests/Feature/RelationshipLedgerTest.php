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

test('ledger view actions have accessible names and decorative icons outside the disclosure', function () {
    $html = $this->blade('<x-relationship-scroll-panel title="FOCUS - DEJ" title-href="/teams/1" :count="4" collapsible collapsed>Staff</x-relationship-scroll-panel>');

    $html->assertSee('aria-label="View FOCUS - DEJ"', false)
        ->assertSee('data-tip="View FOCUS - DEJ"', false)
        ->assertSee('class="bi bi-eye" aria-hidden="true"', false)
        ->assertSeeInOrder(['</button>', 'href="/teams/1"'], false);
});

test('an empty ledger can offer a named add action without an expansion control', function () {
    $html = $this->blade('<x-relationship-scroll-panel title="Contacts" :count="0" collapsible><x-slot:headerActions><a href="/contacts/create" class="btn app-ledger-action" aria-label="Add contact"><i class="bi bi-plus-lg" aria-hidden="true"></i></a></x-slot:headerActions></x-relationship-scroll-panel>');

    $html->assertSee('aria-label="Add contact"', false)
        ->assertDontSee('aria-expanded', false)
        ->assertDontSee('<button', false);
});

test('read-only records retain their metadata without gaining a link', function () {
    $this->blade('<x-relationship-ledger-row title="Restricted agreement" :meta-lines="$lines" :context-badges="[\'Recipient\']" />', ['lines' => [['label' => 'PO', 'value' => '2026-0042']]])
        ->assertSee('Restricted agreement')
        ->assertSee('Recipient')
        ->assertSee('2026-0042')
        ->assertDontSee('<a ', false)
        ->assertDontSee('app-ledger-row--link', false)
        ->assertDontSee('bi-chevron-right', false);
});

test('restricted records appear only in the ledger heading with a lock', function (bool $collapsible) {
    $html = $this->blade('<x-relationship-scroll-panel title="Agreements" :count="3" :hidden-count="2" :collapsible="$collapsible">Visible agreement</x-relationship-scroll-panel>', compact('collapsible'));

    $html->assertSeeInOrder(['app-count--restricted', 'bi-lock-fill', '2 restricted', 'Visible agreement'], false)
        ->assertSee("You don't have access to 2 agreements", false)
        ->assertDontSee('app-ledger-hidden-row', false)
        ->assertDontSee('eye-slash', false);

    expect(substr_count((string) $html, '2 restricted'))->toBe(1);
})->with([true, false]);

test('restricted counts pluralize their kind and retain extra classes', function (int $count, string $kind, string $noun) {
    $this->blade('<x-restricted-count :count="$count" :kind="$kind" class="extra" />', compact('count', 'kind'))
        ->assertSee('app-count app-count--restricted extra', false)
        ->assertSee("You don't have access to {$count} {$noun}", false)
        ->assertSee($count.' restricted');
})->with([[1, 'Certificates', 'certificate'], [2, 'certificate', 'certificates'], [2, 'activities', 'activities']]);

test('nonpositive restricted counts render no badge', function (int $count) {
    $this->blade('<x-restricted-count :count="$count" kind="records" />', compact('count'))
        ->assertDontSee('<span', false);
})->with([0, -1]);

test('linked ledger rows expose a row target and decorative chevron', function () {
    $this->blade('<x-relationship-ledger-row title="Agreement" href="/agreements/1" title-as-badge />')
        ->assertSee('app-ledger-row--link', false)
        ->assertSee('href="/agreements/1" class="app-ledger-name"', false)
        ->assertSee('bi-chevron-right app-ledger-chevron" aria-hidden="true"', false);
});

test('multi-link ledger rows retain separate destinations without a row target', function () {
    $this->blade('<x-relationship-ledger-row title="Candidate" href="/candidates/1" multi-link><x-slot:footer><a href="/certificates/1">Certificate</a></x-slot:footer></x-relationship-ledger-row>')
        ->assertSee('href="/candidates/1"', false)
        ->assertSee('href="/certificates/1"', false)
        ->assertDontSee('app-ledger-row--link', false)
        ->assertDontSee('bi-chevron-right', false);
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
