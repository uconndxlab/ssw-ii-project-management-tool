@props([
    'id',
    'title' => '',
    'titleId' => null,
])

@php
    $titleId = $titleId ?: $id . '-title';
@endphp

<div
    {{ $attributes->class(['form-drawer'])->merge([
        'id' => $id,
        'aria-hidden' => 'true',
    ]) }}
>
    <div class="form-drawer-backdrop" data-form-drawer-close></div>
    <aside class="form-drawer-panel" role="dialog" aria-modal="true" aria-labelledby="{{ $titleId }}" tabindex="-1">
        <div class="form-drawer-header">
            <div class="min-w-0 flex-grow-1">
                <h2 class="h5 mb-0" id="{{ $titleId }}">{{ $title }}</h2>
                @isset($description)
                    <div class="text-muted small mt-1">{{ $description }}</div>
                @endisset
                @isset($summary)
                    {{ $summary }}
                @endisset
            </div>
            <button type="button" class="btn-close flex-shrink-0" data-form-drawer-close aria-label="Close"></button>
        </div>
        <div class="form-drawer-body">
            {{ $slot }}
        </div>
        @isset($footer)
            <div class="form-drawer-footer">
                {{ $footer }}
            </div>
        @endisset
    </aside>
</div>
