@php
    $optId = $row['id'] ?? null;
    $isExisting = filled($optId);
@endphp
<div data-repeater-row data-existing="{{ $isExisting ? '1' : '0' }}">
    @if($isExisting)
        <input type="hidden" name="dimensions[{{ $dimIndex }}][options][{{ $optIndex }}][id]" value="{{ $optId }}">
    @endif
    <input type="hidden" name="dimensions[{{ $dimIndex }}][options][{{ $optIndex }}][_delete]" value="0" data-repeater-delete>
    <span class="repeater-row-drag" data-repeater-drag title="Drag to reorder" aria-hidden="true">
        <i class="bi bi-grip-vertical"></i>
    </span>
    <div class="flex-grow-1 min-w-0">
        <input type="text" class="form-control form-control-sm"
               name="dimensions[{{ $dimIndex }}][options][{{ $optIndex }}][label]" value="{{ $row['label'] ?? '' }}"
               placeholder="e.g. Phase 1" aria-label="Option label">
    </div>
    <button type="button" class="repeater-icon-btn repeater-icon-btn-danger" data-repeater-remove title="Remove option">
        <i class="bi bi-x-circle"></i>
    </button>
</div>
