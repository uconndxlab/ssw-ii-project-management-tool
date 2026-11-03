@props([
    'active' => true,
    'activeLabel' => 'Active',
    'inactiveLabel' => 'Inactive',
    'tone' => null,
])

@php
    $isActive = filter_var($active, FILTER_VALIDATE_BOOLEAN);
    $resolvedTone = $tone ?? ($isActive ? 'success' : 'neutral');
    $label = $isActive ? $activeLabel : $inactiveLabel;
@endphp

<span {{ $attributes->merge(['class' => "app-status app-status--{$resolvedTone}"]) }}>{{ $label }}</span>
