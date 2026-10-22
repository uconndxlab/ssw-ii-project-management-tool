@props([
    'title',
    'titleHref' => null,
    'kind' => null,
    'count' => null,
    'emptyMessage' => 'None yet.',
    'height' => '260px',
    'collapsible' => false,
    'collapsed' => false,
    'panelId' => null,
    'showHeader' => true,
])

@php
    $resolvedPanelId = $panelId ?: 'relationship-ledger-' . \Illuminate\Support\Str::slug($title) . '-' . \Illuminate\Support\Str::random(6);
    $bodyId = $resolvedPanelId . '-body';
    $isEmpty = ! is_null($count) && (int) $count === 0;
    $canCollapse = $showHeader && $collapsible && ! $isEmpty;
    $isCollapsed = $canCollapse && $collapsed;
    $headerMetaFilled = isset($headerMeta) && filled(trim((string) $headerMeta));
    $headerActionsFilled = isset($headerActions) && filled(trim((string) $headerActions));
@endphp

<section {{ $attributes->class(['app-ledger', 'app-ledger--empty' => $isEmpty]) }}>
    @if($showHeader)
        <div class="app-ledger-head">
            <h3 class="app-ledger-heading">
                @if($canCollapse)
                    <button type="button"
                            class="app-ledger-toggle {{ $isCollapsed ? 'collapsed' : '' }}"
                            data-bs-toggle="collapse"
                            data-bs-target="#{{ $bodyId }}"
                            aria-expanded="{{ $isCollapsed ? 'false' : 'true' }}"
                            aria-controls="{{ $bodyId }}">
                        <span class="app-ledger-label">{{ $title }} @if(! is_null($count))<span class="app-count">{{ $count }}</span>@endif</span>
                        <span class="app-ledger-preview" data-ledger-preview aria-hidden="true" hidden></span>
                        <i class="bi bi-chevron-right" aria-hidden="true"></i>
                    </button>
                @else
                    <span class="app-ledger-label">{{ $title }} @if(! is_null($count))<span class="app-count">{{ $count }}</span>@endif</span>
                @endif
            </h3>
            @if($headerMetaFilled)
                {{ $headerMeta }}
            @endif
            @if(filled($titleHref))
                <a href="{{ $titleHref }}" class="app-ledger-view-link" aria-label="View {{ $title }}">View</a>
            @endif
            @if($isEmpty)
                <span class="app-ledger-empty-message">{{ $emptyMessage }}</span>
            @endif
            @if($headerActionsFilled)
                <div class="app-ledger-header-actions">{{ $headerActions }}</div>
            @endif
        </div>
    @endif

    <div id="{{ $bodyId }}" class="{{ $canCollapse ? 'collapse' : '' }} {{ ! $isCollapsed && ! $isEmpty ? 'show' : '' }}" @if($isEmpty) hidden @endif>
        <div class="app-ledger-scroll-frame">
            <div class="app-ledger-body"
                 data-ledger-scroll
                 data-scroll-label="{{ $title }}"
                 aria-describedby="{{ $bodyId }}-scroll-status"
                 style="--app-ledger-height: {{ $height }};">
                {{ $slot }}
            </div>
        </div>
        <p class="app-ledger-scroll-status" id="{{ $bodyId }}-scroll-status" data-ledger-scroll-status hidden></p>
    </div>
</section>
