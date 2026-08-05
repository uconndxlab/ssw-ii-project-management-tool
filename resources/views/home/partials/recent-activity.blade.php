@if($recentActivities?->count() > 0)
<div class="card shadow-sm mb-4">
    <div class="card-header bg-light">
        <h5 class="mb-0">Recent System Activity</h5>
    </div>
    <div class="card-body p-0">
        <div class="list-group list-group-flush">
            @foreach($recentActivities as $activity)
                @php($canManage = auth()->user()->isAdmin() || $activity->user_id === auth()->id())
                <div class="list-group-item px-3 py-2 d-flex justify-content-between align-items-start gap-3">
                    <div class="flex-grow-1 min-w-0">
                        <div class="d-flex flex-wrap align-items-center gap-2 mb-1">
                            <x-user-link :user="$activity->user" class="text-decoration-none fw-semibold" />
                            <span class="text-muted">logged</span>
                            <a href="{{ route('activities.show', $activity) }}" class="text-decoration-none fw-semibold text-body">{{ $activity->activityType?->name ?? 'Activity' }}</a>
                            @if($activity->cancelled)
                                <x-status-badge :active="false" inactive-label="Cancelled" />
                            @endif
                        </div>
                        <div class="small text-muted mb-2">{{ $activity->activityType?->contactFamily?->name ?? '—' }}</div>
                        <div class="d-flex flex-wrap align-items-center gap-2 small">
                            <span class="text-muted">{{ $activity->engagement_date->format('M d, Y') }}</span>
                            <div class="d-flex flex-wrap gap-1">
                                @forelse($activity->agreements as $agreement)
                                    <x-entity-relation-badge kind="agreement" :href="$agreement->isLinkable() ? route('agreements.show', $agreement) : null">
                                        {{ $agreement->name }}
                                    </x-entity-relation-badge>
                                @empty
                                    <span class="text-muted">—</span>
                                @endforelse
                            </div>
                        </div>
                    </div>
                    <div class="text-end d-flex align-items-start gap-2">
                        <a href="{{ route('activities.show', $activity) }}" class="btn btn-outline-primary btn-sm" aria-label="View activity">
                            <i class="bi bi-eye"></i>
                        </a>
                        @if($canManage)
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
