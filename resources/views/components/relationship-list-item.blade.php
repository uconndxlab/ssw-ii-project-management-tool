@props([
    'href' => null,
    'title',
    'subtitle' => null,
])

<div class="border rounded overflow-hidden bg-body px-3 py-2">
    <div class="min-w-0">
        @if($href)
            <a href="{{ $href }}" class="fw-semibold small text-decoration-underline d-block">{{ $title }}</a>
        @else
            <span class="fw-semibold small d-block text-body">{{ $title }}</span>
        @endif
        @if($subtitle)
            <div class="small text-muted mt-1">{{ $subtitle }}</div>
        @endif
    </div>
</div>
