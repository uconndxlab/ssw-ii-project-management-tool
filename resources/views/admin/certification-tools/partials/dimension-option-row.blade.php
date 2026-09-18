@php
    $optId = $row['id'] ?? null;
    $isExisting = filled($optId);
@endphp
<div class="d-flex gap-2 align-items-end" data-repeater-row data-existing="{{ $isExisting ? '1' : '0' }}">
    @if($isExisting)
        <input type="hidden" name="dimensions[{{ $dimIndex }}][options][{{ $optIndex }}][id]" value="{{ $optId }}">
    @endif
    <input type="hidden" name="dimensions[{{ $dimIndex }}][options][{{ $optIndex }}][_delete]" value="0" data-repeater-delete>
    <div class="flex-grow-1">
        <input type="text" class="form-control form-control-sm"
               name="dimensions[{{ $dimIndex }}][options][{{ $optIndex }}][label]" value="{{ $row['label'] ?? '' }}"
               placeholder="e.g. Phase 1">
    </div>
    <div style="width:5.5rem;">
        <input type="number" class="form-control form-control-sm"
               name="dimensions[{{ $dimIndex }}][options][{{ $optIndex }}][sort_order]" value="{{ $row['sort_order'] ?? 0 }}" min="0">
    </div>
    <button type="button" class="btn btn-sm btn-outline-danger" data-repeater-remove>
        <i class="bi bi-trash"></i>
    </button>
</div>
