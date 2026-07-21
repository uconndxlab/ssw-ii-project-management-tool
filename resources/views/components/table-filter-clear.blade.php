@props([
    'href',
    'filterKeys' => [],
    'wrapperClass' => 'col-auto',
])

@php
    $visible = ! empty($filterKeys) && request()->hasAny($filterKeys);
@endphp

<div class="{{ $wrapperClass }} {{ $visible ? '' : 'd-none' }}" data-table-filter-clear-wrap>
    <a href="{{ $href }}"
       class="btn btn-outline-danger btn-sm"
       data-bs-toggle="tooltip"
       data-bs-title="Clear filters"
       aria-label="Clear filters">
        <i class="bi bi-x-lg"></i>
    </a>
</div>
