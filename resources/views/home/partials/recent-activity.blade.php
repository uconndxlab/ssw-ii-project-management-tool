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
                            <strong>{{ $activity->user->name }}</strong>
                            <span class="text-muted">logged</span>
                            <strong>{{ $activity->activityType?->name ?? 'Activity' }}</strong>
                        </div>
                        <small class="text-muted">
                            {{ $activity->engagement_date->format('M d, Y') }}
                            @if($activity->agreements?->isNotEmpty())
                                • {{ $activity->agreements->pluck('name')->join(', ') }}
                            @endif
                        </small>
                    </div>
                    <div class="text-end ms-2">
                        <span class="badge bg-secondary">{{ number_format($activity->event_hours ?? 0, 1) }}h</span>
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
