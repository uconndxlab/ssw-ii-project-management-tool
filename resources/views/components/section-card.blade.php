@props([
    'title',
    'subtitle' => null,
])

<div {{ $attributes->class(['card', 'shadow-sm', 'mb-4', 'mw-100', 'min-w-0']) }}>
    <div class="card-body min-w-0">
        <div class="d-flex flex-wrap justify-content-between align-items-start gap-3 mb-3">
            <div class="min-w-0">
                <h2 class="h5 mb-0">{{ $title }}</h2>
                @if($subtitle)
                    <p class="text-muted small mb-0 mt-1">{{ $subtitle }}</p>
                @endif
            </div>
            @isset($actions)
                <div class="flex-shrink-0">
                    {{ $actions }}
                </div>
            @endisset
        </div>

        {{ $slot }}
    </div>
</div>
