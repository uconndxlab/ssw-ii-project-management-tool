@props([
    'activity',
    'labeled' => false,
    'menu' => false,
    'linkActivity' => true,
])

@php
    $tooltipAttributes = ($labeled || $menu)
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
        {{ $attributes->merge($tooltipAttributes)->class($menu ? ['dropdown-item'] : ['btn', 'btn-outline-secondary']) }}
        hx-get="{{ $actionLogsUrl }}"
        hx-target="#activity-action-log-modal-body"
        hx-swap="innerHTML"
        onclick="window.showActivityActionLogModal()"
        aria-label="View log"
    >
        <i class="bi bi-journal-text{{ ($labeled || $menu) ? ' me-2' : '' }}" aria-hidden="true"></i>{{ ($labeled || $menu) ? 'View log' : '' }}
    </button>
@endif
