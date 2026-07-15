@php
    $rowKey = $row['row_key'];
    $assignmentGroups = $row['assignment_groups'] ?? [];
    $hasAssignments = !empty($assignmentGroups);
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
    <td class="text-wrap align-top" style="min-width: 180px; max-width: 280px; white-space: normal;">
        @if($hasAssignments)
            @foreach($assignmentGroups as $group)
                <div class="mb-2 w-100">
                    @if(!empty($group['team_name']))
                        <div class="d-block mb-1">
                            <span class="badge bg-secondary-subtle text-secondary-emphasis border">{{ $group['team_name'] }}</span>
                        </div>
                        @if(!empty($group['users']))
                            <div class="ps-2 d-flex flex-column align-items-start gap-1">
                                @foreach($group['users'] as $name)
                                    <span class="badge bg-primary-subtle text-primary-emphasis border">{{ $name }}</span>
                                @endforeach
                            </div>
                        @endif
                    @elseif(!empty($group['users']))
                        <div class="d-flex flex-column align-items-start gap-1">
                            @foreach($group['users'] as $name)
                                <span class="badge bg-primary-subtle text-primary-emphasis border">{{ $name }}</span>
                            @endforeach
                        </div>
                    @endif
                </div>
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
