@props([
    'tabs',
    'label',
    'hash' => false,
    'active' => null,
    'stickyTitle' => null,
])

@php
    $active ??= array_key_first($tabs);
@endphp

<div data-section-tabs @if($hash) data-section-tabs-hash @endif>
    @isset($actions)
        <div data-record-bar-sentinel class="app-record-bar-sentinel" aria-hidden="true"></div>
        <div class="app-record-bar-wrap" data-record-bar>
            <div class="app-record-bar">
                @if($stickyTitle)
                    <div class="app-record-bar-title" aria-hidden="true"><i class="bi bi-file-earmark-text"></i><strong class="text-truncate">{{ $stickyTitle }}</strong></div>
                @endif
                <nav class="nav nav-pills gap-4 app-record-bar-nav" aria-label="{{ $label }}">
                    @foreach($tabs as $key => $title)
                        <a href="#{{ $key }}" @class(['nav-link', 'active' => $key === $active]) data-section-nav="{{ $key }}" aria-controls="section-{{ $key }}" @if($key === $active) aria-current="page" @endif>{{ $title }}</a>
                    @endforeach
                </nav>
                <span class="app-record-bar-divider" aria-hidden="true"></span>
                <div class="app-record-bar-actions">{{ $actions }}</div>
            </div>
        </div>
    @else
    <nav {{ $attributes->class(['nav', 'nav-pills', 'gap-4', 'cert-sections']) }} aria-label="{{ $label }}">
        @foreach($tabs as $key => $title)
            @if($hash)
                <a href="#{{ $key }}" @class(['nav-link', 'active' => $key === $active]) data-section-nav="{{ $key }}" aria-controls="section-{{ $key }}" @if($key === $active) aria-current="page" @endif>{{ $title }}</a>
            @else
                <button type="button" @class(['nav-link', 'active' => $key === $active]) data-section-nav="{{ $key }}" aria-controls="section-{{ $key }}" @if($key === $active) aria-current="page" @endif>{{ $title }}</button>
            @endif
        @endforeach
    </nav>
    @endisset
    {{ $slot }}
</div>
