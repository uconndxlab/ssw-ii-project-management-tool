@php
    $fieldId = $row['id'] ?? null;
    $isExisting = filled($fieldId);
    $unit = $row['unit'] ?? null;
    $unitValue = $unit instanceof \App\Enums\CertificationToolScoreUnit ? $unit->value : ($unit ?? \App\Enums\CertificationToolScoreUnit::Percent->value);
@endphp
<div class="repeater-row-card repeater-row-inline" data-repeater-row data-existing="{{ $isExisting ? '1' : '0' }}">
    @if($isExisting)
        <input type="hidden" name="score_fields[{{ $fieldIndex }}][id]" value="{{ $fieldId }}">
    @endif
    <input type="hidden" name="score_fields[{{ $fieldIndex }}][_delete]" value="0" data-repeater-delete>
    <span class="repeater-row-drag" data-repeater-drag title="Drag to reorder" aria-hidden="true">
        <i class="bi bi-grip-vertical"></i>
    </span>
    <div class="repeater-inline-fields">
        <input type="text" class="form-control form-control-sm flex-grow-1"
               name="score_fields[{{ $fieldIndex }}][name]" value="{{ $row['name'] ?? '' }}"
               placeholder="e.g. Overall Match %" aria-label="Score field name">
        <select class="form-select form-select-sm flex-shrink-0" style="width:9rem;"
                name="score_fields[{{ $fieldIndex }}][unit]" aria-label="Unit">
            @foreach(\App\Enums\CertificationToolScoreUnit::cases() as $case)
                <option value="{{ $case->value }}" @selected($unitValue === $case->value)>{{ $case->label() }}</option>
            @endforeach
        </select>
    </div>
    <div class="repeater-row-actions">
        <button type="button" class="repeater-icon-btn repeater-icon-btn-danger" data-repeater-remove title="Remove score field">
            <i class="bi bi-x-circle"></i>
        </button>
    </div>
</div>
