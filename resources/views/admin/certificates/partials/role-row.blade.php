@php
    $rowId = $row['id'] ?? null;
    $isExisting = filled($rowId);
@endphp
<div class="d-flex gap-2 align-items-end" data-repeater-row data-existing="{{ $isExisting ? '1' : '0' }}">
    @if($isExisting)
        <input type="hidden" name="roles[{{ $roleIndex }}][id]" value="{{ $rowId }}">
    @endif
    <input type="hidden" name="roles[{{ $roleIndex }}][_delete]" value="0" data-repeater-delete>
    <div class="flex-grow-1">
        <input type="text" class="form-control form-control-sm"
               name="roles[{{ $roleIndex }}][name]" value="{{ $row['name'] ?? '' }}" placeholder="e.g. Observer">
    </div>
    <div class="form-check">
        <input class="form-check-input" type="checkbox" value="1"
               id="role-{{ $roleIndex }}-active"
               name="roles[{{ $roleIndex }}][active]"
               @checked(filter_var($row['active'] ?? true, FILTER_VALIDATE_BOOLEAN))>
        <label class="form-check-label small" for="role-{{ $roleIndex }}-active">Active</label>
    </div>
    <button type="button" class="btn btn-sm btn-outline-danger" data-repeater-remove>
        <i class="bi bi-trash"></i>
    </button>
</div>
