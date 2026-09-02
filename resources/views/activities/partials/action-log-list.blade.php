<span id="activity-action-log-modal-count" class="badge rounded-pill bg-secondary-subtle text-secondary-emphasis border" hx-swap-oob="true">{{ $actionLogs->count() }}</span>

<div id="activity-action-log-modal-subtitle" class="small text-muted text-break" hx-swap-oob="true">
    @if($linkActivity)
        <a href="{{ route('activities.show', $activity) }}" class="text-muted text-decoration-underline">{{ $activity->identityLabel() }}</a>
    @else
        {{ $activity->identityLabel() }}
    @endif
</div>

<x-relationship-scroll-panel title="Actions" :show-header="false" height="360px">
    @forelse($actionLogs as $log)
        <div class="border rounded overflow-hidden bg-body px-3 py-2">
            <div class="d-flex justify-content-between align-items-start gap-3">
                <div class="min-w-0">
                    <div class="fw-semibold small">
                        @if($log->user)
                            <x-user-link :user="$log->user" class="text-body" />
                        @else
                            Deleted user
                        @endif
                    </div>
                    <div class="small text-muted">
                        {{ $log->action->label() }}
                        @if($log->relatedActivityLabel())
                            {{ $log->action->relatedPreposition() }}
                            @if($log->relatedActivityHref())
                                <a href="{{ $log->relatedActivityHref() }}" class="text-decoration-underline">{{ $log->relatedActivityLabel() }}</a>
                            @else
                                {{ $log->relatedActivityLabel() }}
                            @endif
                        @endif
                    </div>
                </div>
                <div class="small text-muted text-nowrap">{{ $log->created_at->format('M j, Y g:i A') }}</div>
            </div>
        </div>
    @empty
        <div class="text-muted small px-1 py-2">No actions recorded yet.</div>
    @endforelse
</x-relationship-scroll-panel>
