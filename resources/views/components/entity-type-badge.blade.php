@props([
    'label',
    'badgeClass',
])

<span {{ $attributes->merge(['class' => "badge {$badgeClass} entity-type-badge"]) }}>
    {{ $label }}
</span>
