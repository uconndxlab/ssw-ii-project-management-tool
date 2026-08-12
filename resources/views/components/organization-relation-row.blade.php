@props([
    'organization',
    'showRoute' => null,
    'kfsNumbers' => [],
])

@php
    $href = $showRoute ?? route('organizations.show', $organization);
    $contextBadges = [];

    if ($organization->pivot->payor_source ?? false) {
        $contextBadges[] = ['label' => 'Payor source', 'kind' => 'payor-source'];
    }

    if ($organization->pivot->recipient ?? false) {
        $contextBadges[] = ['label' => 'Recipient', 'kind' => 'recipient'];
    }

    $metaLines = collect($kfsNumbers)
        ->map(fn ($number) => ['label' => 'KFS', 'value' => $number])
        ->values();

    if (filled($organization->po_number)) {
        $metaLines->push(['label' => 'PO', 'value' => $organization->po_number]);
    }
@endphp

<x-relationship-ledger-row
    :title="$organization->name"
    :href="$href"
    kind="organization"
    title-as-badge
    wrap-title
    :context-badges="$contextBadges"
    :meta-lines="$metaLines->all()"
    {{ $attributes }}
/>
