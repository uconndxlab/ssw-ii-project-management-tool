@props([
    'activities',
    'viewAllUrl' => null,
    'viewAllLabel' => 'View Activities',
    'logActivityUrl' => null,
    'logActivityEnabled' => true,
    'logActivityDisabledReason' => null,
    'hideAgreements' => false,
    'emptyMessage' => null,
])

@php
    $activities = $activities ?? collect();
    $emptyMessage = $emptyMessage ?? 'No activities logged yet.';
    $viewAllUrl = $viewAllUrl ?? route('activities.index');
    $disabledReasonId = 'log-activity-reason-' . \Illuminate\Support\Str::random(6);
@endphp

<div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
    @if($logActivityUrl)
        <div class="d-flex align-items-center gap-2 flex-wrap">
            @if($logActivityEnabled)
                <a href="{{ $logActivityUrl }}" class="btn btn-sm btn-primary">Log Activity</a>
            @else
                <button type="button" class="btn btn-sm btn-primary" disabled @if($logActivityDisabledReason) aria-describedby="{{ $disabledReasonId }}" @endif>Log Activity</button>
                @if($logActivityDisabledReason)
                    <span id="{{ $disabledReasonId }}" class="text-muted small">{{ $logActivityDisabledReason }}</span>
                @endif
            @endif
        </div>
    @endif

    <a href="{{ $viewAllUrl }}" class="btn btn-sm btn-outline-secondary {{ $logActivityUrl ? '' : 'ms-auto' }}">{{ $viewAllLabel }}</a>
</div>

@if($activities->isNotEmpty())
    <div class="table-responsive app-embedded-table">
        <table class="table table-hover mb-0">
            <thead>
                <tr>
                    <th class="text-nowrap">Date</th>
                    <th>Activity</th>
                    @unless($hideAgreements)
                        <th>Agreements</th>
                    @endunless
                    <th>Logged by</th>
                    <th class="app-col-actions"><span class="visually-hidden">Actions</span></th>
                </tr>
            </thead>
            <tbody>
                @foreach($activities as $activity)
                    @php
                        $canView = $activity->isLinkable();
                        $canManage = auth()->user()->can('update', $activity);
                    @endphp
                    <tr>
                        <td class="text-nowrap">
                            {{ $activity->engagement_date->format('M d, Y') }}
                            @if($activity->cancelled)
                                <div class="mt-1">
                                    <x-status-badge :active="false" inactive-label="Cancelled" tone="danger" />
                                </div>
                            @endif
                        </td>
                        <td>
                            @if($canView)
                                <a href="{{ route('activities.show', $activity) }}" class="app-link--strong app-row-link">{{ $activity->activityType?->name ?? 'Activity' }}</a>
                            @else
                                <span class="fw-semibold">{{ $activity->activityType?->name ?? 'Activity' }}</span>
                            @endif
                            <div class="text-muted small">{{ $activity->activityType?->contactFamily?->name ?? '—' }}</div>
                        </td>
                        @unless($hideAgreements)
                            <td>
                                <x-table-badge-list
                                    kind="agreement"
                                    :items="$activity->agreements->map(fn ($agreement) => [
                                        'name' => $agreement->name,
                                        'href' => $agreement->isLinkable() ? route('agreements.show', $agreement) : null,
                                    ])"
                                    href-key="href"
                                    container-class="app-chip-list app-chip-list--primary"
                                />
                            </td>
                        @endunless
                        <td class="text-nowrap">
                            @if($activity->user)
                                <x-user-link :user="$activity->user" />
                            @else
                                —
                            @endif
                        </td>
                        <td class="app-col-actions">
                            @if($canManage)
                                <div class="dropdown app-row-actions d-inline-block">
                                    <button type="button" class="btn btn-sm btn-link app-row-actions-toggle" data-bs-toggle="dropdown" data-bs-popper-config='{"strategy":"fixed"}' aria-expanded="false" aria-label="Activity actions for {{ $activity->activityType?->name ?? 'activity' }}"><i class="bi bi-three-dots" aria-hidden="true"></i></button>
                                    <div class="dropdown-menu dropdown-menu-end">
                                        @if($canView)
                                            <a href="{{ route('activities.show', $activity) }}" class="dropdown-item"><i class="bi bi-eye me-2" aria-hidden="true"></i>View activity</a>
                                        @endif
                                        <a href="{{ route('activities.edit', $activity) }}" class="dropdown-item"><i class="bi bi-pencil-square me-2" aria-hidden="true"></i>Edit activity</a>
                                    </div>
                                </div>
                            @endif
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
@else
    <p class="text-muted mb-0">{{ $emptyMessage }}</p>
@endif
