@props([
    'title' => null,
    'tone' => 'info',
])

@php
    $icon = $tone === 'warning' ? 'bi-exclamation-triangle-fill' : 'bi-info-circle-fill';
@endphp

<div {{ $attributes->class(['app-callout', 'app-callout--'.$tone, 'app-form-note', 'mb-4']) }} role="note">
    <i class="bi {{ $icon }} app-callout-icon" aria-hidden="true"></i>
    <div class="app-callout-body">
        @if(filled($title))
            <div class="app-callout-title">{{ $title }}</div>
        @endif
        {{ $slot }}
    </div>
</div>
