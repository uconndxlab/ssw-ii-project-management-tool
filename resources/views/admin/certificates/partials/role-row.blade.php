@php
    $rowId = $row['id'] ?? null;
    $isExisting = filled($rowId);
@endphp
<div class="repeater-row-card repeater-row-inline" data-repeater-row data-existing="{{ $isExisting ? '1' : '0' }}">
    @if($isExisting)
        <input type="hidden" name="roles[{{ $roleIndex }}][id]" value="{{ $rowId }}">
    @endif
    <input type="hidden" name="roles[{{ $roleIndex }}][_delete]" value="0" data-repeater-delete>
    <span class="repeater-row-drag" data-repeater-drag title="Drag to reorder" aria-hidden="true">
        <i class="bi bi-grip-vertical"></i>
    </span>
    <div class="repeater-inline-fields">
        <input type="text" class="form-control form-control-sm flex-grow-1"
               name="roles[{{ $roleIndex }}][name]" value="{{ $row['name'] ?? '' }}" placeholder="e.g. Observer"
               aria-label="Role name">
        <div class="form-check flex-shrink-0 mb-0">
            <input class="form-check-input" type="checkbox" value="1"
                   id="role-{{ $roleIndex }}-active"
                   name="roles[{{ $roleIndex }}][active]"
                   @checked(filter_var($row['active'] ?? true, FILTER_VALIDATE_BOOLEAN))>
            <label class="form-check-label small" for="role-{{ $roleIndex }}-active">Active</label>
        </div>
    </div>
    <div class="repeater-row-actions">
        <button type="button" class="repeater-icon-btn repeater-icon-btn-danger" data-repeater-remove title="Remove role">
            <i class="bi bi-x-circle"></i>
        </button>
    </div>
</div>
