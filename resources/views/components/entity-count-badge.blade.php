@props([
    'kind',
    'count',
])

@php
    $classes = \App\Support\EntityBadge::countClasses($kind);
@endphp

<span {{ $attributes->merge(['class' => "badge {$classes}"]) }}>{{ $count }}</span>
