@php
    $rowKey = $row['row_key'];
    $assignmentBadges = $row['assignment_badges'] ?? ['teams' => [], 'users' => []];
    $teamNames = $assignmentBadges['teams'] ?? [];
    $userNames = $assignmentBadges['users'] ?? [];
    $hasAssignments = !empty($teamNames) || !empty($userNames);
@endphp

<tr data-deliverable-row data-row-key="{{ $rowKey }}" data-deliverable-row-data='@json($row)' @if(!empty($row['_delete']) && $row['_delete'] === '1') class="table-active text-muted" style="display:none" @endif>
    <td>
        <div class="fw-semibold">{{ $row['contact_family_label'] ?: '—' }}</div>
        <div class="text-muted small">{{ $row['activity_type_label'] ?: 'Any activity type' }}</div>
        @if(!empty($row['program_label']))
            <div class="text-muted small">Program: {{ $row['program_label'] }}</div>
        @endif
    </td>
    <td>
        <div class="small">{{ $row['rules_summary'] ?: '—' }}</div>
    </td>
    <td>
        @if($hasAssignments)
            @foreach($teamNames as $name)
                <span class="badge bg-secondary-subtle text-secondary-emphasis border me-1 mb-1">{{ $name }}</span>
            @endforeach
            @foreach($userNames as $name)
                <span class="badge bg-primary-subtle text-primary-emphasis border me-1 mb-1">{{ $name }}</span>
            @endforeach
        @else
            <span class="text-muted small">—</span>
        @endif
    </td>
    <td class="text-wrap" style="min-width: 200px; max-width: 100%; white-space: normal;">{{ $row['notes'] ?: '—' }}</td>
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
