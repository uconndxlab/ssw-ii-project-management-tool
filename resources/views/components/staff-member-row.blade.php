@props([
    'name',
    'href' => null,
    'role' => null,
    'isPrincipalInvestigator' => false,
])

<div {{ $attributes->merge(['class' => 'py-2']) }}>
    <div class="d-flex flex-wrap align-items-center gap-1 small">
        @if($href)
            <a href="{{ $href }}" class="fw-semibold text-decoration-underline">{{ $name }}</a>
        @else
            <span class="fw-semibold text-dark">{{ $name }}</span>
        @endif

        @if($role)
            <x-category-badge kind="role">{{ ucfirst($role) }}</x-category-badge>
        @endif

        @if($isPrincipalInvestigator)
            <x-category-badge kind="pi">PI</x-category-badge>
        @endif
    </div>

    @isset($after)
        <div class="d-flex flex-wrap gap-1 mt-1">
            {{ $after }}
        </div>
    @endisset
</div>
