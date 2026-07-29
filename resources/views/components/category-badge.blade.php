@props([
    'kind' => 'default',
])

@php
    $classes = \App\Support\EntityBadge::categoryClasses($kind);
@endphp

<span {{ $attributes->merge(['class' => "badge {$classes}"]) }}>{{ $slot }}</span>
