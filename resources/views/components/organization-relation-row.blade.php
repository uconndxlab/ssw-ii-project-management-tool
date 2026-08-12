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

    if (filled($organization->po_number)) {
        $metaLines[] = ['label' => 'PO', 'value' => $organization->po_number];
    }

    if (!empty($kfsNumbers)) {
        $metaLines[] = ['label' => 'KFS', 'value' => implode(', ', $kfsNumbers)];
    }
@endphp

<x-relationship-ledger-row
    :title="$organization->name"
    :href="$href"
    kind="organization"
    title-as-badge
    wrap-title
    :context-badges="$contextBadges"
    :meta-lines="$metaLines"
    {{ $attributes }}
/>
