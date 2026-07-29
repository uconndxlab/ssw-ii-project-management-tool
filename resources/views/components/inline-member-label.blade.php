@props([
    'name',
    'role' => null,
])

<span {{ $attributes->merge(['class' => 'd-inline-flex align-items-center gap-1 small']) }}>
    <span class="fw-semibold text-dark">{{ $name }}</span>
    @if(filled($role))
        <x-category-badge kind="role">{{ ucfirst($role) }}</x-category-badge>
    @endif
</span>
