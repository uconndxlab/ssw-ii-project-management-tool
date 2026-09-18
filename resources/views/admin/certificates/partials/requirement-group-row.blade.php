@php
    $rowId = $row['id'] ?? null;
    $isExisting = filled($rowId);
    $satisfyMode = $row['satisfy_mode'] ?? \App\Enums\CertificateGroupSatisfyMode::All->value;
@endphp
<div class="card mb-2" data-repeater-row data-existing="{{ $isExisting ? '1' : '0' }}">
    <div class="card-body py-2 px-3">
        @if($isExisting)
            <input type="hidden" name="requirement_groups[{{ $groupIndex }}][id]" value="{{ $rowId }}">
        @endif
        <input type="hidden" name="requirement_groups[{{ $groupIndex }}][_delete]" value="0" data-repeater-delete>
        <input type="hidden" name="requirement_groups[{{ $groupIndex }}][phase]" value="{{ $phase }}">
        <div class="row g-2 align-items-end">
            <div class="col-md-4">
                <label class="form-label form-label-sm mb-1">Group label</label>
                <input type="text" class="form-control form-control-sm"
                       name="requirement_groups[{{ $groupIndex }}][label]" value="{{ $row['label'] ?? '' }}"
                       placeholder="e.g. Coaching observations">
            </div>
            <div class="col-md-3">
                <label class="form-label form-label-sm mb-1">Satisfied when</label>
                <select class="form-select form-select-sm" data-group-satisfy-select
                        name="requirement_groups[{{ $groupIndex }}][satisfy_mode]">
                    @foreach(\App\Enums\CertificateGroupSatisfyMode::cases() as $case)
                        <option value="{{ $case->value }}" @selected($satisfyMode === $case->value)>{{ $case->label() }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-2" data-group-required-count-wrap style="{{ $satisfyMode === \App\Enums\CertificateGroupSatisfyMode::NOf->value ? '' : 'display:none;' }}">
                <label class="form-label form-label-sm mb-1">Required count</label>
                <input type="number" class="form-control form-control-sm" min="1"
                       name="requirement_groups[{{ $groupIndex }}][required_count]" value="{{ $row['required_count'] ?? '' }}">
            </div>
            <div class="col-md-3 text-end">
                <button type="button" class="btn btn-sm btn-outline-danger" data-repeater-remove>
                    <i class="bi bi-trash"></i> Remove group
                </button>
            </div>
        </div>
    </div>
</div>
