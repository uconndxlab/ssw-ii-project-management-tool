@props([
    'title' => null,
    'meta' => null,
])

<div {{ $attributes->class(['form-subsection']) }}>
    @if(filled($title) || filled($meta))
        <div class="form-subsection-header">
            @if(filled($title))
                <div class="form-subsection-title">{{ $title }}</div>
            @endif
            <div class="form-subsection-meta{{ filled($meta) ? '' : ' d-none' }}">{{ $meta }}</div>
        </div>
    @endif

    {{ $slot }}
</div>
