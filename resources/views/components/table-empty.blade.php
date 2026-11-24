@props(['colspan', 'plural', 'indexRoute'])

@php
    $query = request()->query();
    $filtered = $indexRoute !== null && collect($query)
        ->except(['page', 'per_page', 'sort', 'direction'])
        ->flatten()
        ->contains(fn ($value) => filled($value));
@endphp

<tr class="app-table-empty">
    <td colspan="{{ $colspan }}" {{ $attributes->class(['text-center', 'py-5']) }}>
        @if($filtered)
            <p class="text-muted mb-2">No {{ $plural }} match your search or filters.</p>
            <a href="{{ route($indexRoute, \Illuminate\Support\Arr::only($query, ['per_page'])) }}" class="btn btn-sm btn-outline-secondary">Clear filters</a>
        @else
            {{ $slot }}
        @endif
    </td>
</tr>
