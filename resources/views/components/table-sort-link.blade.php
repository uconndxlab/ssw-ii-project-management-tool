@props([
    'column',
    'label',
    'sort',
    'direction',
    'url',
    'target' => '#users-table',
])

@php
    $active = $sort === $column;
@endphp

<a href="{{ $url }}"
   class="text-decoration-none text-dark fw-semibold d-inline-flex align-items-center gap-1"
   hx-get="{{ $url }}"
   hx-target="{{ $target }}"
   hx-push-url="true">
    {{ $label }}
    @if($active)
        @if($direction === 'asc')
            <i class="bi bi-caret-up-fill ms-1" aria-hidden="true"></i>
        @else
            <i class="bi bi-caret-down-fill ms-1" aria-hidden="true"></i>
        @endif
    @else
        <span class="d-inline-flex flex-column lh-1 ms-1 text-muted opacity-50" style="font-size: .65rem;" aria-hidden="true">
            <i class="bi bi-caret-up"></i>
            <i class="bi bi-caret-down"></i>
        </span>
    @endif
</a>
