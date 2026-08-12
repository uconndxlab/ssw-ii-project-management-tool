@props([
    'title',
    'href' => null,
    'kind' => null,
    'titleAsBadge' => false,
    'wrapTitle' => false,
    'showKindBadge' => false,
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
    $hasDetails = filled($description) || $metaBadges->isNotEmpty() || $metaLines->isNotEmpty() || $footerFilled;
    $resolvedKindLabel = filled($kind)
        ? \Illuminate\Support\Str::of($kind)->replace('-', ' ')->title()->value()
        : null;
@endphp

<div {{ $attributes->merge(['class' => 'border rounded overflow-hidden bg-body px-3 py-2']) }}>
    <div class="d-flex justify-content-between align-items-start gap-3">
        <div class="min-w-0 flex-grow-1">
            <div class="d-flex flex-wrap align-items-center gap-2 min-w-0">
                @if($titleAsBadge && filled($kind))
                    <x-entity-relation-badge :kind="$kind" :href="$href" :wrap="$wrapTitle">
                        {{ $title }}
                    </x-entity-relation-badge>
                @elseif($href)
                    <a href="{{ $href }}" class="fw-semibold small text-decoration-underline d-block text-break">{{ $title }}</a>
                @else
                    <span class="fw-semibold small d-block text-body text-break">{{ $title }}</span>
                @endif

                @if($showKindBadge && filled($resolvedKindLabel) && ! $titleAsBadge)
                    <x-entity-type-badge :label="$resolvedKindLabel" :badge-class="\App\Support\EntityBadge::typeClasses($kind)" />
                @endif
            </div>

            @if($contextBadges->isNotEmpty())
                <div class="d-flex flex-wrap gap-1 mt-2">
                    @foreach($contextBadges as $badge)
                        @if(filled($badge['class']))
                            <span class="badge {{ $badge['class'] }}">{{ $badge['label'] }}</span>
                        @else
                            <x-category-badge :kind="$badge['kind']">{{ $badge['label'] }}</x-category-badge>
                        @endif
                    @endforeach
                </div>
            @endif

            @if($hasDetails)
                <hr class="my-2">

                @if(filled($description))
                    <div class="small text-muted">{{ $description }}</div>
                @endif

                @if($metaBadges->isNotEmpty())
                    <div class="d-flex flex-wrap gap-1 {{ filled($description) ? 'mt-2' : '' }}">
                        @foreach($metaBadges as $badge)
                            @if(filled($badge['class']))
                                <span class="badge {{ $badge['class'] }}">{{ $badge['label'] }}</span>
                            @else
                                <x-category-badge :kind="$badge['kind']">{{ $badge['label'] }}</x-category-badge>
                            @endif
                        @endforeach
                    </div>
                @endif

                @if($metaLines->isNotEmpty())
                    <ul class="small text-muted mb-0 ps-3 {{ filled($description) || $metaBadges->isNotEmpty() ? 'mt-2' : '' }}">
                        @foreach($metaLines as $line)
                            <li>
                                @if(filled($line['label'] ?? null))
                                    <span class="fw-semibold text-body">{{ $line['label'] }}:</span>
                                @endif
                                @if(filled($line['value'] ?? null))
                                    <span class="fw-normal text-muted">{{ $line['value'] }}</span>
                                @endif
                            </li>
                        @endforeach
                    </ul>
                @endif

                @if($footerFilled)
                    <div class="mt-2">{{ $footer }}</div>
                @endif
            @endif
        </div>

        @if($actionsFilled)
            <div class="flex-shrink-0">{{ $actions }}</div>
        @endif
    </div>
</div>
