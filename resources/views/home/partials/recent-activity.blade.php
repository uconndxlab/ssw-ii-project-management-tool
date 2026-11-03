@if($recentActivities?->count() > 0)
<div class="card shadow-sm mb-4">
    <div class="card-header">
        <h5 class="mb-0">Recent System Activity</h5>
    </div>
    <div class="card-body p-0">
        <div class="list-group list-group-flush">
            @foreach($recentActivities as $activity)
                @php $canManage = auth()->user()->can('update', $activity); @endphp
                <div class="list-group-item px-3 py-2 d-flex justify-content-between align-items-start gap-2">
                    <div class="flex-grow-1 min-w-0">
                        <div class="d-flex flex-wrap align-items-center gap-2 mb-1">
                            <x-user-link :user="$activity->user" class="text-decoration-none fw-semibold" />
                            <span class="text-muted">logged</span>
                            <a href="{{ route('activities.show', $activity) }}" class="app-link app-link--strong app-row-link">{{ $activity->activityType?->name ?? 'Activity' }}</a>
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
                    @if($canManage)
                        <div class="dropdown app-row-actions flex-shrink-0">
                            <button type="button" class="btn btn-sm btn-link app-row-actions-toggle" data-bs-toggle="dropdown" data-bs-popper-config='{"strategy":"fixed"}' aria-expanded="false" aria-label="Activity actions for {{ $activity->activityType?->name ?? 'activity' }}"><i class="bi bi-three-dots" aria-hidden="true"></i></button>
                            <div class="dropdown-menu dropdown-menu-end">
                                <a href="{{ route('activities.show', $activity) }}" class="dropdown-item"><i class="bi bi-eye me-2" aria-hidden="true"></i>View activity</a>
                                <a href="{{ route('activities.edit', $activity) }}" class="dropdown-item"><i class="bi bi-pencil-square me-2" aria-hidden="true"></i>Edit activity</a>
                            </div>
                        </div>
                    @endif
                </div>
            @endforeach
        </div>
    </div>
    <div class="card-footer bg-light text-center py-2">
        <a href="{{ route('activities.index') }}" class="text-decoration-none small">View all activity</a>
    </div>
</div>
@endif
