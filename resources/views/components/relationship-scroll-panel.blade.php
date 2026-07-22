@props([
    'title',
    'count' => null,
    'emptyMessage' => 'None yet.',
    'headerBadgeClass' => 'bg-secondary-subtle text-secondary-emphasis border',
    'height' => '300px',
])

<div {{ $attributes->merge(['class' => 'd-flex flex-column h-100']) }}>
    <div class="border rounded overflow-hidden d-flex flex-column"
         style="height: {{ $height }}; background-color: var(--app-surface-inset, #eef0f2);">
        <div class="small text-muted px-3 py-2 border-bottom bg-body d-flex align-items-center gap-2 flex-shrink-0">
            <div class="d-flex align-items-center gap-2 min-w-0">
                <span class="fw-semibold text-body">{{ $title }}</span>
                @if(! is_null($count))
                    <span class="badge {{ $headerBadgeClass }} rounded-pill">{{ $count }}</span>
                @endif
            </div>
            @isset($headerActions)
                <div class="ms-auto flex-shrink-0">{{ $headerActions }}</div>
            @endisset
        </div>

        <div class="flex-grow-1 overflow-auto" style="min-height: 0;">
            <div class="m-3 mt-2 mb-2 d-grid gap-2">
                {{ $slot }}
            </div>
        </div>
    </div>
</div>
