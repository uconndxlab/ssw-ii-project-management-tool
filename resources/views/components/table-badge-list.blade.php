@props([
    'items' => null,
    'kind',
    'routeName' => null,
    'itemKey' => null,
    'labelKey' => 'name',
    'titleKey' => null,
    'hrefKey' => null,
    'max' => 5,
    'emptyLabel' => '—',
    'emptyClass' => 'text-muted small',
    'containerClass' => 'd-flex flex-wrap gap-1',
    'ellipsisLabel' => '...',
])

@php
    $normalizedItems = collect($items ?? [])
        ->map(function ($item) use ($itemKey, $labelKey, $titleKey, $hrefKey, $routeName) {
            $source = filled($itemKey) ? data_get($item, $itemKey) : $item;
            $labelSource = $source ?? $item;

            $label = data_get($labelSource, $labelKey);

            if (blank($label) && $labelSource !== $item) {
                $label = data_get($item, $labelKey);
            }

            if (blank($label) && is_string($labelSource)) {
                $label = $labelSource;
            }

            $title = filled($titleKey)
                ? (data_get($item, $titleKey) ?? data_get($labelSource, $titleKey))
                : null;

            $href = filled($hrefKey)
                ? data_get($item, $hrefKey)
                : (filled($routeName) && filled($source) ? route($routeName, $source) : null);

            if ($href && is_object($source) && method_exists($source, 'isLinkable') && ! $source->isLinkable()) {
                $href = null;
            }

            return [
                'label' => filled($label) ? (string) $label : null,
                'title' => $title,
                'href' => $href,
            ];
        })
        ->filter(fn ($item) => filled($item['label']))
        ->sortBy(fn ($item) => mb_strtolower($item['label']))
        ->values();

    $visibleItems = $normalizedItems->take($max);
    $hiddenCount = max($normalizedItems->count() - $visibleItems->count(), 0);
@endphp

<div class="{{ $containerClass }}">
    @if($visibleItems->isEmpty())
        <span class="{{ $emptyClass }}">{{ $emptyLabel }}</span>
    @else
        @foreach($visibleItems as $item)
            <x-entity-relation-badge :kind="$kind" :href="$item['href']" :title="$item['title']">
                {{ $item['label'] }}
            </x-entity-relation-badge>
        @endforeach

        @if($hiddenCount > 0)
            <span class="badge bg-light text-muted border" title="{{ $hiddenCount }} more">{{ $ellipsisLabel }}</span>
        @endif
    @endif
</div>
