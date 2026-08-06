@props([
    'activities',
    'viewAllUrl' => null,
    'viewAllLabel' => 'View Activities',
    'logActivityUrl' => null,
    'logActivityEnabled' => true,
    'emptyMessage' => null,
])

@php
    $activities = $activities ?? collect();
    $emptyMessage = $emptyMessage ?? 'No activities logged yet.';
    $viewAllUrl = $viewAllUrl ?? route('activities.index');
@endphp

@once
    <style>
        .recent-activity-scroll-wrap {
            border: 1px solid var(--bs-border-color);
            border-radius: var(--bs-card-border-radius);
            overflow: hidden;
        }

        .recent-activity-scroll-area {
            overflow-x: auto;
        }

        .recent-activity-scroll-area::-webkit-scrollbar {
            height: 13px;
        }

        .recent-activity-scroll-area::-webkit-scrollbar-track {
            background: #e9edf2;
            border-radius: 999px;
        }

        .recent-activity-scroll-area::-webkit-scrollbar-thumb {
            background: #8d98a5;
            border-radius: 999px;
            border: 2px solid #e9edf2;
        }

        .recent-activity-scroll-area .table {
            margin-bottom: 0;
        }

        .recent-activity-scroll-area .table th:first-child,
        .recent-activity-scroll-area .table td:first-child {
            padding-left: 0.75rem;
        }

        .recent-activity-scroll-area .table th:last-child,
        .recent-activity-scroll-area .table td:last-child {
            padding-right: 0.75rem;
        }
    </style>
@endonce

<div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
    @if($logActivityUrl)
        @if($logActivityEnabled)
            <a href="{{ $logActivityUrl }}" class="btn btn-sm btn-success">Log Activity</a>
        @else
            <span class="btn btn-sm btn-success disabled" aria-disabled="true">Log Activity</span>
        @endif
    @endif

    <a href="{{ $viewAllUrl }}" class="btn btn-sm btn-outline-secondary {{ $logActivityUrl ? '' : 'ms-auto' }}">{{ $viewAllLabel }}</a>
</div>

@if($activities->isNotEmpty())
    <div class="recent-activity-scroll-wrap">
    <div class="table-responsive recent-activity-scroll-area">
        <table class="table table-sm table-hover" style="min-width: 860px;">
            <thead class="table-light">
                <tr>
                    <th class="text-nowrap">Date</th>
                    <th class="text-nowrap" style="min-width: 280px;">Activity</th>
                    <th style="min-width: 220px;">Agreements</th>
                    <th style="min-width: 140px;">Logged By</th>
                    <th class="text-end text-nowrap" style="width:96px;">Actions</th>
                </tr>
            </thead>
            <tbody>
                @foreach($activities as $activity)
                    @php($canManage = auth()->user()->isAdmin() || $activity->user_id === auth()->id())
                    <tr>
                        <td class="small">
                            <a href="{{ route('activities.show', $activity) }}" class="text-decoration-none text-dark d-block text-nowrap">
                                {{ $activity->engagement_date->format('M d, Y') }}
                            </a>
                            @if($activity->cancelled)
                                <div class="mt-1">
                                    <x-status-badge :active="false" inactive-label="Cancelled" />
                                </div>
                            @endif
                        </td>
                        <td>
                            <div class="text-nowrap">
                                <a href="{{ route('activities.show', $activity) }}" class="fw-semibold text-decoration-none text-dark">
                                    {{ $activity->activityType?->name ?? 'Activity' }}
                                </a>
                            </div>
                            <div class="text-muted small">
                                {{ $activity->activityType?->contactFamily?->name ?? '—' }}
                            </div>
                        </td>
                        <td>
                            <x-table-badge-list
                                kind="agreement"
                                :items="$activity->agreements->map(fn ($agreement) => [
                                    'name' => $agreement->name,
                                    'href' => $agreement->isLinkable() ? route('agreements.show', $agreement) : null,
                                ])"
                                href-key="href"
                            />
                        </td>
                        <td class="small">
                            @if($activity->user)
                                <x-user-link :user="$activity->user" />
                            @else
                                —
                            @endif
                        </td>
                        <td class="text-end text-nowrap">
                            <div class="btn-group btn-group-sm" role="group" aria-label="Recent activity actions">
                                <a href="{{ route('activities.show', $activity) }}"
                                   class="btn btn-outline-primary"
                                   data-bs-toggle="tooltip"
                                   data-bs-title="View activity"
                                   aria-label="View activity">
                                    <i class="bi bi-eye"></i>
                                </a>
                                @if($canManage)
                                    <a href="{{ route('activities.edit', $activity) }}"
                                       class="btn btn-outline-secondary"
                                       data-bs-toggle="tooltip"
                                       data-bs-title="Edit activity"
                                       aria-label="Edit activity">
                                        <i class="bi bi-pencil-square"></i>
                                    </a>
                                @endif
                            </div>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    </div>
@else
    <p class="text-muted mb-0">{{ $emptyMessage }}</p>
@endif
