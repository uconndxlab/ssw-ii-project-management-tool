<tr>
    <td>{{ $deliverable->activityType?->name ?? '—' }}</td>
    <td>{{ $deliverable->contactFamily?->name ?? '—' }}</td>
    <td class="text-center">{{ $deliverable->required_hours !== null ? number_format($deliverable->required_hours, 1) : '—' }}</td>
    <td class="text-center">{{ $deliverable->required_activities ?? '—' }}</td>
    <td>{{ $deliverable->notes ?? '' }}</td>
    <td class="text-end text-nowrap">
        <button type="button"
                class="btn btn-sm btn-outline-secondary"
                hx-get="{{ route('agreements.edit-deliverable', [$agreement, $deliverable]) }}"
                hx-target="#deliverable-edit-modal-body"
                hx-swap="innerHTML">
            Edit
        </button>
        <button type="button"
                class="btn btn-sm btn-outline-danger"
                hx-delete="{{ route('agreements.remove-deliverable', [$agreement, $deliverable]) }}"
                hx-target="#deliverable-list"
                hx-swap="innerHTML"
                hx-confirm="Remove this deliverable?">
            Remove
        </button>
    </td>
</tr>
