@props([
    'kind',
    'href' => null,
    'pill' => false,
    'title' => null,
])

@php
    $classes = match ($kind) {
        'project' => 'bg-primary-subtle text-primary-emphasis border',
        'program' => 'bg-warning-subtle text-warning-emphasis border',
        'agreement' => $pill ? 'bg-success' : 'bg-success-subtle text-success-emphasis border',
        'team' => $pill ? 'bg-info text-dark' : 'bg-info-subtle text-info-emphasis border',
        'state' => 'bg-info text-dark',
        default => 'bg-secondary-subtle text-secondary-emphasis border',
    };

    $pillClass = $pill ? ' rounded-pill' : '';
    $linkClass = $href ? ' text-decoration-underline' : '';
    $class = trim("badge {$classes}{$pillClass}{$linkClass}");
@endphp

@if($href)
    <a href="{{ $href }}" {{ $attributes->merge(['class' => $class, 'title' => $title]) }}>{{ $slot }}</a>
@else
    <span {{ $attributes->merge(['class' => $class, 'title' => $title]) }}>{{ $slot }}</span>
@endif
