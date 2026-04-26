@props([
    'title',
    'subtitle' => null,
])

<div {{ $attributes->merge(['class' => 'card shadow-sm']) }}>
    <div class="card-body">
        <div class="mb-3">
            <h2 class="h6 mb-1">{{ $title }}</h2>
            @if($subtitle)
                <p class="text-muted small mb-0">{{ $subtitle }}</p>
            @endif
        </div>

        {{ $slot }}
    </div>
</div>
