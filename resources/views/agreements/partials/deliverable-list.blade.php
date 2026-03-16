@foreach($agreement->deliverables as $deliverable)
<tr>
    <td>{{ $deliverable->activityType?->name ?? '—' }}</td>
    <td>{{ $deliverable->contactFamily?->name ?? '—' }}</td>
    <td class="text-center">{{ $deliverable->required_hours ? number_format($deliverable->required_hours, 1) : '—' }}</td>
    <td class="text-center">{{ $deliverable->required_activities ?? '—' }}</td>
    <td>{{ $deliverable->notes ?? '' }}</td>
    <td class="text-end">
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
@endforeach
@if($agreement->deliverables->isEmpty())
<tr>
    <td colspan="6" class="text-muted text-center py-3">No deliverables defined yet</td>
</tr>
@endif
