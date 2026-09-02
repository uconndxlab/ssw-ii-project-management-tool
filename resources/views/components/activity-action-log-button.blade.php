@props([
    'activity',
    'labeled' => false,
])

@php
    $tooltipAttributes = $labeled
        ? []
        : [
            'data-bs-toggle' => 'tooltip',
            'data-bs-title' => 'View log',
        ];
@endphp

@if(auth()->user()->can('viewActionLog', $activity))
    <button
        type="button"
        {{ $attributes->merge($tooltipAttributes)->class(['btn', 'btn-outline-secondary']) }}
        hx-get="{{ route('activities.action-logs', $activity) }}"
        hx-target="#activity-action-log-modal-body"
        hx-swap="innerHTML"
        onclick="window.showActivityActionLogModal()"
        aria-label="View log"
    >
        <i class="bi bi-journal-text{{ $labeled ? ' me-1' : '' }}"></i>{{ $labeled ? 'View log' : '' }}
    </button>
@endif
