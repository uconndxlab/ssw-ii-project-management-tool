@php
    $ruleId = $row['id'] ?? null;
    $isExisting = filled($ruleId);
    $mode = $row['mode'] ?? \App\Enums\CertificateDimensionRuleMode::Coverage->value;
    $selectedDimensionId = $row['certification_tool_dimension_id'] ?? null;
    $selectedOptionIds = collect($row['option_ids'] ?? [])->map(fn ($id) => (int) $id)->all();
    $selectedDimension = $certificationTools->flatMap(fn ($tool) => $tool->dimensions)->firstWhere('id', $selectedDimensionId);
    $dimensionName = $selectedDimension?->name ?? '';
    $modeLabel = \App\Enums\CertificateDimensionRuleMode::tryFrom($mode)?->label() ?? '';
    $optionTotal = $selectedDimension?->options?->count() ?? 0;
    $optionSelected = $selectedDimension
        ? $selectedDimension->options->filter(fn ($option) => in_array($option->id, $selectedOptionIds, true))->count()
        : 0;
@endphp
<div class="repeater-row-card" data-repeater-row data-dimension-rule-row data-repeater-collapsible
     data-existing="{{ $isExisting ? '1' : '0' }}"
     data-repeater-mode="{{ $isExisting ? 'display' : 'edit' }}">
    @if($isExisting)
        <input type="hidden" name="requirements[{{ $reqIndex }}][dimension_rules][{{ $ruleIndex }}][id]" value="{{ $ruleId }}">
    @endif
    <input type="hidden" name="requirements[{{ $reqIndex }}][dimension_rules][{{ $ruleIndex }}][_delete]" value="0" data-repeater-delete>

    <div class="repeater-row-toolbar" data-row-display-click>
        <span class="repeater-row-drag" data-repeater-drag title="Drag to reorder" aria-hidden="true">
            <i class="bi bi-grip-vertical"></i>
        </span>
        <div class="repeater-row-summary">
            <span class="repeater-row-title" data-summary-value="dimension" data-summary-fallback="No dimension selected">{{ $dimensionName !== '' ? $dimensionName : 'No dimension selected' }}</span>
            <span class="repeater-row-meta" data-summary-value="mode">{{ $modeLabel }}</span>
            <span class="repeater-row-meta"><span data-summary-selected-options>{{ $optionSelected }} of {{ $optionTotal }}</span> options</span>
        </div>
        <div class="repeater-row-actions">
            <button type="button" class="repeater-icon-btn" data-row-edit title="{{ $isExisting ? 'Edit rule' : 'Collapse rule' }}">
                <i class="bi bi-pencil" data-icon-edit></i>
                <i class="bi bi-chevron-up" data-icon-collapse></i>
            </button>
            <button type="button" class="repeater-icon-btn repeater-icon-btn-danger" data-repeater-remove title="Remove rule">
                <i class="bi bi-x-circle"></i>
            </button>
        </div>
    </div>

    <div class="repeater-row-body repeater-row-body--form" data-row-edit-fields>
        <div class="row g-3">
            <div class="col-md-6">
                <label class="form-label mb-1">Dimension <small class="text-muted fw-normal">(<span data-summary-dimension-options>{{ $optionTotal }}</span> options)</small></label>
                <select class="form-select" data-dimension-select data-summary-source="dimension"
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
            <div class="col-md-6">
                <label class="form-label mb-1">Rule</label>
                <select class="form-select" data-mode-select data-summary-source="mode"
                        name="requirements[{{ $reqIndex }}][dimension_rules][{{ $ruleIndex }}][mode]">
                    @foreach(\App\Enums\CertificateDimensionRuleMode::cases() as $case)
                        <option value="{{ $case->value }}" @selected($mode === $case->value)>{{ $case->label() }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-4" data-min-count-group style="{{ $mode === \App\Enums\CertificateDimensionRuleMode::Quota->value ? '' : 'display:none;' }}">
                <label class="form-label mb-1">Min count <small class="text-muted fw-normal">(of <span data-summary-dimension-options>{{ $optionTotal }}</span> options)</small></label>
                <input type="number" class="form-control" min="1"
                       name="requirements[{{ $reqIndex }}][dimension_rules][{{ $ruleIndex }}][min_count]" value="{{ $row['min_count'] ?? '' }}">
            </div>
            <div class="col-12">
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
                                    <label class="form-check-label" for="req-{{ $reqIndex }}-rule-{{ $ruleIndex }}-opt-{{ $option->id }}">
                                        {{ $option->label }}
                                    </label>
                                </div>
                            @endforeach
                        </div>
                    @endforeach
                @endforeach
            </div>
        </div>
    </div>
</div>
