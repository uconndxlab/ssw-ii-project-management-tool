@props([
    'items' => [],
])

@php
    $items = collect($items)->values();

    if ($items->count() > 4) {
        $items = collect([
            $items->first(),
            ['label' => '...', 'url' => null, 'current' => false, 'ellipsis' => true],
        ])->concat($items->slice(-2))->values();
    }
@endphp

@if($items->isNotEmpty())
    <nav aria-label="Breadcrumb" class="mb-2">
        <ol class="breadcrumb small mb-0 flex-wrap">
            @foreach($items as $item)
                <li class="breadcrumb-item {{ !empty($item['current']) ? 'active' : '' }}">
                    @if(!empty($item['ellipsis']))
                        <span aria-hidden="true">&hellip;</span>
                        <span class="visually-hidden">More breadcrumbs</span>
                    @elseif(!empty($item['current']) || empty($item['url']))
                        <span>{{ $item['label'] }}</span>
                    @else
                        <a href="{{ $item['url'] }}" class="text-decoration-none">{{ $item['label'] }}</a>
                    @endif
                </li>
            @endforeach
        </ol>
    </nav>
@endif
