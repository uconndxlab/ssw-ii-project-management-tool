{{--
    Shared show-page body layout component.

    Props:
        mainCardTitle - Title for the primary right-side card
        activityFirst - Whether recent activity appears before the main card

    Named slots:
        $summary       - Core field list (left column)
        $relationships - Related entity cards (right column, top)
        $activity      - Recent activity content (right column, bottom)
--}}
@props([
    'mainCardTitle'  => 'Relationships',
    'activityFirst'  => false,
])

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

    <div class="col-lg-8 d-flex flex-column gap-4">
        @if($activityFirst)
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
