@props([
    'kind',
    'href' => null,
    'pill' => false,
    'title' => null,
    'wrap' => false,
])

{{-- A linked record is a chip; a record the user cannot open is plain text, so a chip always means "clickable". --}}
@php
    $wrapClass = $wrap ? ' text-wrap text-start' : '';
@endphp

@if($href)
    <a href="{{ $href }}" {{ $attributes->merge(['class' => trim('badge '.\App\Support\EntityBadge::relationClasses($kind).$wrapClass), 'title' => $title]) }}>{{ $slot }}</a>
@else
    <span {{ $attributes->merge(['class' => 'app-plain', 'title' => $title]) }}>{{ $slot }}</span>
@endif
