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
    'formId' => null,
    'saveLabel' => null,
    'lastSavedAt' => null,
    'sticky' => null,
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

    $resolvedTitleClass = $titleClass ?? ($context === 'dashboard' ? 'app-dashboard-title' : 'app-page-title');
    $resolvedTitleMarginClass = $titleMarginClass ?? 'mb-0';
    $resolvedTitleWeightClass = $titleWeightClass ?? ($context === 'dashboard' ? 'fw-semibold' : 'fw-bold');
    $resolvedDescriptionClass = $descriptionClass ?? 'text-muted small mb-0 mt-1';
    $resolvedMetaClass = $metaClass ?? 'd-flex flex-wrap align-items-center gap-2 text-muted small mt-2';
    $resolvedControlsClass = $controlsClass ?? 'flex-shrink-0 ms-sm-auto align-self-start';
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
        $breadcrumbItems = app(\App\Support\RouteBreadcrumbs::class)
            ->items(request(), $resolvedBreadcrumbLabel ?: $resolvedTitle);
    }

    $hasBadgeAugment = isset($badges) && filled(trim((string) $badges));
    $hasExplicitMeta = isset($meta) && filled(trim((string) $meta));
    $hasExplicitControls = isset($controls) && filled(trim((string) $controls));
    $inlineDescription = filled($description) && in_array($context, ['form', 'show'], true);
    $hasRoleBadge = $badgeKind === 'role' && filled($entityType);
    $hasMeta = $hasExplicitMeta || $hasBadgeAugment || $hasRoleBadge || !is_null($active) || $inlineDescription;
    $hasDefaultAction = filled($actionUrl) && filled($resolvedActionLabel) && filled($resolvedActionClass);
    $hasMore = isset($more) && filled(trim((string) $more));
    $recordIcon = $context === 'show' && filled($entityType)
        ? match ($badgeKind === 'role' ? 'user' : $resolvedEntityKind) {
            'activity' => 'bi-calendar-check',
            'organization' => 'bi-building',
            'state' => 'bi-geo-alt',
            'project' => 'bi-folder',
            'program' => 'bi-diagram-3',
            'team', 'user' => 'bi-people',
            'certificate' => 'bi-award',
            'logging-field' => 'bi-ui-checks',
            default => 'bi-file-earmark-text',
        }
        : null;

    $hasFormActions = $isFormContext && filled($saveLabel);
    $stickyHeader = $sticky ?? $hasFormActions;
    $hasControls = $hasExplicitControls || $hasDefaultAction || $hasFormActions || $hasMore;

    $breadcrumbs = app(\App\Support\RouteBreadcrumbs::class);
    $cancelUrl = $hasFormActions ? $breadcrumbs->cancelUrl(request()) : null;
    $parentCrumb = $stickyHeader ? collect($breadcrumbs->items(request(), null))->last() : null;
    $initialState = $hasFormActions && isset($errors) && $errors->any()
        ? 'error'
        : (filled($lastSavedAt) ? 'saved' : 'idle');
    $savedTime = filled($lastSavedAt) ? \Carbon\Carbon::parse($lastSavedAt)->format('g:i A') : '';
@endphp

{{-- The actions are captured once and rendered in both the full header and the compact sticky bar. --}}
@php ob_start(); @endphp
@if($hasFormActions)
    @if($cancelUrl)
        <a href="{{ $cancelUrl }}" class="btn btn-outline-secondary app-header-btn" data-form-cancel data-tip="Cancel" aria-label="Cancel">
            <i class="bi bi-x-lg" aria-hidden="true"></i><span class="app-header-btn-label">Cancel</span>
        </a>
    @endif
    <button type="button" class="btn btn-primary app-header-btn" data-form-save data-tip="{{ $saveLabel }} (Ctrl/⌘ + S)" aria-label="{{ $saveLabel }}">
        <i class="bi bi-check-lg" aria-hidden="true"></i><span class="app-header-btn-label app-header-btn-label--keep">{{ $saveLabel }}</span>
    </button>
@elseif($hasExplicitControls)
    {{ $controls }}
@elseif($hasDefaultAction)
    <a href="{{ $actionUrl }}" class="{{ $resolvedActionClass }} app-header-btn" data-tip="{{ $resolvedActionLabel }}" aria-label="{{ $resolvedActionLabel }}">
        @if(filled($resolvedActionIconClass))
            <i class="{{ trim(str_replace('me-1', '', $resolvedActionIconClass)) }}" aria-hidden="true"></i>
        @endif
        <span class="app-header-btn-label app-header-btn-label--keep">{{ $resolvedActionLabel }}</span>
    </a>
@endif
@if($hasMore)
    <div class="dropdown">
        <button type="button" class="btn btn-outline-secondary app-header-btn app-header-btn--icon" data-bs-toggle="dropdown" aria-expanded="false" data-tip="More actions" aria-label="More actions">
            <i class="bi bi-three-dots" aria-hidden="true"></i>
        </button>
        <ul class="dropdown-menu dropdown-menu-end">
            {{ $more }}
        </ul>
    </div>
@endif
@php $actionsHtml = new \Illuminate\Support\HtmlString(ob_get_clean()); @endphp
@php $formStatus = new \Illuminate\Support\HtmlString($hasFormActions ? '<span class="app-form-status" data-form-status></span>' : ''); @endphp

@if($stickyHeader)
    <div class="app-header-sticky" data-header-sticky inert aria-hidden="true">
        <div class="app-header-bar">
            @if($parentCrumb && filled($parentCrumb['url'] ?? null))
                <a href="{{ $parentCrumb['url'] }}" class="app-header-back" aria-label="Back to {{ $parentCrumb['label'] }}">
                    <i class="bi bi-arrow-left" aria-hidden="true"></i><span class="app-header-back-label">{{ $parentCrumb['label'] }}</span>
                </a>
                <span class="app-header-divider" aria-hidden="true"></span>
            @endif
            <span class="app-header-bar-title">
                <strong>{{ $resolvedTitle }}</strong>
                @if(filled($resolvedBreadcrumbLabel) && $resolvedBreadcrumbLabel !== $resolvedTitle)
                    <span class="app-header-bar-sub">{{ $resolvedBreadcrumbLabel }}</span>
                @endif
            </span>
            <div class="app-header-actions ms-auto">{{ $formStatus }}{{ $actionsHtml }}</div>
        </div>
    </div>
@endif

<div {{ $attributes->merge(['class' => $resolvedContainerClass]) }}
     data-header-main
     @if($hasFormActions)
         data-form-header
         data-form-id="{{ $formId }}"
         data-initial="{{ $initialState }}"
         data-saved-time="{{ $savedTime }}"
     @endif>
    @if($resolvedShowBreadcrumbs)
        <x-page-breadcrumbs :items="$breadcrumbItems" />
    @endif

    <div class="d-flex justify-content-between align-items-start flex-wrap gap-3">
        @if($recordIcon)
            <span class="app-record-icon" aria-hidden="true"><i class="bi {{ $recordIcon }}"></i></span>
        @endif
        <div class="page-header-heading min-w-0">
            <h1 class="{{ $resolvedTitleClass }} {{ $resolvedTitleMarginClass }} {{ $resolvedTitleWeightClass }}">{{ $resolvedTitle }}</h1>

            @if(filled($description) && ! $inlineDescription)
                <p class="{{ $resolvedDescriptionClass }}">{{ $description }}</p>
            @endif

            @if($hasMeta)
                <div class="{{ $resolvedMetaClass }}">
                    @if($hasRoleBadge)
                        <x-category-badge kind="role">{{ $entityType }}</x-category-badge>
                    @endif
                    @if(! is_null($active))
                        <x-status-badge :active="$active" :inactive-label="$inactiveLabel" />
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
            <div class="{{ $resolvedControlsClass }} app-header-controls">
                <div class="app-header-actions">{{ $actionsHtml }}</div>
                @if($hasFormActions)
                    <div class="app-header-status">{{ $formStatus }}</div>
                @endif
            </div>
        @endif
    </div>
    @isset($facts)
        <dl class="app-record-facts">{{ $facts }}</dl>
    @endisset
</div>
