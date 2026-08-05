@props([
    'activities',
    'variant' => 'user',
    'totalCount' => null,
    'emptyMessage' => null,
])

@php
    $activities = $activities ?? collect();
    $emptyMessage = $emptyMessage ?? match ($variant) {
        'agreement' => 'No activities logged for this agreement yet.',
        'organization' => 'No activities logged yet.',
        'project' => 'No activities logged for this project yet.',
        'program' => 'No activities logged for this program yet.',
        'state' => 'No activities logged for this state yet.',
        default => 'No activities logged yet.',
    };
@endphp

@if($activities->isNotEmpty())
    <div class="table-responsive">
        <table class="table table-sm table-hover mb-0">
            <thead class="table-light">
                <tr>
                    @switch($variant)
                        @case('agreement')
                            <th>Date</th>
                            <th>Contact Family</th>
                            <th>Activity Type</th>
                            <th>Logged By</th>
                            <th class="text-end fw-normal" style="width:52px;">Actions</th>
                            @break
                        @case('organization')
                            <th>Date</th>
                            <th>Agreement</th>
                            <th>Type</th>
                            <th class="text-end">Hours</th>
                            <th>By</th>
                            @break
                        @case('project')
                            <th>Date</th>
                            <th>Program</th>
                            <th>Type</th>
                            <th class="text-end">Hours</th>
                            @break
                        @case('program')
                        @case('state')
                        @case('user')
                        @default
                            <th>Date</th>
                            <th>Type</th>
                            <th>Agreement</th>
                            <th class="text-end">Hours</th>
                            @break
                    @endswitch
                </tr>
            </thead>
            <tbody>
                @foreach($activities as $activity)
                    <tr>
                        @switch($variant)
                            @case('agreement')
                                <td class="small text-nowrap">
                                    {{ $activity->engagement_date->format('M d, Y') }}
                                    @if($activity->cancelled)
                                        <x-status-badge :active="false" inactive-label="Cancelled" class="ms-1" />
                                    @endif
                                </td>
                                <td class="small">{{ $activity->activityType?->contactFamily?->name ?? '—' }}</td>
                                <td class="small">{{ $activity->activityType->name ?? '—' }}</td>
                                <td class="small">
                                    @if($activity->user)
                                        <x-user-link :user="$activity->user" />
                                    @else
                                        —
                                    @endif
                                </td>
                                <td class="text-end text-nowrap">
                                    <a href="{{ route('activities.show', $activity) }}"
                                       class="btn btn-outline-primary btn-sm"
                                       data-bs-toggle="tooltip"
                                       data-bs-title="View activity"
                                       aria-label="View activity">
                                        <i class="bi bi-eye"></i>
                                    </a>
                                </td>
                                @break
                            @case('organization')
                                <td>
                                    <a href="{{ route('activities.show', $activity) }}" class="text-decoration-none text-dark">
                                        {{ $activity->engagement_date->format('M d, Y') }}
                                    </a>
                                    @if($activity->cancelled)
                                        <x-status-badge :active="false" inactive-label="Cancelled" class="ms-1" />
                                    @endif
                                </td>
                                <td>
                                    @foreach($activity->agreements->take(1) as $agr)
                                        @if($agr->isLinkable())
                                            <a href="{{ route('agreements.show', $agr) }}" class="text-decoration-none badge bg-secondary">{{ $agr->name }}</a>
                                        @else
                                            <span class="badge bg-secondary">{{ $agr->name }}</span>
                                        @endif
                                    @endforeach
                                </td>
                                <td class="small">{{ $activity->activityType->name ?? '—' }}</td>
                                <td class="text-end">{{ 0 }}</td>
                                <td class="small text-muted">
                                    @if($activity->user)
                                        <x-user-link :user="$activity->user" />
                                    @else
                                        —
                                    @endif
                                </td>
                                @break
                            @case('project')
                                <td>
                                    <a href="{{ route('activities.show', $activity) }}" class="text-decoration-none text-dark">
                                        {{ $activity->engagement_date->format('M d, Y') }}
                                    </a>
                                    @if($activity->cancelled)
                                        <x-status-badge :active="false" inactive-label="Cancelled" class="ms-1" />
                                    @endif
                                </td>
                                <td>
                                    @foreach($activity->programs->take(2) as $prog)
                                        <x-entity-relation-badge kind="program" :href="route('programs.show', $prog)" class="me-1">{{ $prog->name }}</x-entity-relation-badge>
                                    @endforeach
                                </td>
                                <td>{{ $activity->activityType->name ?? '—' }}</td>
                                <td class="text-end">—</td>
                                @break
                            @case('state')
                                <td>
                                    <a href="{{ route('activities.show', $activity) }}" class="text-decoration-none text-dark">
                                        {{ $activity->engagement_date->format('M d, Y') }}
                                    </a>
                                    @if($activity->cancelled)
                                        <x-status-badge :active="false" inactive-label="Cancelled" class="ms-1" />
                                    @endif
                                </td>
                                <td>{{ $activity->activityType->name ?? '—' }}</td>
                                <td>
                                    @foreach($activity->agreements->take(2) as $agr)
                                        <x-entity-relation-badge kind="agreement" :href="$agr->isLinkable() ? route('agreements.show', $agr) : null" class="me-1">{{ $agr->name }}</x-entity-relation-badge>
                                    @endforeach
                                </td>
                                <td class="text-end">—</td>
                                @break
                            @case('program')
                                <td>
                                    <a href="{{ route('activities.show', $activity) }}" class="text-decoration-none text-dark">
                                        {{ $activity->engagement_date->format('M d, Y') }}
                                    </a>
                                    @if($activity->cancelled)
                                        <x-status-badge :active="false" inactive-label="Cancelled" class="ms-1" />
                                    @endif
                                </td>
                                <td>{{ $activity->activityType->name ?? '—' }}</td>
                                <td>
                                    @foreach($activity->agreements->take(2) as $agr)
                                        <x-entity-relation-badge kind="agreement" :href="$agr->isLinkable() ? route('agreements.show', $agr) : null" class="me-1">{{ $agr->name }}</x-entity-relation-badge>
                                    @endforeach
                                </td>
                                <td class="text-end">—</td>
                                @break
                            @case('user')
                            @default
                                <td>
                                    <a href="{{ route('activities.show', $activity) }}" class="text-decoration-none text-dark">
                                        {{ $activity->engagement_date->format('M d, Y') }}
                                    </a>
                                    @if($activity->cancelled)
                                        <x-status-badge :active="false" inactive-label="Cancelled" class="ms-1" />
                                    @endif
                                </td>
                                <td>{{ $activity->activityType->name ?? '—' }}</td>
                                <td>
                                    @foreach($activity->agreements->take(2) as $agr)
                                        <x-entity-relation-badge kind="agreement" :href="$agr->isLinkable() ? route('agreements.show', $agr) : null" class="me-1">{{ $agr->name }}</x-entity-relation-badge>
                                    @endforeach
                                </td>
                                <td class="text-end">—</td>
                                @break
                        @endswitch
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    @if(! is_null($totalCount) && $totalCount > $activities->count())
        <p class="text-muted small mt-2 mb-0">Showing {{ $activities->count() }} of {{ $totalCount }} activities.</p>
    @endif
@else
    <p class="text-muted mb-0">{{ $emptyMessage }}</p>
@endif
