@props([
    'title',
    'kind' => null,
    'count' => null,
    'emptyMessage' => 'None yet.',
    'headerBadgeClass' => 'bg-secondary-subtle text-secondary-emphasis border',
    'height' => '260px',
    'collapsible' => false,
    'collapsed' => false,
    'panelId' => null,
])

@php
    $resolvedPanelId = $panelId ?: 'relationship-ledger-' . \Illuminate\Support\Str::slug($title) . '-' . \Illuminate\Support\Str::random(6);
    $bodyId = $resolvedPanelId . '-body';
    $headerMetaFilled = isset($headerMeta) && filled(trim((string) $headerMeta));
    $headerActionsFilled = isset($headerActions) && filled(trim((string) $headerActions));
@endphp

@once
    <style>
        .relationship-ledger-toggle .bi {
            transition: transform 0.2s ease;
        }

        .relationship-ledger-toggle.collapsed .bi {
            transform: rotate(-180deg);
        }
    </style>
@endonce

<div {{ $attributes->merge(['class' => 'd-flex flex-column h-100']) }}>
    <div class="border rounded overflow-hidden d-flex flex-column"
         style="background-color: var(--app-surface-inset, #eef0f2);">
        <div class="small text-muted px-3 py-2 border-bottom bg-body d-flex align-items-center gap-2 flex-shrink-0">
            <div class="d-flex align-items-center gap-2 min-w-0 flex-grow-1">
                @if($collapsible)
                    <button
                        type="button"
                        class="relationship-ledger-toggle btn btn-link btn-sm p-0 text-decoration-none text-reset d-flex align-items-center justify-content-between gap-3 w-100 text-start {{ $collapsed ? 'collapsed' : '' }}"
                        data-bs-toggle="collapse"
                        data-bs-target="#{{ $bodyId }}"
                        aria-expanded="{{ $collapsed ? 'false' : 'true' }}"
                        aria-controls="{{ $bodyId }}">
                        <span class="d-flex align-items-center gap-2 min-w-0 flex-wrap">
                            <span class="fw-semibold text-body">{{ $title }}</span>
                            @if(! is_null($count))
                                @if(filled($kind))
                                    <x-entity-count-badge :kind="$kind" :count="$count" />
                                @else
                                    <span class="badge {{ $headerBadgeClass }} rounded-pill">{{ $count }}</span>
                                @endif
                            @endif
                            @if($headerMetaFilled)
                                {{ $headerMeta }}
                            @endif
                        </span>
                        <i class="bi bi-chevron-up text-muted flex-shrink-0"></i>
                    </button>
                @else
                    <div class="d-flex align-items-center gap-2 min-w-0 flex-wrap">
                        <span class="fw-semibold text-body">{{ $title }}</span>
                        @if(! is_null($count))
                            @if(filled($kind))
                                <x-entity-count-badge :kind="$kind" :count="$count" />
                            @else
                                <span class="badge {{ $headerBadgeClass }} rounded-pill">{{ $count }}</span>
                            @endif
                        @endif
                        @if($headerMetaFilled)
                            {{ $headerMeta }}
                        @endif
                    </div>
                @endif
            </div>
            @if($headerActionsFilled)
                <div class="ms-auto flex-shrink-0">{{ $headerActions }}</div>
            @endif
        </div>

        <div id="{{ $bodyId }}" class="{{ $collapsible ? 'collapse' : '' }} {{ $collapsed ? '' : 'show' }} overflow-auto" style="max-height: {{ $height }}; min-height: 0;">
            <div class="m-3 mt-2 mb-2 d-grid gap-2">
                {{ $slot }}
            </div>
        </div>
    </div>
</div>
