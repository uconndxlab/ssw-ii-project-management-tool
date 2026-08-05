@if($recentActivities?->count() > 0)
<div class="card shadow-sm mb-4">
    <div class="card-header bg-light">
        <h5 class="mb-0">Recent System Activity</h5>
    </div>
    <div class="card-body p-0">
        <div class="list-group list-group-flush">
            @foreach($recentActivities as $activity)
                <div class="list-group-item px-3 py-2 d-flex justify-content-between align-items-start">
                    <div class="flex-grow-1">
                        <div class="mb-1">
                            <x-user-link :user="$activity->user" class="text-decoration-none fw-semibold" />
                            <span class="text-muted">logged</span>
                            <a href="{{ route('activities.show', $activity) }}" class="text-decoration-none fw-semibold">{{ $activity->activityType?->name ?? 'Activity' }}</a>
                            @if($activity->cancelled)
                                <x-status-badge :active="false" inactive-label="Cancelled" class="ms-1" />
                            @endif
                        </div>
                        <small class="text-muted">
                            {{ $activity->engagement_date->format('M d, Y') }}
                            @if($activity->agreements?->isNotEmpty())
                                • 
                                @foreach($activity->agreements as $agreement)
                                    <x-agreement-link :agreement="$agreement" class="text-decoration-none" />@if(!$loop->last), @endif
                                @endforeach
                            @endif
                        </small>
                    </div>
                    <div class="text-end ms-2 d-flex align-items-start gap-2">
                        <a href="{{ route('activities.show', $activity) }}" class="btn btn-outline-primary btn-sm" aria-label="View activity">
                            <i class="bi bi-eye"></i>
                        </a>
                        @if(auth()->user()->isAdmin() || $activity->user_id === auth()->id())
                            <a href="{{ route('activities.edit', $activity) }}" class="btn btn-outline-secondary btn-sm" aria-label="Edit activity">
                                <i class="bi bi-pencil-square"></i>
                            </a>
                        @endif
                    </div>
                </div>
            @endforeach
        </div>
    </div>
    <div class="card-footer bg-light text-center py-2">
        <a href="{{ route('activities.index') }}" class="text-decoration-none small">View all activity</a>
    </div>
</div>
@endif
