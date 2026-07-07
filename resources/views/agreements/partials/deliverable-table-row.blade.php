@php
    $rowKey = $row['row_key'];
    $assignedUserNames = collect($row['assigned_user_names'] ?? []);
@endphp

<tr data-deliverable-row data-row-key="{{ $rowKey }}" data-deliverable-row-data='@json($row)'>
    <td>
        <div class="fw-semibold">{{ $row['contact_family_label'] ?: '—' }}</div>
        <div class="text-muted small">{{ $row['activity_type_label'] ?: 'Any activity type' }}</div>
    </td>
    <td class="text-wrap" style="min-width: 320px; max-width: 100%; white-space: normal;">{{ $row['notes'] ?: '—' }}</td>
    <td>
        @forelse($assignedUserNames as $name)
            <span class="badge bg-secondary me-1 mb-1">{{ $name }}</span>
        @empty
            <span class="text-muted small">—</span>
        @endforelse
    </td>
    <td class="text-end text-nowrap">
        <div class="btn-group btn-group-sm" role="group" aria-label="Deliverable actions">
            <button type="button" class="btn btn-outline-secondary" data-deliverable-edit data-bs-toggle="tooltip" data-bs-title="Edit deliverable" aria-label="Edit deliverable">
                <i class="bi bi-pencil-square"></i>
            </button>
            <button type="button" class="btn btn-outline-secondary" data-deliverable-duplicate data-bs-toggle="tooltip" data-bs-title="Duplicate deliverable" aria-label="Duplicate deliverable">
                <i class="bi bi-files"></i>
            </button>
            <button type="button" class="btn btn-outline-danger" data-deliverable-remove data-bs-toggle="tooltip" data-bs-title="Remove deliverable" aria-label="Remove deliverable">
                <i class="bi bi-trash"></i>
            </button>
        </div>
    </td>
</tr>