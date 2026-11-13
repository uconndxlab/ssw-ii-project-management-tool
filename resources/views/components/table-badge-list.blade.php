@props([
    'items' => null,
    'kind',
    'routeName' => null,
    'itemKey' => null,
    'labelKey' => 'name',
    'titleKey' => null,
    'hrefKey' => null,
    'max' => 1,
    'emptyLabel' => '—',
    'emptyClass' => 'text-muted',
    'containerClass' => 'app-chip-list',
    'listLabel' => null,
    'hiddenCount' => 0,
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
    // Records the viewer cannot open still count toward "+N" and the total, but are never named.
    $moreCount = max($normalizedItems->count() - $visibleItems->count(), 0) + $hiddenCount;
    $totalCount = $normalizedItems->count() + $hiddenCount;
@endphp

@php
    $resolvedListLabel = $listLabel ?? \Illuminate\Support\Str::plural(\Illuminate\Support\Str::headline($kind));
@endphp

{{-- First item as a chip, the rest behind a visible "+N" button that lists every item as a deep link (or plain text when the user cannot open it). --}}
<div class="{{ $containerClass }}">
    @if($visibleItems->isEmpty() && $hiddenCount > 0)
        <x-restricted-count :count="$hiddenCount" :kind="$kind" />
    @elseif($visibleItems->isEmpty())
        <span class="{{ $emptyClass }}">{{ $emptyLabel }}</span>
    @else
        @foreach($visibleItems as $item)
            <x-entity-relation-badge :kind="$kind" :href="$item['href']" :title="$item['title'] ?? $item['label']">
                {{ $item['label'] }}
            </x-entity-relation-badge>
        @endforeach

        @if($moreCount > 0)
            <div class="dropdown app-more">
                <button type="button"
                        class="app-more-btn"
                        data-bs-toggle="dropdown"
                        data-bs-popper-config='{"strategy":"fixed"}'
                        aria-expanded="false"
                        aria-label="Show all {{ $totalCount }} {{ \Illuminate\Support\Str::lower($resolvedListLabel) }}{{ $hiddenCount > 0 ? ', '.$hiddenCount.' restricted' : '' }}">+{{ $moreCount }}</button>
                <div class="dropdown-menu app-more-menu">
                    <div class="app-more-head"><span>{{ $resolvedListLabel }}</span><span>{{ $totalCount }}</span></div>
                    <ul class="app-more-list">
                        @foreach($normalizedItems as $item)
                            <li>
                                @if($item['href'])
                                    <a class="dropdown-item" href="{{ $item['href'] }}">{{ $item['label'] }}</a>
                                @else
                                    <span class="dropdown-item-text">{{ $item['label'] }}</span>
                                @endif
                            </li>
                        @endforeach
                    </ul>
                    @if($hiddenCount > 0)
                        <div class="px-3 py-2 border-top"><x-restricted-count :count="$hiddenCount" :kind="$kind" /></div>
                    @endif
                </div>
            </div>
        @endif
    @endif
</div>
