@php
    $ruleId = $row['id'] ?? null;
    $isExisting = filled($ruleId);
    $mode = $row['mode'] ?? \App\Enums\CertificateDimensionRuleMode::Coverage->value;
    $selectedDimensionId = $row['certification_tool_dimension_id'] ?? null;
    $selectedOptionIds = collect($row['option_ids'] ?? [])->map(fn ($id) => (int) $id)->all();
@endphp
<div class="border rounded p-2 mb-2" data-repeater-row data-dimension-rule-row data-existing="{{ $isExisting ? '1' : '0' }}">
    @if($isExisting)
        <input type="hidden" name="requirements[{{ $reqIndex }}][dimension_rules][{{ $ruleIndex }}][id]" value="{{ $ruleId }}">
    @endif
    <input type="hidden" name="requirements[{{ $reqIndex }}][dimension_rules][{{ $ruleIndex }}][_delete]" value="0" data-repeater-delete>

    <div class="row g-2 align-items-end">
        <div class="col-md-4">
            <label class="form-label form-label-sm mb-1">Dimension</label>
            <select class="form-select form-select-sm" data-dimension-select
                    name="requirements[{{ $reqIndex }}][dimension_rules][{{ $ruleIndex }}][certification_tool_dimension_id]">
                <option value="">Select dimension…</option>
                @foreach($certificationTools as $tool)
                    @if($tool->dimensions->isNotEmpty())
                        <optgroup label="{{ $tool->name }}">
                            @foreach($tool->dimensions as $dimension)
                                <option value="{{ $dimension->id }}" data-tool-id="{{ $tool->id }}"
                                        @selected((string) $selectedDimensionId === (string) $dimension->id)>
                                    {{ $dimension->name }}
                                </option>
                            @endforeach
                        </optgroup>
                    @endif
                @endforeach
            </select>
        </div>
        <div class="col-md-3">
            <label class="form-label form-label-sm mb-1">Rule</label>
            <select class="form-select form-select-sm" data-mode-select
                    name="requirements[{{ $reqIndex }}][dimension_rules][{{ $ruleIndex }}][mode]">
                @foreach(\App\Enums\CertificateDimensionRuleMode::cases() as $case)
                    <option value="{{ $case->value }}" @selected($mode === $case->value)>{{ $case->label() }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-md-2" data-min-count-group style="{{ $mode === \App\Enums\CertificateDimensionRuleMode::Quota->value ? '' : 'display:none;' }}">
            <label class="form-label form-label-sm mb-1">Min count</label>
            <input type="number" class="form-control form-control-sm" min="1"
                   name="requirements[{{ $reqIndex }}][dimension_rules][{{ $ruleIndex }}][min_count]" value="{{ $row['min_count'] ?? '' }}">
        </div>
        <div class="col-md-3 text-end">
            <button type="button" class="btn btn-sm btn-outline-danger" data-repeater-remove>
                <i class="bi bi-trash"></i> Remove rule
            </button>
        </div>
    </div>

    <div class="mt-2">
        @foreach($certificationTools as $tool)
            @foreach($tool->dimensions as $dimension)
                <div class="d-flex flex-wrap gap-3 @if((string) $selectedDimensionId !== (string) $dimension->id) d-none @endif"
                     data-option-group data-dimension-id="{{ $dimension->id }}">
                    @foreach($dimension->options as $option)
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox"
                                   id="req-{{ $reqIndex }}-rule-{{ $ruleIndex }}-opt-{{ $option->id }}"
                                   name="requirements[{{ $reqIndex }}][dimension_rules][{{ $ruleIndex }}][option_ids][]"
                                   value="{{ $option->id }}"
                                   @checked(in_array($option->id, $selectedOptionIds, true))>
                            <label class="form-check-label small" for="req-{{ $reqIndex }}-rule-{{ $ruleIndex }}-opt-{{ $option->id }}">
                                {{ $option->label }}
                            </label>
                        </div>
                    @endforeach
                </div>
            @endforeach
        @endforeach
    </div>
</div>
