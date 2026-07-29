@props([
    'organization',
    'showRoute' => null,
])

@php
    $href = $showRoute ?? route('organizations.show', $organization);
@endphp

<div {{ $attributes->merge(['class' => 'py-2 min-w-0']) }}>
    <div class="d-flex flex-wrap align-items-center gap-1 min-w-0">
        <x-entity-relation-badge kind="organization" :href="$href" wrap>
            {{ $organization->name }}
        </x-entity-relation-badge>

        @if($organization->pivot->payor_source ?? false)
            <x-category-badge kind="payor-source">Payor source</x-category-badge>
        @endif

        @if($organization->pivot->recipient ?? false)
            <x-category-badge kind="recipient">Recipient</x-category-badge>
        @endif
    </div>

    @if(($organization->pivot->payor_source ?? false) && filled($organization->kfs_number))
        <div class="text-muted small mt-1">{{ $organization->kfs_number }}</div>
    @endif
</div>
