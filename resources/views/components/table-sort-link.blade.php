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
    $sortState = $active ? ($direction === 'asc' ? 'ascending' : 'descending') : 'none';
@endphp

<a href="{{ $url }}"
   data-sort-state="{{ $sortState }}"
   class="app-sort"
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
        <span class="app-sort-idle" aria-hidden="true">
            <i class="bi bi-caret-up"></i>
            <i class="bi bi-caret-down"></i>
        </span>
    @endif
</a>
