@props([
    'active' => true,
    'activeLabel' => 'Active',
    'inactiveLabel' => 'Inactive',
])

@php
    $isActive = filter_var($active, FILTER_VALIDATE_BOOLEAN);
    $class = $isActive ? 'bg-success' : 'bg-secondary';
    $label = $isActive ? $activeLabel : $inactiveLabel;
@endphp

<span {{ $attributes->merge(['class' => "badge {$class} rounded-pill"]) }}>{{ $label }}</span>
