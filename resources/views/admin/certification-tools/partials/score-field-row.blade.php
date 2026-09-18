@php
    $fieldId = $row['id'] ?? null;
    $isExisting = filled($fieldId);
    $unit = $row['unit'] ?? null;
    $unitValue = $unit instanceof \App\Enums\CertificationToolScoreUnit ? $unit->value : ($unit ?? \App\Enums\CertificationToolScoreUnit::Percent->value);
@endphp
<div class="d-flex gap-2 align-items-end" data-repeater-row data-existing="{{ $isExisting ? '1' : '0' }}">
    @if($isExisting)
        <input type="hidden" name="score_fields[{{ $fieldIndex }}][id]" value="{{ $fieldId }}">
    @endif
    <input type="hidden" name="score_fields[{{ $fieldIndex }}][_delete]" value="0" data-repeater-delete>
    <div class="flex-grow-1">
        <input type="text" class="form-control form-control-sm"
               name="score_fields[{{ $fieldIndex }}][name]" value="{{ $row['name'] ?? '' }}"
               placeholder="e.g. Overall Match %">
    </div>
    <div style="width:9rem;">
        <select class="form-select form-select-sm" name="score_fields[{{ $fieldIndex }}][unit]">
            @foreach(\App\Enums\CertificationToolScoreUnit::cases() as $case)
                <option value="{{ $case->value }}" @selected($unitValue === $case->value)>{{ $case->label() }}</option>
            @endforeach
        </select>
    </div>
    <div style="width:5.5rem;">
        <input type="number" class="form-control form-control-sm"
               name="score_fields[{{ $fieldIndex }}][sort_order]" value="{{ $row['sort_order'] ?? 0 }}" min="0">
    </div>
    <button type="button" class="btn btn-sm btn-outline-danger" data-repeater-remove>
        <i class="bi bi-trash"></i>
    </button>
</div>
