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
])

{{-- ── Page Header ──────────────────────────────────────────────────────── --}}
<div class="d-flex justify-content-between align-items-start mb-4 flex-wrap gap-3">
    <div>
        <span class="badge {{ $typeBadgeClass }} text-uppercase mb-2" style="font-size:.7rem;letter-spacing:.05em;">
            {{ $type }}
        </span>
        <h1 class="h2 mb-0 fw-bold">{{ $title }}</h1>
    </div>
    <div class="d-flex gap-2 flex-wrap">
        @if($editRoute)
            <a href="{{ $editRoute }}" class="btn btn-outline-primary">
                ✏️ Edit
            </a>
        @endif
        <a href="{{ $backRoute }}" class="btn btn-outline-secondary">
            ← {{ $backLabel }}
        </a>
    </div>
</div>

{{-- ── Three-column card grid ───────────────────────────────────────────── --}}
<div class="row g-4">

    {{-- Summary column (narrow) --}}
    <div class="col-lg-4">
        <div class="card shadow-sm h-100">
            <div class="card-header bg-light py-2">
                <span class="text-muted fw-semibold small text-uppercase" style="letter-spacing:.05em;">
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
            <div class="card shadow-sm">
                <div class="card-header bg-light py-2">
                    <span class="text-muted fw-semibold small text-uppercase" style="letter-spacing:.05em;">
                        Recent Activity
                    </span>
                </div>
                <div class="card-body">
                    {{ $activity }}
                </div>
            </div>

            {{-- Main content card --}}
            <div class="card shadow-sm">
                <div class="card-header bg-light py-2">
                    <span class="text-muted fw-semibold small text-uppercase" style="letter-spacing:.05em;">
                        {{ $mainCardTitle }}
                    </span>
                </div>
                <div class="card-body">
                    {{ $relationships }}
                </div>
            </div>
        @else
            {{-- Relationships card --}}
            <div class="card shadow-sm">
                <div class="card-header bg-light py-2">
                    <span class="text-muted fw-semibold small text-uppercase" style="letter-spacing:.05em;">
                        {{ $mainCardTitle }}
                    </span>
                </div>
                <div class="card-body">
                    {{ $relationships }}
                </div>
            </div>

            {{-- Recent Activity card --}}
            <div class="card shadow-sm">
                <div class="card-header bg-light py-2">
                    <span class="text-muted fw-semibold small text-uppercase" style="letter-spacing:.05em;">
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
