@php
    $rowId = $row['id'] ?? null;
    $isExisting = filled($rowId);
    $satisfyMode = $row['satisfy_mode'] ?? \App\Enums\CertificateGroupSatisfyMode::All->value;
@endphp
<div class="repeater-row-card repeater-row-inline is-labeled" data-repeater-row data-requirement-group-row data-group-index="{{ $groupIndex }}" data-existing="{{ $isExisting ? '1' : '0' }}">
    @if($isExisting)
        <input type="hidden" name="requirement_groups[{{ $groupIndex }}][id]" value="{{ $rowId }}">
    @endif
    <input type="hidden" name="requirement_groups[{{ $groupIndex }}][_delete]" value="0" data-repeater-delete>
    <input type="hidden" name="requirement_groups[{{ $groupIndex }}][phase]" value="{{ $phase }}">
    <span class="repeater-row-drag" data-repeater-drag title="Drag to reorder" aria-hidden="true">
        <i class="bi bi-grip-vertical"></i>
    </span>
    <div class="repeater-inline-fields">
        <div>
            <label class="form-label form-label-sm mb-1">Group label</label>
            <input type="text" class="form-control form-control-sm"
                   name="requirement_groups[{{ $groupIndex }}][label]" value="{{ $row['label'] ?? '' }}"
                   placeholder="e.g. Coaching observations">
        </div>
        <div>
            <label class="form-label form-label-sm mb-1">Satisfied when</label>
            <select class="form-select form-select-sm" data-group-satisfy-select
                    name="requirement_groups[{{ $groupIndex }}][satisfy_mode]">
                @foreach(\App\Enums\CertificateGroupSatisfyMode::cases() as $case)
                    <option value="{{ $case->value }}" @selected($satisfyMode === $case->value)>{{ $case->label() }}</option>
                @endforeach
            </select>
        </div>
        <div data-group-required-count-wrap style="{{ $satisfyMode === \App\Enums\CertificateGroupSatisfyMode::NOf->value ? '' : 'display:none;' }}">
            <label class="form-label form-label-sm mb-1">Required count</label>
            <input type="number" class="form-control form-control-sm" min="1"
                   name="requirement_groups[{{ $groupIndex }}][required_count]" value="{{ $row['required_count'] ?? '' }}">
        </div>
    </div>
    <div class="repeater-row-actions">
        <button type="button" class="repeater-icon-btn repeater-icon-btn-danger" data-repeater-remove title="Remove group">
            <i class="bi bi-x-circle"></i>
        </button>
    </div>
</div>
