@php
    $rulePrefix = 'requirements.'.$reqIndex.'.dimension_rules.'.$ruleIndex;
    $ruleField = 'requirements['.$reqIndex.'][dimension_rules]['.$ruleIndex.']';
    $ruleControl = 'req-'.$reqIndex.'-rule-'.$ruleIndex;
    $ruleDeleted = filter_var($row['_delete'] ?? false, FILTER_VALIDATE_BOOLEAN);
@endphp
<div class="border-bottom mb-3 pb-2" data-cert-rule="{{ $ruleIndex }}" @if($ruleDeleted) hidden @endif>
    @if(filled($row['id'] ?? null))<input type="hidden" name="{{ $ruleField }}[id]" value="{{ $row['id'] }}">@endif
    <input type="hidden" name="{{ $ruleField }}[_delete]" value="{{ $ruleDeleted ? 1 : 0 }}" data-cert-delete>
    <div class="row g-3">
        <div class="col-md-6">
            <x-form-field label="Dimension" :for="$ruleControl.'-dimension'" :name="$rulePrefix.'.certification_tool_dimension_id'">
                <select class="form-select" id="{{ $ruleControl }}-dimension" name="{{ $ruleField }}[certification_tool_dimension_id]" data-cert-dimension>
                    <option value="">Choose dimension…</option>
                    @foreach($certificationTools as $tool)
                        @foreach($tool->dimensions as $dimension)
                            <option value="{{ $dimension->id }}" data-tool="{{ $tool->id }}" @selected((string)($row['certification_tool_dimension_id'] ?? '') === (string)$dimension->id)>{{ $dimension->name }}</option>
                        @endforeach
                    @endforeach
                </select>
            </x-form-field>
        </div>
        <div class="col-md-6">
            <x-form-field label="Rule" :for="$ruleControl.'-mode'" :name="$rulePrefix.'.mode'">
                <select class="form-select" id="{{ $ruleControl }}-mode" name="{{ $ruleField }}[mode]" data-cert-rule-mode>
                    <option value="coverage" @selected(($row['mode'] ?? 'coverage') === 'coverage')>Cover every selected category</option>
                    <option value="quota" @selected(($row['mode'] ?? '') === 'quota')>Minimum submissions in selected categories</option>
                </select>
            </x-form-field>
        </div>
    </div>
    <div data-cert-quota>
        <x-form-field label="Minimum qualifying submissions" :for="$ruleControl.'-minimum'" :name="$rulePrefix.'.min_count'" help="Counts submissions in any selected category; this is not the number of categories.">
            <input type="number" class="form-control" id="{{ $ruleControl }}-minimum" name="{{ $ruleField }}[min_count]" min="1" value="{{ $row['min_count'] ?? '' }}" data-cert-minimum>
        </x-form-field>
    </div>
    <x-form-field label="Categories" :for="$ruleControl.'-options'" :name="$rulePrefix.'.option_ids'" :group="true">
        <div id="{{ $ruleControl }}-options">
            @foreach($certificationTools as $tool)
                @foreach($tool->dimensions as $dimension)
                    <div data-cert-options="{{ $dimension->id }}">
                        @foreach($dimension->options as $option)
                            <div class="form-check">
                                <input type="checkbox" class="form-check-input" id="{{ $ruleControl }}-option-{{ $option->id }}" name="{{ $ruleField }}[option_ids][]" value="{{ $option->id }}" @checked(in_array((string)$option->id, array_map('strval', $row['option_ids'] ?? []), true))>
                                <label class="form-check-label" for="{{ $ruleControl }}-option-{{ $option->id }}">{{ $option->label }}</label>
                            </div>
                        @endforeach
                    </div>
                @endforeach
            @endforeach
        </div>
        <p class="form-text" data-cert-category-help></p>
    </x-form-field>
    <p class="small" data-cert-rule-summary></p>
    <button type="button" class="btn btn-sm btn-outline-danger" data-cert-remove="rule">Remove rule</button>
</div>
