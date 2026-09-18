@php
    $rowId = $row['id'] ?? null;
    $isExisting = filled($rowId);
    $kind = $row['kind'] ?? \App\Enums\CertificateRequirementKind::ActivityCount->value;
    $dimensionRuleRows = $row['dimension_rules'] ?? [];
    $selectedGroupIndex = $row['group_index'] ?? '';
@endphp
<div class="card mb-2" data-repeater-row data-requirement-row data-existing="{{ $isExisting ? '1' : '0' }}">
    <div class="card-body py-2 px-3">
        @if($isExisting)
            <input type="hidden" name="requirements[{{ $reqIndex }}][id]" value="{{ $rowId }}">
        @endif
        <input type="hidden" name="requirements[{{ $reqIndex }}][_delete]" value="0" data-repeater-delete>
        <input type="hidden" name="requirements[{{ $reqIndex }}][phase]" value="{{ $phase }}">

        <div class="row g-2 align-items-end mb-2">
            <div class="col-md-3">
                <label class="form-label form-label-sm mb-1">Requirement label</label>
                <input type="text" class="form-control form-control-sm"
                       name="requirements[{{ $reqIndex }}][label]" value="{{ $row['label'] ?? '' }}"
                       placeholder="e.g. Wraparound 101">
            </div>
            <div class="col-md-2">
                <label class="form-label form-label-sm mb-1">Kind</label>
                <select class="form-select form-select-sm" data-kind-select
                        name="requirements[{{ $reqIndex }}][kind]">
                    @foreach(\App\Enums\CertificateRequirementKind::cases() as $case)
                        <option value="{{ $case->value }}" @selected($kind === $case->value)>{{ $case->label() }}</option>
                    @endforeach
                </select>
            </div>
            @if($groupOptions->isNotEmpty())
                <div class="col-md-2">
                    <label class="form-label form-label-sm mb-1">Group</label>
                    <select class="form-select form-select-sm" name="requirements[{{ $reqIndex }}][group_index]">
                        <option value="">No group</option>
                        @foreach($groupOptions as $groupIndex => $groupLabel)
                            <option value="{{ $groupIndex }}" @selected((string) $selectedGroupIndex === (string) $groupIndex)>{{ $groupLabel }}</option>
                        @endforeach
                    </select>
                </div>
            @endif
            <div class="col-md-2">
                <label class="form-label form-label-sm mb-1">Role (optional)</label>
                <select class="form-select form-select-sm" name="requirements[{{ $reqIndex }}][certification_role_id]">
                    <option value="">Any role</option>
                    @foreach($roleOptions as $role)
                        <option value="{{ $role->id }}" @selected((string) ($row['certification_role_id'] ?? '') === (string) $role->id)>{{ $role->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label form-label-sm mb-1">Target count</label>
                <input type="number" class="form-control form-control-sm" min="1"
                       name="requirements[{{ $reqIndex }}][target_count]" value="{{ $row['target_count'] ?? 1 }}">
            </div>
            <div class="col-md-1 text-end">
                <button type="button" class="btn btn-sm btn-outline-danger" data-repeater-remove title="Remove requirement">
                    <i class="bi bi-trash"></i>
                </button>
            </div>
        </div>

        <div class="kind-field" data-kind-field="activity_count" style="{{ $kind === 'activity_count' ? '' : 'display:none;' }}">
            <div class="row g-2">
                <div class="col-md-4">
                    <label class="form-label form-label-sm mb-1">Activity family</label>
                    <select class="form-select form-select-sm" name="requirements[{{ $reqIndex }}][contact_family_id]">
                        <option value="">Any</option>
                        @foreach($contactFamilies as $family)
                            <option value="{{ $family->id }}" @selected((string) ($row['contact_family_id'] ?? '') === (string) $family->id)>{{ $family->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label form-label-sm mb-1">Activity type</label>
                    <select class="form-select form-select-sm" name="requirements[{{ $reqIndex }}][activity_type_id]">
                        <option value="">Any</option>
                        @foreach($activityTypes as $type)
                            <option value="{{ $type->id }}" @selected((string) ($row['activity_type_id'] ?? '') === (string) $type->id)>{{ $type->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label form-label-sm mb-1">Rolling window (months)</label>
                    <input type="number" class="form-control form-control-sm" min="1"
                           name="requirements[{{ $reqIndex }}][window_months]" value="{{ $row['window_months'] ?? '' }}"
                           placeholder="Certificate default">
                </div>
            </div>
        </div>

        <div class="kind-field" data-kind-field="tool_submission" style="{{ $kind === 'tool_submission' ? '' : 'display:none;' }}">
            <div class="row g-2 align-items-end mb-2">
                <div class="col-md-4">
                    <label class="form-label form-label-sm mb-1">Tool</label>
                    <select class="form-select form-select-sm"
                            name="requirements[{{ $reqIndex }}][certification_tool_id]">
                        <option value="">Select tool…</option>
                        @foreach($certificationTools as $tool)
                            <option value="{{ $tool->id }}" @selected((string) ($row['certification_tool_id'] ?? '') === (string) $tool->id)>{{ $tool->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-4">
                    <div class="form-check mt-4">
                        <input class="form-check-input" type="checkbox" value="1"
                               id="requirements-{{ $reqIndex }}-requires-passing"
                               name="requirements[{{ $reqIndex }}][requires_passing]"
                               @checked(filter_var($row['requires_passing'] ?? true, FILTER_VALIDATE_BOOLEAN))>
                        <label class="form-check-label" for="requirements-{{ $reqIndex }}-requires-passing">Only passing submissions count</label>
                    </div>
                </div>
                <div class="col-md-4">
                    <label class="form-label form-label-sm mb-1">Rolling window (months)</label>
                    <input type="number" class="form-control form-control-sm" min="1"
                           name="requirements[{{ $reqIndex }}][window_months]" value="{{ $row['window_months'] ?? '' }}"
                           placeholder="Certificate default">
                </div>
            </div>

            <label class="form-label form-label-sm mb-1">Dimension rules <small class="text-muted fw-normal">(optional; e.g. span all phases, or 4 of 6 full reviews)</small></label>
            <x-repeater-rows
                name="requirements[{{ $reqIndex }}][dimension_rules]"
                :rows="$dimensionRuleRows"
                index-token="__DR_INDEX__"
                add-label="Add dimension rule"
                :template="view('admin.certificates.partials.dimension-rule-row', ['row' => [], 'reqIndex' => $reqIndex, 'ruleIndex' => '__DR_INDEX__', 'certificationTools' => $certificationTools])->render()"
            >
                @foreach($dimensionRuleRows as $ruleIndex => $rule)
                    @include('admin.certificates.partials.dimension-rule-row', ['row' => $rule, 'reqIndex' => $reqIndex, 'ruleIndex' => $ruleIndex, 'certificationTools' => $certificationTools])
                @endforeach
            </x-repeater-rows>
        </div>

        <div class="row g-2 mt-1">
            <div class="col-md-6">
                <label class="form-label form-label-sm mb-1">Threshold note <small class="text-muted fw-normal">(display only, never evaluated)</small></label>
                <input type="text" class="form-control form-control-sm"
                       name="requirements[{{ $reqIndex }}][threshold_note]" value="{{ $row['threshold_note'] ?? '' }}"
                       placeholder="e.g. 85% overall match">
            </div>
            <div class="col-md-6">
                <label class="form-label form-label-sm mb-1">Notes</label>
                <input type="text" class="form-control form-control-sm"
                       name="requirements[{{ $reqIndex }}][notes]" value="{{ $row['notes'] ?? '' }}">
            </div>
        </div>
    </div>
</div>
