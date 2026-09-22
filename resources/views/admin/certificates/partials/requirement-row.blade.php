@php
    $rowId = $row['id'] ?? null;
    $isExisting = filled($rowId);
    $kind = $row['kind'] ?? \App\Enums\CertificateRequirementKind::ActivityCount->value;
    $dimensionRuleRows = $row['dimension_rules'] ?? [];
    $selectedGroupIndex = $row['group_index'] ?? '';
    $kindLabel = \App\Enums\CertificateRequirementKind::tryFrom($kind)?->label() ?? 'Requirement';
@endphp
<div class="repeater-row-card" data-repeater-row data-requirement-row data-repeater-collapsible
     data-existing="{{ $isExisting ? '1' : '0' }}"
     data-repeater-mode="{{ $isExisting ? 'display' : 'edit' }}">
    @if($isExisting)
        <input type="hidden" name="requirements[{{ $reqIndex }}][id]" value="{{ $rowId }}">
    @endif
    <input type="hidden" name="requirements[{{ $reqIndex }}][_delete]" value="0" data-repeater-delete>
    <input type="hidden" name="requirements[{{ $reqIndex }}][phase]" value="{{ $phase }}">

    <div class="repeater-row-toolbar" data-row-display-click>
        <span class="repeater-row-drag" data-repeater-drag title="Drag to reorder" aria-hidden="true">
            <i class="bi bi-grip-vertical"></i>
        </span>
        <div class="repeater-row-summary">
            <span class="repeater-row-title" data-summary-value="label" data-summary-fallback="Untitled requirement">{{ ($row['label'] ?? '') !== '' ? $row['label'] : 'Untitled requirement' }}</span>
            <span class="repeater-row-meta" data-summary-value="kind">{{ $kindLabel }}</span>
            <span class="repeater-row-meta">Target: <span data-summary-value="target_count">{{ $row['target_count'] ?? 1 }}</span></span>
        </div>
        <div class="repeater-row-actions">
            <button type="button" class="repeater-icon-btn" data-row-edit title="{{ $isExisting ? 'Edit requirement' : 'Collapse requirement' }}">
                <i class="bi bi-pencil" data-icon-edit></i>
                <i class="bi bi-chevron-up" data-icon-collapse></i>
            </button>
            <button type="button" class="repeater-icon-btn repeater-icon-btn-danger" data-repeater-remove title="Remove requirement">
                <i class="bi bi-x-circle"></i>
            </button>
        </div>
    </div>

    <div class="repeater-row-body repeater-row-body--form" data-row-edit-fields>
        <div class="row g-3">
            <div class="col-12">
                <label class="form-label mb-1">Requirement label</label>
                <input type="text" class="form-control" data-summary-source="label"
                       name="requirements[{{ $reqIndex }}][label]" value="{{ $row['label'] ?? '' }}"
                       placeholder="e.g. Wraparound 101">
            </div>
            <div class="col-md-6">
                <label class="form-label mb-1">Kind</label>
                <select class="form-select" data-kind-select data-summary-source="kind"
                        name="requirements[{{ $reqIndex }}][kind]">
                    @foreach(\App\Enums\CertificateRequirementKind::cases() as $case)
                        <option value="{{ $case->value }}" @selected($kind === $case->value)>{{ $case->label() }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label mb-1">Role</label>
                <select class="form-select" name="requirements[{{ $reqIndex }}][certification_role_id]">
                    <option value="">Any role</option>
                    @foreach($roleOptions as $role)
                        <option value="{{ $role->id }}" @selected((string) ($row['certification_role_id'] ?? '') === (string) $role->id)>{{ $role->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label mb-1">Target count</label>
                <input type="number" class="form-control" min="1" data-summary-source="target_count"
                       name="requirements[{{ $reqIndex }}][target_count]" value="{{ $row['target_count'] ?? 1 }}">
            </div>
            @if($groupOptions->isNotEmpty())
                <div class="col-md-6">
                    <label class="form-label mb-1">Group</label>
                    <select class="form-select" name="requirements[{{ $reqIndex }}][group_index]">
                        <option value="">No group</option>
                        @foreach($groupOptions as $groupIndex => $groupLabel)
                            <option value="{{ $groupIndex }}" @selected((string) $selectedGroupIndex === (string) $groupIndex)>{{ $groupLabel }}</option>
                        @endforeach
                    </select>
                </div>
            @endif
        </div>

        <div class="kind-field" data-kind-field="activity_count" style="{{ $kind === 'activity_count' ? '' : 'display:none;' }}">
            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label mb-1">Activity family</label>
                    <select class="form-select" name="requirements[{{ $reqIndex }}][contact_family_id]">
                        <option value="">Any</option>
                        @foreach($contactFamilies as $family)
                            <option value="{{ $family->id }}" @selected((string) ($row['contact_family_id'] ?? '') === (string) $family->id)>{{ $family->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-6">
                    <label class="form-label mb-1">Activity type</label>
                    <select class="form-select" name="requirements[{{ $reqIndex }}][activity_type_id]">
                        <option value="">Any</option>
                        @foreach($activityTypes as $type)
                            <option value="{{ $type->id }}" @selected((string) ($row['activity_type_id'] ?? '') === (string) $type->id)>{{ $type->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label mb-1">Rolling window (months)</label>
                    <input type="number" class="form-control" min="1"
                           name="requirements[{{ $reqIndex }}][window_months]" value="{{ $row['window_months'] ?? '' }}"
                           placeholder="Certificate default">
                </div>
            </div>
        </div>

        <div class="kind-field" data-kind-field="tool_submission" style="{{ $kind === 'tool_submission' ? '' : 'display:none;' }}">
            <div class="row g-3">
                <div class="col-md-8">
                    <label class="form-label mb-1">Tool</label>
                    <select class="form-select"
                            name="requirements[{{ $reqIndex }}][certification_tool_id]">
                        <option value="">Select tool…</option>
                        @foreach($certificationTools as $tool)
                            <option value="{{ $tool->id }}" @selected((string) ($row['certification_tool_id'] ?? '') === (string) $tool->id)>{{ $tool->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label mb-1">Rolling window (months)</label>
                    <input type="number" class="form-control" min="1"
                           name="requirements[{{ $reqIndex }}][window_months]" value="{{ $row['window_months'] ?? '' }}"
                           placeholder="Certificate default">
                </div>
                <div class="col-12">
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" value="1"
                               id="requirements-{{ $reqIndex }}-requires-passing"
                               name="requirements[{{ $reqIndex }}][requires_passing]"
                               @checked(filter_var($row['requires_passing'] ?? true, FILTER_VALIDATE_BOOLEAN))>
                        <label class="form-check-label" for="requirements-{{ $reqIndex }}-requires-passing">Only passing submissions count</label>
                    </div>
                </div>
            </div>

            <div data-rules-list class="mt-3">
                <label class="form-label mb-2">Dimension rules <small class="text-muted fw-normal">(optional; e.g. span all phases, or 4 of 6 full reviews)</small></label>
                <x-repeater-rows
                    name="requirements[{{ $reqIndex }}][dimension_rules]"
                    :rows="$dimensionRuleRows"
                    index-token="__DR_INDEX__"
                    add-label="Add dimension rule"
                    variant="nested"
                    :scroll="false"
                    :template="view('admin.certificates.partials.dimension-rule-row', ['row' => [], 'reqIndex' => $reqIndex, 'ruleIndex' => '__DR_INDEX__', 'certificationTools' => $certificationTools])->render()"
                >
                    @foreach($dimensionRuleRows as $ruleIndex => $rule)
                        @include('admin.certificates.partials.dimension-rule-row', ['row' => $rule, 'reqIndex' => $reqIndex, 'ruleIndex' => $ruleIndex, 'certificationTools' => $certificationTools])
                    @endforeach
                </x-repeater-rows>
            </div>
        </div>

        <div class="row g-3">
            <div class="col-md-6">
                <label class="form-label mb-1">Threshold note <small class="text-muted fw-normal">(display only)</small></label>
                <input type="text" class="form-control"
                       name="requirements[{{ $reqIndex }}][threshold_note]" value="{{ $row['threshold_note'] ?? '' }}"
                       placeholder="e.g. 85% overall match">
            </div>
            <div class="col-md-6">
                <label class="form-label mb-1">Notes</label>
                <input type="text" class="form-control"
                       name="requirements[{{ $reqIndex }}][notes]" value="{{ $row['notes'] ?? '' }}">
            </div>
        </div>
    </div>
</div>
