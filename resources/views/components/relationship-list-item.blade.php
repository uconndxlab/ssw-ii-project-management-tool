@props([
    'href' => null,
    'title',
    'subtitle' => null,
    'kind' => null,
    'titleAsBadge' => false,
    'wrapTitle' => false,
    'showKindBadge' => false,
])

<x-relationship-ledger-row
    :href="$href"
    :title="$title"
    :kind="$kind"
    :description="$subtitle"
    :title-as-badge="$titleAsBadge"
    :wrap-title="$wrapTitle"
    :show-kind-badge="$showKindBadge"
    {{ $attributes }}
/>
