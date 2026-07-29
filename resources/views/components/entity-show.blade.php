{{--
    Shared "Show" layout component for all major entities.

    Props:
        title          - Entity name / heading
        type           - Label for the type badge (e.g. "State", "Agreement")
        typeBadgeClass - Bootstrap bg-* class for the badge (default: bg-secondary)
        editRoute      - URL string for the Edit button (omit to hide)
        backRoute      - URL string for the Back button
        backLabel      - Text for the Back button (default: "Back")

    Named slots:
        $summary       - Core field list (left column)
        $relationships - Related entity cards (right column, top)
        $activity      - Recent activity content (right column, bottom)
--}}
@props([
    'title',
    'type',
    'typeBadgeClass' => 'bg-secondary',
    'editRoute'      => null,
    'backRoute',
    'backLabel'      => 'Back',
    'mainCardTitle'  => 'Relationships',
    'activityFirst'  => false,
    'active'         => null,
])

{{-- ── Page Header ──────────────────────────────────────────────────────── --}}
<div class="mb-4">
    <div class="d-flex justify-content-between align-items-start gap-3 flex-wrap">
        <div class="min-w-0 flex-grow-1">
            <a href="{{ $backRoute }}" class="btn btn-link btn-sm text-muted text-decoration-none px-0 mb-1">
                <i class="bi bi-arrow-left me-1"></i>{{ $backLabel }}
            </a>

            <div class="d-flex flex-wrap align-items-center gap-2 mb-2">
                <x-entity-type-badge :label="$type" :badge-class="$typeBadgeClass" />
                @if(! is_null($active))
                    <x-status-badge :active="$active" />
                @endif
            </div>
            <h1 class="h2 mb-0 fw-bold">{{ $title }}</h1>
        </div>

        @if($editRoute)
            <a href="{{ $editRoute }}" class="btn btn-outline-primary flex-shrink-0">
                <i class="bi bi-pencil-square me-1"></i>Edit
            </a>
        @endif
    </div>
</div>

{{-- ── Three-column card grid ───────────────────────────────────────────── --}}
<div class="row g-4">

    {{-- Summary column (narrow) --}}
    <div class="col-lg-4">
        <div class="card shadow-sm entity-show-card h-100">
            <div class="card-header bg-light py-2 px-3">
                <span class="text-muted fw-semibold small text-uppercase mb-0" style="letter-spacing:.05em;">
                    Summary
                </span>
            </div>
            <div class="card-body">
                {{ $summary }}
            </div>
        </div>
    </div>

    {{-- Relationships + Activity column (wide) --}}
    <div class="col-lg-8 d-flex flex-column gap-4">

        @if($activityFirst)
            {{-- Recent Activity card --}}
            <div class="card shadow-sm entity-show-card">
                <div class="card-header bg-light py-2 px-3">
                    <span class="text-muted fw-semibold small text-uppercase mb-0" style="letter-spacing:.05em;">
                        Recent Activity
                    </span>
                </div>
                <div class="card-body">
                    {{ $activity }}
                </div>
            </div>

            {{-- Main content card --}}
            <div class="card shadow-sm entity-show-card">
                <div class="card-header bg-light py-2 px-3">
                    <span class="text-muted fw-semibold small text-uppercase mb-0" style="letter-spacing:.05em;">
                        {{ $mainCardTitle }}
                    </span>
                </div>
                <div class="card-body">
                    {{ $relationships }}
                </div>
            </div>
        @else
            {{-- Relationships card --}}
            <div class="card shadow-sm entity-show-card">
                <div class="card-header bg-light py-2 px-3">
                    <span class="text-muted fw-semibold small text-uppercase mb-0" style="letter-spacing:.05em;">
                        {{ $mainCardTitle }}
                    </span>
                </div>
                <div class="card-body">
                    {{ $relationships }}
                </div>
            </div>

            {{-- Recent Activity card --}}
            <div class="card shadow-sm entity-show-card">
                <div class="card-header bg-light py-2 px-3">
                    <span class="text-muted fw-semibold small text-uppercase mb-0" style="letter-spacing:.05em;">
                        Recent Activity
                    </span>
                </div>
                <div class="card-body">
                    {{ $activity }}
                </div>
            </div>
        @endif

    </div>
</div>
