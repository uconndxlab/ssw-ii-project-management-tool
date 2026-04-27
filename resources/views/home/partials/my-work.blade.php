@if($myActivities?->count() > 0 || $myAgreements?->count() > 0)
<div class="card shadow-sm mb-4">
    <div class="card-header bg-light">
        <h5 class="mb-0">My Work</h5>
    </div>
    <div class="card-body">
        <div class="row g-4">
            @if($myAgreements && $myAgreements->count() > 0)
            <div class="col-md-6">
                <h6 class="mb-3">My Agreements</h6>
                <div class="list-group list-group-sm">
                    @foreach($myAgreements->take(5) as $agreement)
                        <a href="{{ route('agreements.show', $agreement) }}" class="list-group-item list-group-item-action d-flex justify-content-between align-items-center">
                            <div>
                                <div class="fw-bold">{{ $agreement->name }}</div>
                                <small class="text-muted">
                                    @if($agreement->organizations?->isNotEmpty())
                                        {{ $agreement->organizations->pluck('name')->join(', ') }}
                                    @else
                                        No organizations
                                    @endif
                                </small>
                            </div>
                            <span class="badge bg-secondary rounded-pill">{{ $agreement->activities_count ?? 0 }}</span>
                        </a>
                    @endforeach
                </div>
                @if($myAgreements->count() > 5)
                    <div class="mt-2">
                        <a href="{{ route('agreements.index') }}" class="text-decoration-none small">View all agreements</a>
                    </div>
                @endif
            </div>
            @endif

            @if($myActivities && $myActivities->count() > 0)
            <div class="col-md-6">
                <h6 class="mb-3">Recent Activity</h6>
                <div class="list-group list-group-sm">
                    @foreach($myActivities->take(5) as $activity)
                        <a href="{{ route('activities.show', $activity) }}" class="list-group-item list-group-item-action">
                            <div class="d-flex justify-content-between align-items-start">
                                <div class="flex-grow-1">
                                    <div class="fw-bold">{{ $activity->activityType?->name ?? 'Activity' }}</div>
                                    <small class="text-muted">
                                        {{ $activity->engagement_date->format('M d, Y') }}
                                        @if($activity->agreements?->isNotEmpty())
                                            • {{ $activity->agreements->pluck('name')->join(', ') }}
                                        @endif
                                    </small>
                                </div>
                                <span class="badge bg-info">{{ number_format($activity->event_hours ?? 0, 1) }}h</span>
                            </div>
                        </a>
                    @endforeach
                </div>
                <div class="mt-2">
                    <a href="{{ route('activities.index') }}" class="text-decoration-none small">View all activities</a>
                </div>
            </div>
            @endif
        </div>
    </div>
</div>
@endif
