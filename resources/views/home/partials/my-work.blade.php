@if($myActivities?->count() > 0 || $myAgreements?->count() > 0 || $myAssignedDeliverables?->count() > 0)
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
                                <span class="badge bg-info">—</span>
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

        {{-- My Assigned Deliverables --}}
        @if($myAssignedDeliverables && $myAssignedDeliverables->count() > 0)
        <hr>
        <h6 class="mb-3">My Assigned Deliverables</h6>
        <div class="table-responsive">
            <table class="table table-sm table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Deliverable</th>
                        <th>Agreement</th>
                        <th>Organization</th>
                        <th class="text-center">Metric</th>
                        <th class="text-center">Target</th>
                        <th>Notes</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($myAssignedDeliverables as $deliverable)
                    <tr>
                        <td>
                            <div class="fw-semibold">{{ $deliverable->activityType?->name ?? '—' }}</div>
                            @if($deliverable->contactFamily)
                                <small class="text-muted">{{ $deliverable->contactFamily->name }}</small>
                            @endif
                        </td>
                        <td>
                            @if($deliverable->agreement)
                                <a href="{{ route('agreements.show', $deliverable->agreement) }}" class="text-decoration-none">
                                    {{ $deliverable->agreement->name }}
                                </a>
                            @else
                                <span class="text-muted">—</span>
                            @endif
                        </td>
                        <td class="small text-muted">
                            @if($deliverable->agreement?->organizations?->isNotEmpty())
                                {{ $deliverable->agreement->organizations->pluck('name')->join(', ') }}
                            @else
                                —
                            @endif
                        </td>
                        <td class="text-center">
                            {{ $deliverable->metric_type ? ucfirst($deliverable->metric_type) : '—' }}
                        </td>
                        <td class="text-center">
                            {{ $deliverable->target_quantity !== null ? number_format((float) $deliverable->target_quantity, 1) : '—' }}
                        </td>
                        <td class="small text-muted">{{ $deliverable->notes ?? '' }}</td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        @endif
    </div>
</div>
@endif
