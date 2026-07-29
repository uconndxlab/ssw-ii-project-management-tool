@props([
    'kind',
    'href' => null,
    'pill' => false,
    'title' => null,
    'wrap' => false,
])

@php
    $classes = \App\Support\EntityBadge::relationClasses($kind, $pill);
    $linkClass = $href ? ' text-decoration-underline' : '';
    $wrapClass = $wrap ? ' text-wrap text-start d-inline-block' : '';
    $class = trim("badge {$classes}{$linkClass}{$wrapClass}");
    $mergeAttributes = ['class' => $class, 'title' => $title];

    if ($wrap) {
        $mergeAttributes['style'] = 'max-width: 100%; white-space: normal;';
    }
@endphp

@if($href)
    <a href="{{ $href }}" {{ $attributes->merge($mergeAttributes) }}>{{ $slot }}</a>
@else
    <span {{ $attributes->merge($mergeAttributes) }}>{{ $slot }}</span>
@endif
