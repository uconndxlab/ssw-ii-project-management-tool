@props([
    'context' => 'default',
    'title' => null,
    'breadcrumbLabel' => null,
    'description' => null,
    'descriptionClass' => null,
    'showBreadcrumbs' => null,
    'titleClass' => null,
    'titleMarginClass' => null,
    'titleWeightClass' => null,
    'metaClass' => null,
    'controlsClass' => null,
    'containerClass' => null,
    'entityType' => null,
    'entityKind' => null,
    'entityTypeBadgeClass' => null,
    'badgeKind' => null,
    'active' => null,
    'inactiveLabel' => 'Inactive',
    'mode' => 'create',
    'actionUrl' => null,
    'actionKind' => null,
    'actionLabel' => null,
    'actionEntityLabel' => null,
    'actionClass' => null,
    'actionIconClass' => null,
])

@php
    $isFormContext = $context === 'form';
    $isProfileRoute = request()->routeIs('profile', 'profile.*');
    $resolvedEntityKind = $entityKind;

    if (blank($resolvedEntityKind) && filled($entityType)) {
        $resolvedEntityKind = \Illuminate\Support\Str::of($entityType)
            ->lower()
            ->replace(' ', '-')
            ->value();
    }

    $resolvedTitle = $title;

    if ($isFormContext && blank($resolvedTitle)) {
        $resolvedTitle = $mode === 'edit'
            ? 'Edit ' . $entityType
            : ($resolvedEntityKind === 'activity' ? 'Log Activity' : 'Create ' . $entityType);
    }

    $resolvedEntityTypeBadgeClass = $entityTypeBadgeClass;

    if (blank($resolvedEntityTypeBadgeClass) && filled($resolvedEntityKind)) {
        $resolvedEntityTypeBadgeClass = \App\Support\EntityBadge::typeClasses($resolvedEntityKind);
    }

    $resolvedBreadcrumbLabel = $breadcrumbLabel;

    if (blank($resolvedBreadcrumbLabel)) {
        $resolvedBreadcrumbLabel = $isFormContext
            ? (filled($entityType) && $mode === 'edit' ? 'Edit ' . $entityType : ($resolvedTitle ?: ($resolvedEntityKind === 'activity' ? 'Log Activity' : 'Create ' . $entityType)))
            : $resolvedTitle;
    }

    $resolvedShowBreadcrumbs = $showBreadcrumbs;

    if ($resolvedShowBreadcrumbs === null) {
        $resolvedShowBreadcrumbs = ! in_array($context, ['index', 'dashboard'], true);
    }

    if ($context === 'show' && $isProfileRoute) {
        $resolvedShowBreadcrumbs = false;
    }

    $resolvedContainerClass = $containerClass ?? match ($context) {
        'form', 'show', 'dashboard' => 'mb-4',
        default => 'mb-3',
    };

    $resolvedTitleClass = $titleClass ?? ($context === 'dashboard' ? 'display-6' : 'h2');
    $resolvedTitleMarginClass = $titleMarginClass ?? 'mb-0';
    $resolvedTitleWeightClass = $titleWeightClass ?? ($context === 'dashboard' ? 'fw-semibold' : 'fw-bold');
    $resolvedDescriptionClass = $descriptionClass ?? 'text-muted small mb-0 mt-1';
    $resolvedMetaClass = $metaClass ?? 'd-flex flex-wrap align-items-center gap-2 text-muted small mt-2';
    $resolvedControlsClass = $controlsClass ?? 'flex-shrink-0 ms-sm-auto align-self-end';
    $resolvedActionKind = $actionKind;

    if (blank($resolvedActionKind) && filled($actionUrl)) {
        $resolvedActionKind = match ($context) {
            'show' => 'edit',
            'index' => 'create',
            default => null,
        };
    }

    $resolvedActionEntityLabel = $actionEntityLabel
        ?: ($entityType ?: \Illuminate\Support\Str::singular((string) $resolvedTitle));

    $resolvedActionLabel = $actionLabel;

    if (blank($resolvedActionLabel) && filled($resolvedActionKind)) {
        $resolvedActionLabel = match ($resolvedActionKind) {
            'create' => $resolvedEntityKind === 'activity'
                ? 'Log Activity'
                : 'Create ' . $resolvedActionEntityLabel,
            'edit' => 'Edit',
            default => null,
        };
    }

    $resolvedActionClass = $actionClass;

    if (blank($resolvedActionClass) && filled($resolvedActionKind)) {
        $resolvedActionClass = match ($resolvedActionKind) {
            'edit' => 'btn btn-outline-primary',
            default => 'btn btn-primary',
        };
    }

    $resolvedActionIconClass = $actionIconClass;

    if (blank($resolvedActionIconClass)) {
        $resolvedActionIconClass = match ($resolvedActionKind) {
            'edit' => 'bi bi-pencil-square me-1',
            'create' => 'bi bi-plus-lg me-1',
            default => null,
        };
    }

    $breadcrumbItems = [];

    if ($resolvedShowBreadcrumbs) {
        $breadcrumbItems = app(\App\Services\SessionBackTargetService::class)
            ->breadcrumbs(request(), $resolvedBreadcrumbLabel ?: $resolvedTitle);
    }

    $hasBadgeAugment = isset($badges) && filled(trim((string) $badges));
    $hasExplicitMeta = isset($meta) && filled(trim((string) $meta));
    $hasExplicitControls = isset($controls) && filled(trim((string) $controls));
    $inlineDescription = filled($description) && in_array($context, ['form', 'show'], true);
    $showFormMeta = $isFormContext && filled($entityType);
    $showStandardMeta = $showFormMeta || filled($entityType) || !is_null($active) || $inlineDescription;
    $hasMeta = $hasExplicitMeta || $hasBadgeAugment || $showStandardMeta;
    $hasDefaultAction = filled($actionUrl) && filled($resolvedActionLabel) && filled($resolvedActionClass);
    $hasControls = $hasExplicitControls || $hasDefaultAction;
@endphp

<div {{ $attributes->merge(['class' => $resolvedContainerClass]) }}>
    @if($resolvedShowBreadcrumbs)
        <x-page-breadcrumbs :items="$breadcrumbItems" />
    @endif

    <div class="d-flex justify-content-between align-items-end flex-wrap gap-3">
        <div class="flex-grow-1 min-w-0">
            <h1 class="{{ $resolvedTitleClass }} {{ $resolvedTitleMarginClass }} {{ $resolvedTitleWeightClass }}">{{ $resolvedTitle }}</h1>

            @if(filled($description) && ! $inlineDescription)
                <p class="{{ $resolvedDescriptionClass }}">{{ $description }}</p>
            @endif

            @if($hasMeta)
                <div class="{{ $resolvedMetaClass }}">
                    @if($showStandardMeta)
                        @if(filled($entityType))
                            @if(filled($badgeKind))
                                <x-category-badge :kind="$badgeKind">{{ $entityType }}</x-category-badge>
                            @else
                                <x-entity-type-badge :label="$entityType" :badge-class="$resolvedEntityTypeBadgeClass" />
                            @endif
                        @endif
                        @if(! is_null($active))
                            <x-status-badge :active="$active" :inactive-label="$inactiveLabel" />
                        @endif
                    @endif
                    @if($hasBadgeAugment)
                        {{ $badges }}
                    @endif
                    @if($hasExplicitMeta)
                        {{ $meta }}
                    @endif
                    @if($inlineDescription)
                        <span>{{ $description }}</span>
                    @endif
                </div>
            @endif
        </div>

        @if($hasControls)
            <div class="{{ $resolvedControlsClass }}">
                @if($hasExplicitControls)
                    {{ $controls }}
                @elseif($hasDefaultAction)
                    <a href="{{ $actionUrl }}" class="{{ $resolvedActionClass }}">
                        @if(filled($resolvedActionIconClass))
                            <i class="{{ $resolvedActionIconClass }}"></i>
                        @endif
                        {{ $resolvedActionLabel }}
                    </a>
                @endif
            </div>
        @endif
    </div>
</div>
