@php
    $isEditMode = isset($certificate);
    $selectedProjectIds = old('project_ids', $isEditMode ? $certificate->projects->pluck('id')->toArray() : []);
    $selectedProgramIds = old('program_ids', $isEditMode ? $certificate->programs->pluck('id')->toArray() : []);
    $scopeId = $isEditMode ? 'certificate-edit-scope' : 'certificate-create-scope';

    $selectedPrerequisiteIds = old('prerequisite_certificate_ids', $isEditMode ? $certificate->prerequisites->pluck('id')->toArray() : []);

    $roleRows = old('roles', $isEditMode ? $certificate->roles->map(fn ($r) => [
        'id' => $r->id,
        'name' => $r->name,
        'active' => $r->active,
    ])->all() : []);

    if ($isEditMode) {
        $groupIdToIndex = [];
        $allGroupRows = [];
        foreach ($certificate->requirementGroups as $i => $group) {
            $allGroupRows[$i] = [
                'id' => $group->id,
                'phase' => $group->phase?->value ?? $group->phase,
                'label' => $group->label,
                'satisfy_mode' => $group->satisfy_mode?->value ?? $group->satisfy_mode,
                'required_count' => $group->required_count,
            ];
            $groupIdToIndex[$group->id] = $i;
        }

        $allRequirementRows = [];
        foreach ($certificate->requirements as $i => $requirement) {
            $allRequirementRows[$i] = [
                'id' => $requirement->id,
                'phase' => $requirement->phase?->value ?? $requirement->phase,
                'group_index' => $requirement->certificate_requirement_group_id ? ($groupIdToIndex[$requirement->certificate_requirement_group_id] ?? '') : '',
                'kind' => $requirement->kind?->value ?? $requirement->kind,
                'contact_family_id' => $requirement->contact_family_id,
                'activity_type_id' => $requirement->activity_type_id,
                'certification_tool_id' => $requirement->certification_tool_id,
                'certification_role_id' => $requirement->certification_role_id,
                'target_count' => $requirement->target_count,
                'requires_passing' => $requirement->requires_passing,
                'threshold_note' => $requirement->threshold_note,
                'window_months' => $requirement->window_months,
                'label' => $requirement->label,
                'notes' => $requirement->notes,
                'dimension_rules' => $requirement->dimensionRules->map(fn ($rule) => [
                    'id' => $rule->id,
                    'certification_tool_dimension_id' => $rule->certification_tool_dimension_id,
                    'mode' => $rule->mode?->value ?? $rule->mode,
                    'option_ids' => $rule->option_ids ?? [],
                    'min_count' => $rule->min_count,
                ])->all(),
            ];
        }
    } else {
        $allGroupRows = [];
        $allRequirementRows = [];
    }

    $allGroupRows = old('requirement_groups', $allGroupRows);
    $allRequirementRows = old('requirements', $allRequirementRows);

    $initialGroupRows = collect($allGroupRows)->filter(fn ($g) => ($g['phase'] ?? 'initial') === 'initial');
    $renewalGroupRows = collect($allGroupRows)->filter(fn ($g) => ($g['phase'] ?? 'initial') === 'renewal');
    $initialRequirementRows = collect($allRequirementRows)->filter(fn ($r) => ($r['phase'] ?? 'initial') === 'initial');
    $renewalRequirementRows = collect($allRequirementRows)->filter(fn ($r) => ($r['phase'] ?? 'initial') === 'renewal');

    $nextGroupIndex = empty($allGroupRows) ? 0 : max(array_keys($allGroupRows)) + 1;
    $nextRequirementIndex = empty($allRequirementRows) ? 0 : max(array_keys($allRequirementRows)) + 1;

    $initialGroupLabels = $initialGroupRows->map(fn ($g) => $g['label'] ?? 'Group');
    $renewalGroupLabels = $renewalGroupRows->map(fn ($g) => $g['label'] ?? 'Group');

    $roleOptions = ($globalRoles ?? collect())->merge($isEditMode ? $certificate->roles : collect());

    $renewalMatchesInitial = filter_var(old('renewal_matches_initial', $isEditMode ? $certificate->renewal_matches_initial : false), FILTER_VALIDATE_BOOLEAN);
@endphp

<x-section-card title="Information">
    <x-form-field label="Name" for="name" name="name" :required="true">
        <input type="text" class="form-control @error('name') is-invalid @enderror"
               id="name" name="name" value="{{ old('name', $certificate->name ?? '') }}" required>
    </x-form-field>

    <x-form-field label="Description" for="description" name="description">
        <textarea class="form-control @error('description') is-invalid @enderror"
                  id="description" name="description" rows="3">{{ old('description', $certificate->description ?? '') }}</textarea>
    </x-form-field>

    <x-project-program-scope-picker
        :scope-id="$scopeId"
        :projects="$projects"
        :selected-project-ids="$selectedProjectIds"
        :selected-program-ids="$selectedProgramIds"
        :show-scope-mode-selector="true"
        :selected-scope-mode="old('program_scope_mode', $certificate->program_scope_mode?->value ?? 'specific')"
        :lock-all="$isEditMode && $certificate->program_scope_mode?->value === 'all'"
        project-empty-selection-label="All projects"
        program-empty-selection-label="All programs"
    />

    <div class="row g-3 mt-1">
        <div class="col-md-4">
            <x-form-field label="Valid for (months)" for="validity_months" name="validity_months" help="Leave blank if it never expires.">
                <input type="number" class="form-control @error('validity_months') is-invalid @enderror"
                       id="validity_months" name="validity_months" min="1"
                       value="{{ old('validity_months', $certificate->validity_months ?? '') }}">
            </x-form-field>
        </div>
        <div class="col-md-4">
            <x-form-field label="Default rolling window (months)" for="default_window_months" name="default_window_months" help="Leave blank to count all-time. Individual requirements may override.">
                <input type="number" class="form-control @error('default_window_months') is-invalid @enderror"
                       id="default_window_months" name="default_window_months" min="1"
                       value="{{ old('default_window_months', $certificate->default_window_months ?? '') }}">
            </x-form-field>
        </div>
        <div class="col-md-4">
            <x-form-field label="Prerequisites satisfied when" for="prerequisite_mode" name="prerequisite_mode">
                <select class="form-select @error('prerequisite_mode') is-invalid @enderror" id="prerequisite_mode" name="prerequisite_mode">
                    <option value="all" @selected(old('prerequisite_mode', $certificate->prerequisite_mode ?? 'all') === 'all')>All selected</option>
                    <option value="any" @selected(old('prerequisite_mode', $certificate->prerequisite_mode ?? 'all') === 'any')>Any selected</option>
                </select>
            </x-form-field>
        </div>
    </div>

    <x-form-field label="Prerequisite certificates" name="prerequisite_certificate_ids" help="A candidate must already hold these (per the mode above) before pursuing this certificate.">
        <x-token-picker
            picker-id="certificate-prerequisites"
            name="prerequisite_certificate_ids[]"
            :items="$otherCertificates"
            :selected-ids="$selectedPrerequisiteIds"
            placeholder="Search certificates..."
        />
    </x-form-field>

    <x-form-options class="mt-4">
        <x-form-switch
            name="active"
            label="Active"
            help="Only active certificates can be pursued by new enrollments."
            :checked="old('active', $isEditMode ? $certificate->active : true)"
            class="mb-0"
        />
    </x-form-options>
</x-section-card>

<x-section-card title="Certificate-specific Roles" class="mt-3">
    <p class="text-muted small mb-2">Optional. Roles that only apply to this certificate's requirements (e.g. Trainer's Attend/Observe/Co-Train/Be Observed). Standard roles are managed separately.</p>
    <x-repeater-rows
        name="roles"
        :rows="$roleRows"
        add-label="Add role"
        :template="view('admin.certificates.partials.role-row', ['row' => [], 'roleIndex' => '__INDEX__'])->render()"
    >
        @foreach($roleRows as $roleIndex => $role)
            @include('admin.certificates.partials.role-row', ['row' => $role, 'roleIndex' => $roleIndex])
        @endforeach
    </x-repeater-rows>
</x-section-card>

<x-section-card title="Requirements" class="mt-3">
    <ul class="nav nav-tabs" role="tablist">
        <li class="nav-item" role="presentation">
            <button class="nav-link active" data-bs-toggle="tab" data-bs-target="#requirements-initial" type="button" role="tab">Initial Certification</button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link" data-bs-toggle="tab" data-bs-target="#requirements-renewal" type="button" role="tab">Recertification</button>
        </li>
    </ul>
    <div class="tab-content border border-top-0 rounded-bottom p-3">
        <div class="tab-pane fade show active" id="requirements-initial" role="tabpanel" data-requirements-phase="initial">
            <h6 class="text-muted small text-uppercase mt-1 mb-1">Requirement Groups</h6>
            <p class="text-muted small mb-2">Optional. Use when the candidate must complete all of these, any one, or a set number.</p>
            <x-repeater-rows
                name="requirement_groups"
                :rows="$initialGroupRows->all()"
                :next-index="$nextGroupIndex"
                add-label="Add group"
                :template="view('admin.certificates.partials.requirement-group-row', ['row' => [], 'groupIndex' => '__INDEX__', 'phase' => 'initial'])->render()"
            >
                @foreach($initialGroupRows as $groupIndex => $group)
                    @include('admin.certificates.partials.requirement-group-row', ['row' => $group, 'groupIndex' => $groupIndex, 'phase' => 'initial'])
                @endforeach
            </x-repeater-rows>

            <h6 class="text-muted small text-uppercase mt-4">Requirements</h6>
            <x-repeater-rows
                name="requirements"
                :rows="$initialRequirementRows->all()"
                :next-index="$nextRequirementIndex"
                add-label="Add requirement"
                :template="view('admin.certificates.partials.requirement-row', [
                    'row' => [], 'reqIndex' => '__INDEX__', 'phase' => 'initial',
                    'groupOptions' => $initialGroupLabels, 'roleOptions' => $roleOptions,
                    'contactFamilies' => $contactFamilies, 'activityTypes' => $activityTypes, 'certificationTools' => $certificationTools,
                ])->render()"
            >
                @foreach($initialRequirementRows as $reqIndex => $requirement)
                    @include('admin.certificates.partials.requirement-row', [
                        'row' => $requirement, 'reqIndex' => $reqIndex, 'phase' => 'initial',
                        'groupOptions' => $initialGroupLabels, 'roleOptions' => $roleOptions,
                        'contactFamilies' => $contactFamilies, 'activityTypes' => $activityTypes, 'certificationTools' => $certificationTools,
                    ])
                @endforeach
            </x-repeater-rows>
        </div>

        <div class="tab-pane fade" id="requirements-renewal" role="tabpanel" data-requirements-phase="renewal">
            <div class="mb-3 pb-3 border-bottom">
                <div class="form-check form-switch m-0 ps-0 d-flex align-items-start gap-2">
                    <input class="form-check-input ms-0 mt-1" type="checkbox" role="switch" value="1"
                           id="renewal_matches_initial" name="renewal_matches_initial" data-renewal-matches-initial
                           @checked($renewalMatchesInitial)>
                    <div>
                        <label class="form-check-label" for="renewal_matches_initial">
                            Recertification requirements are the same as initial certification
                        </label>
                        <div class="form-text">Recertification follows the initial list. This form stays empty, and later edits to the initial requirements apply here too.</div>
                    </div>
                </div>
                <div class="d-flex flex-wrap align-items-center gap-3 mt-3" data-renewal-actions>
                    <button type="button" class="btn btn-sm btn-outline-secondary" data-renewal-copy-initial hidden>
                        Copy initial requirements
                    </button>
                    <button type="button" class="btn btn-sm btn-outline-danger" data-renewal-clear hidden>
                        Clear requirements
                    </button>
                    <span class="text-muted small d-none" data-renewal-empty-note>
                        This certificate expires, and nothing is required to renew yet.
                    </span>
                </div>
            </div>

            <div data-renewal-requirements-form @class(['d-none' => $renewalMatchesInitial])>

            <h6 class="text-muted small text-uppercase mt-1 mb-1">Requirement Groups</h6>
            <p class="text-muted small mb-2">Optional. Use when the candidate must complete all of these, any one, or a set number.</p>
            <x-repeater-rows
                name="requirement_groups"
                :rows="$renewalGroupRows->all()"
                :next-index="$nextGroupIndex + 1000"
                add-label="Add group"
                :template="view('admin.certificates.partials.requirement-group-row', ['row' => [], 'groupIndex' => '__INDEX__', 'phase' => 'renewal'])->render()"
            >
                @foreach($renewalGroupRows as $groupIndex => $group)
                    @include('admin.certificates.partials.requirement-group-row', ['row' => $group, 'groupIndex' => $groupIndex, 'phase' => 'renewal'])
                @endforeach
            </x-repeater-rows>

            <h6 class="text-muted small text-uppercase mt-4">Requirements</h6>
            <x-repeater-rows
                name="requirements"
                :rows="$renewalRequirementRows->all()"
                :next-index="$nextRequirementIndex + 1000"
                add-label="Add requirement"
                :template="view('admin.certificates.partials.requirement-row', [
                    'row' => [], 'reqIndex' => '__INDEX__', 'phase' => 'renewal',
                    'groupOptions' => $renewalGroupLabels, 'roleOptions' => $roleOptions,
                    'contactFamilies' => $contactFamilies, 'activityTypes' => $activityTypes, 'certificationTools' => $certificationTools,
                ])->render()"
            >
                @foreach($renewalRequirementRows as $reqIndex => $requirement)
                    @include('admin.certificates.partials.requirement-row', [
                        'row' => $requirement, 'reqIndex' => $reqIndex, 'phase' => 'renewal',
                        'groupOptions' => $renewalGroupLabels, 'roleOptions' => $roleOptions,
                        'contactFamilies' => $contactFamilies, 'activityTypes' => $activityTypes, 'certificationTools' => $certificationTools,
                    ])
                @endforeach
            </x-repeater-rows>
            </div>
        </div>
    </div>
</x-section-card>

@once
<script>
(function () {
    const initialPane = document.getElementById('requirements-initial');
    const renewalPane = document.getElementById('requirements-renewal');
    const certificateForm = initialPane ? initialPane.closest('form') : null;
    let observer = null;
    let syncing = false;

    function pauseObserver(fn) {
        if (syncing) {
            fn();
            return;
        }

        syncing = true;
        if (observer) {
            observer.disconnect();
        }

        try {
            fn();
        } finally {
            syncing = false;
            if (observer) {
                observer.observe(document.body, { childList: true, subtree: true });
            }
        }
    }

    function syncRequirementRow(row) {
        const kindSelect = row.querySelector('[data-kind-select]');
        if (!kindSelect) return;
        row.querySelectorAll('.kind-field').forEach(function (field) {
            field.style.display = field.getAttribute('data-kind-field') === kindSelect.value ? '' : 'none';
        });
        syncDimensionOptionsForRequirement(row);
    }

    function syncDimensionRuleRow(row) {
        const modeSelect = row.querySelector('[data-mode-select]');
        const minCountGroup = row.querySelector('[data-min-count-group]');
        if (modeSelect && minCountGroup) {
            minCountGroup.style.display = modeSelect.value === 'quota' ? '' : 'none';
        }

        const dimensionSelect = row.querySelector('[data-dimension-select]');
        if (dimensionSelect) {
            row.querySelectorAll('[data-option-group]').forEach(function (group) {
                group.classList.toggle('d-none', group.getAttribute('data-dimension-id') !== dimensionSelect.value);
            });
        }
    }

    function syncGroupRow(row) {
        const satisfySelect = row.querySelector('[data-group-satisfy-select]');
        const requiredCountWrap = row.querySelector('[data-group-required-count-wrap]');
        if (satisfySelect && requiredCountWrap) {
            requiredCountWrap.style.display = satisfySelect.value === 'n_of' ? '' : 'none';
        }
    }

    function isRepeaterRowActive(row) {
        if (!row || row.style.display === 'none') {
            return false;
        }
        const deleteInput = row.querySelector('[data-repeater-delete]');
        return !deleteInput || deleteInput.value !== '1';
    }

    function groupsInPane(pane) {
        if (!pane) {
            return [];
        }
        const repeater = pane.querySelector('[data-repeater-name="requirement_groups"]');
        if (!repeater) {
            return [];
        }
        const groups = [];
        repeater.querySelectorAll('[data-requirement-group-row]').forEach(function (row) {
            if (!isRepeaterRowActive(row)) {
                return;
            }
            const index = row.getAttribute('data-group-index');
            const labelInput = row.querySelector('input[name*="[label]"]');
            const label = labelInput ? labelInput.value.trim() : '';
            groups.push({ index: index, label: label || 'Group' });
        });
        return groups;
    }

    function syncRequirementGroupSelects(pane) {
        if (!pane) {
            return;
        }
        const groups = groupsInPane(pane);
        pane.querySelectorAll('[data-requirement-group-select]').forEach(function (select) {
            const current = select.value;
            while (select.options.length > 1) {
                select.remove(1);
            }
            groups.forEach(function (group) {
                const option = document.createElement('option');
                option.value = group.index;
                option.textContent = group.label;
                select.appendChild(option);
            });
            if (current && groups.some(function (group) { return String(group.index) === String(current); })) {
                select.value = current;
            } else {
                select.value = '';
            }
        });
    }

    function programIdsForScope(section) {
        const programFieldName = section.dataset.programFieldName || 'program_ids[]';
        const fromInputs = Array.from(section.querySelectorAll('input[type="hidden"][name="' + programFieldName + '"]'))
            .map(function (input) { return String(input.value); })
            .filter(Boolean);
        if (fromInputs.length > 0) {
            return fromInputs;
        }

        // The program token picker writes those hidden inputs on DOMContentLoaded.
        // Until then, data-selected is the saved selection. Reading it here keeps an
        // in-scope tool selected on edit instead of treating the certificate as having no programs.
        const picker = section.querySelector('[data-token-picker][data-name="' + programFieldName + '"]');
        if (!picker || picker.dataset.tokenPickerInitialized === 'true') {
            return fromInputs;
        }

        try {
            const parsed = JSON.parse(picker.dataset.selected || '[]');
            return Array.isArray(parsed) ? parsed.map(String).filter(Boolean) : [];
        } catch (error) {
            return [];
        }
    }

    function certificateScopeFromForm() {
        const section = certificateForm ? certificateForm.querySelector('[data-project-program-scope]') : null;
        if (!section) {
            return { mode: 'specific', programIds: [] };
        }
        const mode = section.querySelector('input[name="program_scope_mode"]:checked')?.value
            || section.dataset.defaultScopeMode
            || 'specific';
        return { mode: String(mode), programIds: programIdsForScope(section) };
    }

    function toolOptionVisible(option, scopeMode, programIds) {
        const fieldMode = option.getAttribute('data-scope-mode') || 'specific';
        const fieldProgramIds = (option.getAttribute('data-program-ids') || '').split(',').filter(Boolean);
        const agreementMode = String(scopeMode || 'specific');
        const selectedPrograms = programIds.map(String);

        if (agreementMode === 'all') {
            return fieldMode !== 'none';
        }
        if (agreementMode === 'none' || selectedPrograms.length === 0) {
            return false;
        }
        if (fieldMode === 'all') {
            return true;
        }
        if (fieldMode === 'none' || fieldProgramIds.length === 0) {
            return false;
        }
        return fieldProgramIds.some(function (programId) {
            return selectedPrograms.includes(String(programId));
        });
    }

    function syncToolSelects(root) {
        const scope = certificateScopeFromForm();
        root.querySelectorAll('[data-certification-tool-select]').forEach(function (select) {
            const previous = select.value;
            let previousVisible = !previous;
            Array.from(select.options).forEach(function (option) {
                if (!option.value) {
                    return;
                }
                const visible = toolOptionVisible(option, scope.mode, scope.programIds);
                option.hidden = !visible;
                option.disabled = !visible;
                if (visible && String(option.value) === String(previous)) {
                    previousVisible = true;
                }
            });
            if (previous && previousVisible) {
                select.value = previous;
                return;
            }
            if (previous && !previousVisible) {
                select.value = '';
                select.dispatchEvent(new Event('change', { bubbles: true }));
            }
        });
    }

    function syncDimensionOptionsForRequirement(requirementRow) {
        const toolSelect = requirementRow.querySelector('[data-certification-tool-select]');
        const toolId = toolSelect ? toolSelect.value : '';
        requirementRow.querySelectorAll('[data-dimension-select]').forEach(function (dimensionSelect) {
            let selectedStillVisible = false;
            dimensionSelect.querySelectorAll('option').forEach(function (option) {
                if (!option.value) {
                    return;
                }
                const optionToolId = option.getAttribute('data-tool-id');
                const visible = !toolId || String(optionToolId) === String(toolId);
                option.hidden = !visible;
                option.disabled = !visible;
                if (visible && option.selected) {
                    selectedStillVisible = true;
                }
            });
            dimensionSelect.querySelectorAll('optgroup[data-tool-id]').forEach(function (group) {
                const visible = !toolId || String(group.getAttribute('data-tool-id')) === String(toolId);
                group.hidden = !visible;
            });
            if (!selectedStillVisible && dimensionSelect.value) {
                dimensionSelect.value = '';
                dimensionSelect.dispatchEvent(new Event('change', { bubbles: true }));
            }
        });
    }

    function syncAll(root) {
        root.querySelectorAll('[data-requirement-row]').forEach(syncRequirementRow);
        root.querySelectorAll('[data-dimension-rule-row]').forEach(syncDimensionRuleRow);
        root.querySelectorAll('[data-repeater-row]').forEach(function (row) {
            if (row.querySelector('[data-group-satisfy-select]')) {
                syncGroupRow(row);
            }
        });
        syncToolSelects(root);
        if (initialPane) {
            syncRequirementGroupSelects(initialPane);
        }
        if (renewalPane) {
            syncRequirementGroupSelects(renewalPane);
        }
        updateRenewalActions();
    }

    function liveRowsInPane(pane) {
        if (!pane) {
            return 0;
        }
        return Array.from(pane.querySelectorAll('[data-requirement-row], [data-requirement-group-row]'))
            .filter(function (row) { return isRepeaterRowActive(row); })
            .length;
    }

    function updateRenewalActions() {
        if (!renewalPane) {
            return;
        }
        const sameAsInitial = renewalPane.querySelector('[data-renewal-matches-initial]')?.checked;
        const actions = renewalPane.querySelector('[data-renewal-actions]');
        const copyBtn = renewalPane.querySelector('[data-renewal-copy-initial]');
        const clearBtn = renewalPane.querySelector('[data-renewal-clear]');
        const emptyNote = renewalPane.querySelector('[data-renewal-empty-note]');
        const renewalCount = liveRowsInPane(renewalPane);
        const initialCount = liveRowsInPane(initialPane);
        const validityInput = document.getElementById('validity_months');
        const hasValidity = validityInput && validityInput.value.trim() !== '';
        const showCopy = !sameAsInitial && renewalCount === 0 && initialCount > 0;
        const showClear = !sameAsInitial && renewalCount > 0;
        const showNote = !sameAsInitial && hasValidity && renewalCount === 0;

        if (copyBtn) {
            copyBtn.hidden = !showCopy;
        }
        if (clearBtn) {
            clearBtn.hidden = !showClear;
        }
        if (emptyNote) {
            emptyNote.classList.toggle('d-none', !showNote);
        }
        if (actions) {
            actions.classList.toggle('d-none', !showCopy && !showClear && !showNote);
        }
    }

    function removeRenewalRows() {
        if (!renewalPane) {
            return;
        }
        renewalPane.querySelectorAll('[data-repeater-row]').forEach(function (row) {
            const removeBtn = row.querySelector('[data-repeater-remove]');
            if (removeBtn && isRepeaterRowActive(row)) {
                removeBtn.click();
            }
        });
        updateRenewalActions();
    }

    function replaceIndexedNames(element, pattern, replacement) {
        element.querySelectorAll('[name]').forEach(function (field) {
            if (field.name) {
                field.name = field.name.replace(pattern, replacement);
            }
        });
        element.querySelectorAll('[id]').forEach(function (field) {
            if (field.id) {
                field.id = field.id.replace(pattern, replacement);
            }
        });
        element.querySelectorAll('[for]').forEach(function (field) {
            if (field.htmlFor) {
                field.htmlFor = field.htmlFor.replace(pattern, replacement);
            }
        });
    }

    function cloneInitialRequirementsToRenewal() {
        if (!initialPane || !renewalPane || liveRowsInPane(renewalPane) > 0) {
            return;
        }

        const groupMap = {};
        const groupsRepeater = renewalPane.querySelector('[data-repeater-name="requirement_groups"]');
        const requirementsRepeater = renewalPane.querySelector('[data-repeater-name="requirements"]');
        const initialGroupsContainer = initialPane.querySelector('[data-repeater-name="requirement_groups"] [data-repeater-rows]');
        const initialRequirementsContainer = initialPane.querySelector('[data-repeater-name="requirements"] [data-repeater-rows]');
        const renewalGroupsContainer = groupsRepeater.querySelector('[data-repeater-rows]');
        const renewalRequirementsContainer = requirementsRepeater.querySelector('[data-repeater-rows]');

        let nextGroupIndex = parseInt(groupsRepeater.getAttribute('data-next-index') || '0', 10);
        initialGroupsContainer.querySelectorAll('[data-requirement-group-row]').forEach(function (row) {
            if (!isRepeaterRowActive(row)) {
                return;
            }
            const oldIndex = row.getAttribute('data-group-index');
            const clone = row.cloneNode(true);
            clone.querySelectorAll('input[name*="[id]"]').forEach(function (input) { input.remove(); });
            clone.setAttribute('data-existing', '0');
            clone.setAttribute('data-group-index', String(nextGroupIndex));
            replaceIndexedNames(clone, new RegExp('requirement_groups\\[' + oldIndex + '\\]', 'g'), 'requirement_groups[' + nextGroupIndex + ']');
            const phaseInput = clone.querySelector('input[name*="[phase]"]');
            if (phaseInput) {
                phaseInput.value = 'renewal';
            }
            renewalGroupsContainer.appendChild(clone);
            groupMap[oldIndex] = String(nextGroupIndex);
            nextGroupIndex += 1;
        });
        groupsRepeater.setAttribute('data-next-index', String(nextGroupIndex));

        let nextReqIndex = parseInt(requirementsRepeater.getAttribute('data-next-index') || '0', 10);
        initialRequirementsContainer.querySelectorAll('[data-requirement-row]').forEach(function (row) {
            if (!isRepeaterRowActive(row)) {
                return;
            }
            const oldIndex = row.getAttribute('data-requirement-index');
            const clone = row.cloneNode(true);
            clone.querySelectorAll('input[name*="[id]"]').forEach(function (input) { input.remove(); });
            clone.setAttribute('data-existing', '0');
            clone.setAttribute('data-repeater-mode', 'edit');
            clone.setAttribute('data-requirement-index', String(nextReqIndex));
            replaceIndexedNames(clone, new RegExp('requirements\\[' + oldIndex + '\\]', 'g'), 'requirements[' + nextReqIndex + ']');
            const phaseInput = clone.querySelector('input[name*="[phase]"]');
            if (phaseInput) {
                phaseInput.value = 'renewal';
            }
            const groupSelect = clone.querySelector('[data-requirement-group-select]');
            if (groupSelect && groupSelect.value && groupMap[groupSelect.value]) {
                groupSelect.value = groupMap[groupSelect.value];
            } else if (groupSelect) {
                groupSelect.value = '';
            }
            renewalRequirementsContainer.appendChild(clone);
            nextReqIndex += 1;
        });
        requirementsRepeater.setAttribute('data-next-index', String(nextReqIndex));

        [renewalGroupsContainer, renewalRequirementsContainer].forEach(function (container) {
            const visible = Array.from(container.children).some(function (child) {
                return child.nodeType === 1
                    && child.hasAttribute('data-repeater-row')
                    && child.style.display !== 'none';
            });
            container.classList.toggle('is-empty', !visible);
        });

        syncAll(renewalPane);
    }

    document.addEventListener('change', function (e) {
        if (e.target.matches('[data-kind-select]')) {
            syncRequirementRow(e.target.closest('[data-requirement-row]'));
        }
        if (e.target.matches('[data-mode-select], [data-dimension-select]')) {
            syncDimensionRuleRow(e.target.closest('[data-dimension-rule-row]'));
        }
        if (e.target.matches('[data-group-satisfy-select]')) {
            syncGroupRow(e.target.closest('[data-repeater-row]'));
        }
        if (e.target.matches('[data-certification-tool-select]')) {
            syncDimensionOptionsForRequirement(e.target.closest('[data-requirement-row]'));
        }
        if (e.target.matches('[data-renewal-matches-initial]')) {
            const form = renewalPane.querySelector('[data-renewal-requirements-form]');
            if (e.target.checked) {
                if (liveRowsInPane(renewalPane) > 0 && !window.confirm('This will remove recertification requirements from the form. Continue?')) {
                    e.target.checked = false;
                    return;
                }
                removeRenewalRows();
                form?.classList.add('d-none');
            } else {
                form?.classList.remove('d-none');
            }
            updateRenewalActions();
        }
    });

    document.addEventListener('input', function (e) {
        if (e.target.closest('[data-requirement-group-row]') && e.target.name && e.target.name.includes('[label]')) {
            const pane = e.target.closest('[data-requirements-phase]');
            pauseObserver(function () {
                syncRequirementGroupSelects(pane);
            });
        }
        if (e.target.id === 'validity_months') {
            updateRenewalActions();
        }
    });

    if (renewalPane) {
        renewalPane.querySelector('[data-renewal-copy-initial]')?.addEventListener('click', function () {
            pauseObserver(cloneInitialRequirementsToRenewal);
        });
        renewalPane.querySelector('[data-renewal-clear]')?.addEventListener('click', function () {
            if (!window.confirm('Remove all recertification groups and requirements from this form?')) {
                return;
            }
            removeRenewalRows();
        });
    }

    if (certificateForm) {
        certificateForm.addEventListener('project-program-scope:change', function () {
            syncToolSelects(certificateForm);
        });
    }

    observer = new MutationObserver(function (mutations) {
        if (syncing) {
            return;
        }

        const roots = [];
        mutations.forEach(function (mutation) {
            mutation.addedNodes.forEach(function (node) {
                if (node.nodeType !== 1) {
                    return;
                }
                if (node.matches('[data-repeater-row]') || node.querySelector('[data-repeater-row]')) {
                    roots.push(node.matches('[data-repeater-row]') ? node.parentElement : node);
                }
            });
        });

        if (!roots.length) {
            return;
        }

        pauseObserver(function () {
            roots.forEach(syncAll);
        });
    });
    observer.observe(document.body, { childList: true, subtree: true });

    pauseObserver(function () {
        syncAll(document);
    });
})();
</script>
@endonce
