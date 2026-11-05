@props([
    'tabs',
    'label',
    'hash' => false,
    'active' => null,
])

@php
    $active ??= array_key_first($tabs);
@endphp

<div data-section-tabs @if($hash) data-section-tabs-hash @endif>
    <nav {{ $attributes->class(['nav', 'nav-pills', 'gap-4', 'cert-sections']) }} aria-label="{{ $label }}">
        @foreach($tabs as $key => $title)
            @if($hash)
                <a href="#{{ $key }}" @class(['nav-link', 'active' => $key === $active]) data-section-nav="{{ $key }}" aria-controls="section-{{ $key }}" @if($key === $active) aria-current="page" @endif>{{ $title }}</a>
            @else
                <button type="button" @class(['nav-link', 'active' => $key === $active]) data-section-nav="{{ $key }}" aria-controls="section-{{ $key }}" @if($key === $active) aria-current="page" @endif>{{ $title }}</button>
            @endif
        @endforeach
    </nav>
    {{ $slot }}
</div>
