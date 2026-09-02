@props([
    'activity',
    'labeled' => false,
    'linkActivity' => true,
])

@php
    $tooltipAttributes = $labeled
        ? []
        : [
            'data-bs-toggle' => 'tooltip',
            'data-bs-title' => 'View log',
        ];

    $actionLogsUrl = $linkActivity
        ? route('activities.action-logs', $activity)
        : route('activities.action-logs', ['activity' => $activity, 'current' => 1]);
@endphp

@if(auth()->user()->can('viewActionLog', $activity))
    <button
        type="button"
        {{ $attributes->merge($tooltipAttributes)->class(['btn', 'btn-outline-secondary']) }}
        hx-get="{{ $actionLogsUrl }}"
        hx-target="#activity-action-log-modal-body"
        hx-swap="innerHTML"
        onclick="window.showActivityActionLogModal()"
        aria-label="View log"
    >
        <i class="bi bi-journal-text{{ $labeled ? ' me-1' : '' }}"></i>{{ $labeled ? 'View log' : '' }}
    </button>
@endif
