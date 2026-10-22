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

@php
    $resolvedListLabel = $listLabel ?? \Illuminate\Support\Str::plural(\Illuminate\Support\Str::headline($kind));
@endphp

{{-- First item as a chip, the rest behind a visible "+N" button that lists every item as a deep link (or plain text when the user cannot open it). --}}
<div class="{{ $containerClass }}">
    @if($visibleItems->isEmpty())
        <span class="{{ $emptyClass }}">{{ $emptyLabel }}</span>
    @else
        @foreach($visibleItems as $item)
            <x-entity-relation-badge :kind="$kind" :href="$item['href']" :title="$item['title'] ?? $item['label']">
                {{ $item['label'] }}
            </x-entity-relation-badge>
        @endforeach

        @if($hiddenCount > 0)
            <div class="dropdown app-more">
                <button type="button"
                        class="app-more-btn"
                        data-bs-toggle="dropdown"
                        data-bs-popper-config='{"strategy":"fixed"}'
                        aria-expanded="false"
                        aria-label="Show all {{ $normalizedItems->count() }} {{ \Illuminate\Support\Str::lower($resolvedListLabel) }}">+{{ $hiddenCount }}</button>
                <div class="dropdown-menu app-more-menu">
                    <div class="app-more-head"><span>{{ $resolvedListLabel }}</span><span>{{ $normalizedItems->count() }}</span></div>
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
                </div>
            </div>
        @endif
    @endif
</div>
