@props([
    'title',
    'href' => null,
    'kind' => null,
    'titleAsBadge' => false,
    'wrapTitle' => false,
    'showKindBadge' => false,
    'subtitle' => null,
    'description' => null,
    'contextBadges' => [],
    'metaBadges' => [],
    'metaLines' => [],
])

@php
    $normalizeBadge = function (mixed $badge): ?array {
        if (is_string($badge)) {
            return ['label' => $badge, 'kind' => 'default', 'class' => null];
        }

        if (!is_array($badge)) {
            return null;
        }

        return [
            'label' => $badge['label'] ?? null,
            'kind' => $badge['kind'] ?? 'default',
            'class' => $badge['class'] ?? null,
        ];
    };

    $normalizeMetaLine = function (mixed $line): ?array {
        if (is_string($line)) {
            return ['label' => null, 'value' => $line];
        }

        if (!is_array($line)) {
            return null;
        }

        $label = filled($line['label'] ?? null) ? (string) $line['label'] : null;
        $value = filled($line['value'] ?? null) ? (string) $line['value'] : null;

        if (!filled($label) && !filled($value)) {
            return null;
        }

        return [
            'label' => $label,
            'value' => $value,
        ];
    };

    $contextBadges = collect($contextBadges)
        ->map($normalizeBadge)
        ->filter(fn ($badge) => filled($badge['label'] ?? null))
        ->values();

    $metaBadges = collect($metaBadges)
        ->map($normalizeBadge)
        ->filter(fn ($badge) => filled($badge['label'] ?? null))
        ->values();

    $metaLines = collect($metaLines)
        ->map($normalizeMetaLine)
        ->filter(fn ($line) => filled($line['label'] ?? null) || filled($line['value'] ?? null))
        ->values();

    $actionsFilled = isset($actions) && filled(trim((string) $actions));
    $footerFilled = isset($footer) && filled(trim((string) $footer));
    $resolvedKindLabel = filled($kind)
        ? \Illuminate\Support\Str::of($kind)->replace('-', ' ')->title()->value()
        : null;
    $badges = $contextBadges->concat($metaBadges);
    $hasMeta = filled($subtitle) || $metaLines->isNotEmpty() || $footerFilled;
@endphp

<article data-ledger-item data-ledger-title="{{ $title }}" {{ $attributes->class(['app-ledger-row', 'app-ledger-row--simple' => ! $hasMeta && ! filled($description) && $badges->isEmpty() && ! $actionsFilled]) }}>
    <div class="app-ledger-row-head">
        <div class="app-ledger-row-identity">
            @if($href)
                <a href="{{ $href }}" class="app-ledger-name">{{ $title }}</a>
            @else
                <span class="app-ledger-name">{{ $title }}</span>
            @endif
            @if($showKindBadge && filled($resolvedKindLabel) && ! $titleAsBadge)
                <x-entity-type-badge :label="$resolvedKindLabel" :badge-class="\App\Support\EntityBadge::typeClasses($kind)" />
            @endif
        </div>
        @if($badges->isNotEmpty() || $actionsFilled)
            <div class="app-ledger-row-roles">
                @foreach($badges as $badge)
                    @if(filled($badge['class']))
                        <span class="badge {{ $badge['class'] }}">{{ $badge['label'] }}</span>
                    @else
                        <x-category-badge :kind="$badge['kind']">{{ $badge['label'] }}</x-category-badge>
                    @endif
                @endforeach
                @if($actionsFilled)
                    {{ $actions }}
                @endif
            </div>
        @endif
    </div>
    @if($hasMeta)
        <div class="app-ledger-meta">
            @if(filled($subtitle))
                <span>{{ $subtitle }}</span>
            @endif
            @foreach($metaLines as $line)
                <span>
                    @if(filled($line['label'] ?? null))
                        <span class="fw-medium text-body">{{ $line['label'] }}</span>
                    @endif
                    {{ $line['value'] }}
                </span>
            @endforeach
            @if($footerFilled)
                {{ $footer }}
            @endif
        </div>
    @endif
    @if(filled($description))
        <p class="app-ledger-description">{{ $description }}</p>
    @endif
</article>
