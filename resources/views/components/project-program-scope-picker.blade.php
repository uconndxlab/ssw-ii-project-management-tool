@props([
    'scopeId',
    'projects' => collect(),
    'selectedProjectIds' => [],
    'selectedProgramIds' => [],
    'projectFieldName' => 'project_ids[]',
    'programFieldName' => 'program_ids[]',
    'projectErrorKey' => 'project_ids',
    'programErrorKey' => 'program_ids',
    'projectLabel' => 'Projects',
    'programLabel' => 'Programs',
    'projectPlaceholder' => 'Search projects...',
    'programPlaceholder' => 'Search programs...',
    'projectDisabled' => false,
    'projectDisabledPlaceholder' => 'Select the required upstream filters first...',
    'disabledPlaceholder' => 'Select at least one project first...',
    'projectHelpText' => 'Filters the program list. Not saved.',
    'programHelpText' => 'Saved when Specific is selected.',
    'projectHeight' => '220px',
    'programHeight' => '220px',
    'projectEmptySelectionLabel' => null,
    'programEmptySelectionLabel' => null,
    'expandEmptyPrograms' => true,
    'showScopeModeSelector' => false,
    'selectedScopeMode' => null,
    'defaultScopeMode' => 'specific',
    'scopeModeOptions' => ['all' => 'All', 'specific' => 'Specific', 'none' => 'None'],
    'scopeModeFieldName' => 'program_scope_mode',
    'scopeModeErrorKey' => 'program_scope_mode',
    'scopeModeLabel' => 'Program Scope',
    'scopeModeHelpText' => 'All programs, only selected programs, or none.',
])

@php
    use App\Support\EntityBadge;
    use Illuminate\Support\Str;

    $scopeModeOptions = collect($scopeModeOptions)->mapWithKeys(function ($label, $value) {
        return [(string) $value => $label];
    })->all();
    $allowedScopeModes = array_keys($scopeModeOptions);
    $defaultProjectEmptySelectionLabel = $projectEmptySelectionLabel ?? '';
    $defaultProgramEmptySelectionLabel = $programEmptySelectionLabel ?? '';
    $projectContextBadgeClass = EntityBadge::relationClasses('project');
    $projectLabelIsRequired = Str::endsWith($projectLabel, ' *');
    $programLabelIsRequired = Str::endsWith($programLabel, ' *');
    $projectLabelText = $projectLabelIsRequired ? Str::beforeLast($projectLabel, ' *') : $projectLabel;
    $programLabelText = $programLabelIsRequired ? Str::beforeLast($programLabel, ' *') : $programLabel;
@endphp

@php
    $scopePicker = App\Support\ProjectProgramScope::scopePickerViewData(
        collect($projects ?? []),
        $selectedProjectIds,
        $selectedProgramIds,
        $scopeId,
        $projectContextBadgeClass,
    );
@endphp

@php
    $selectedScopeMode = old($scopeModeFieldName, $selectedScopeMode ?? $defaultScopeMode);
    if (!in_array((string) $selectedScopeMode, $allowedScopeModes, true)) {
        $selectedScopeMode = in_array('specific', $allowedScopeModes, true)
            ? 'specific'
            : ($allowedScopeModes[0] ?? $defaultScopeMode);
    }
    $projectEmptySelectionLabel = $showScopeModeSelector && $selectedScopeMode === 'specific'
        ? ''
        : $defaultProjectEmptySelectionLabel;
    $programEmptySelectionLabel = $showScopeModeSelector && $selectedScopeMode === 'specific'
        ? ''
        : $defaultProgramEmptySelectionLabel;
@endphp

<div data-project-program-scope
     data-scope-id="{{ $scopeId }}"
     data-program-field-name="{{ $programFieldName }}"
     data-scope-mode-field-name="{{ $scopeModeFieldName }}"
     data-scope-mode-enabled="{{ $showScopeModeSelector ? 'true' : 'false' }}"
     data-default-scope-mode="{{ $defaultScopeMode }}"
     data-expand-empty-programs="{{ $expandEmptyPrograms ? 'true' : 'false' }}"
    data-project-empty-selection-label="{{ $defaultProjectEmptySelectionLabel }}"
    data-program-empty-selection-label="{{ $defaultProgramEmptySelectionLabel }}"
     data-project-program-map='@json($scopePicker['projectProgramMap'])'
     data-program-project-ids-map='@json($scopePicker['programProjectIdsMap'])'
        data-project-names-map='@json($scopePicker['projectNamesMap'])'
        data-project-disabled-placeholder="{{ $projectDisabledPlaceholder }}">
    @if($showScopeModeSelector)
        <div class="mb-3">
            <label class="form-label">{{ $scopeModeLabel }}</label>
            <div class="d-flex flex-wrap gap-3">
                @foreach ($scopeModeOptions as $value => $label)
                    <div class="form-check">
                        <input class="form-check-input"
                               type="radio"
                               id="{{ $scopeId }}-scope-mode-{{ $value }}"
                               name="{{ $scopeModeFieldName }}"
                               value="{{ $value }}"
                               {{ (string) $selectedScopeMode === (string) $value ? 'checked' : '' }}>
                        <label class="form-check-label" for="{{ $scopeId }}-scope-mode-{{ $value }}">{{ $label }}</label>
                    </div>
                @endforeach
            </div>
            @if($scopeModeHelpText)
                <div class="form-text">{{ $scopeModeHelpText }}</div>
            @endif
            @error($scopeModeErrorKey)
                <div class="text-danger small mt-1">{{ $message }}</div>
            @enderror
        </div>
    @endif

    <div class="row g-3" data-scope-picker-fields>
        <div class="col-md-6">
            <label class="form-label{{ $projectLabelIsRequired ? ' required-label' : '' }}">{{ $projectLabelText }}</label>
            <x-token-picker
                :picker-id="$scopePicker['projectPickerId']"
                :name="$projectFieldName"
                :items="$scopePicker['scopeProjects']"
                :selected-ids="$scopePicker['selectedProjectIds']"
                :placeholder="$projectPlaceholder"
                :disabled-placeholder="$projectDisabledPlaceholder"
                :disabled="$projectDisabled"
                :height="$projectHeight"
                :empty-selection-label="$projectEmptySelectionLabel ?? ''"
                entity="project"
            />
            @if($projectHelpText)
                <div class="form-text">{{ $projectHelpText }}</div>
            @endif
            @error($projectErrorKey)
                <div class="text-danger small mt-1">{{ $message }}</div>
            @enderror
        </div>

        <div class="col-md-6">
            <label class="form-label{{ $programLabelIsRequired ? ' required-label' : '' }}">{{ $programLabelText }}</label>
            <x-token-picker
                :picker-id="$scopePicker['programPickerId']"
                :name="$programFieldName"
                :options="$scopePicker['programOptions']"
                :selected-ids="$scopePicker['selectedProgramIds']"
                :placeholder="$programPlaceholder"
                :disabled-placeholder="$disabledPlaceholder"
                :disabled="empty($scopePicker['selectedProjectIds'])"
                :height="$programHeight"
                :empty-selection-label="$programEmptySelectionLabel ?? ''"
                entity="program"
            />
            @if($programHelpText)
                <div class="form-text">{{ $programHelpText }}</div>
            @endif
            @error($programErrorKey)
                <div class="text-danger small mt-1">{{ $message }}</div>
            @enderror
        </div>
    </div>
    <div data-effective-program-inputs></div>
</div>

@once
<script>
(function () {
    function parseJson(value, fallback) {
        try {
            return JSON.parse(value || '');
        } catch (error) {
            return fallback;
        }
    }

    function selectedIds(picker) {
        return Array.from(picker.querySelectorAll('[data-token-inputs] input')).map(function (input) {
            return String(input.value);
        });
    }

    function initializeProjectProgramScope(section) {
        if (section.dataset.projectProgramScopeInitialized === 'true') {
            return;
        }

        const scopeId = section.dataset.scopeId;
        const projectPicker = section.querySelector('#' + scopeId + '-projects');
        const programPicker = section.querySelector('#' + scopeId + '-programs');

        if (!projectPicker || !programPicker) {
            return;
        }

        const projectProgramMap = parseJson(section.dataset.projectProgramMap, {});
        const programProjectIdsMap = parseJson(section.dataset.programProjectIdsMap, {});
        const projectNamesMap = parseJson(section.dataset.projectNamesMap, {});
        const defaultProjectDisabledPlaceholder = section.dataset.projectDisabledPlaceholder || 'Select the required upstream filters first...';
        const defaultProjectEmptySelectionLabel = section.dataset.projectEmptySelectionLabel || '';
        const defaultProgramEmptySelectionLabel = section.dataset.programEmptySelectionLabel || '';
        const defaultDisabledPlaceholder = programPicker.dataset.disabledPlaceholder || 'Select at least one project first...';
        const expandEmptyPrograms = section.dataset.expandEmptyPrograms === 'true';
        const scopeModeEnabled = section.dataset.scopeModeEnabled === 'true';
        const defaultScopeMode = section.dataset.defaultScopeMode || 'specific';
        const scopeModeFieldName = section.dataset.scopeModeFieldName || 'program_scope_mode';
        const programFieldName = section.dataset.programFieldName || 'program_ids[]';
        const effectiveProgramInputs = section.querySelector('[data-effective-program-inputs]');
        const scopePickerFields = section.querySelector('[data-scope-picker-fields]');
        const programStorageNotice = section.querySelector('[data-program-storage-notice]');
        let externalAllowedProgramIds = null;
        let forceProjectDisabled = projectPicker.dataset.disabled === 'true';
        let forcedProjectDisabledPlaceholder = defaultProjectDisabledPlaceholder;
        let forceProgramDisabled = false;
        let forcedProgramDisabledPlaceholder = defaultDisabledPlaceholder;

        function selectedScopeMode() {
            if (!scopeModeEnabled) {
                return defaultScopeMode;
            }

            const input = section.querySelector('input[name="' + scopeModeFieldName + '"]:checked');

            return input ? String(input.value) : defaultScopeMode;
        }

        function setPickerInputsDisabled(picker, disabled) {
            picker.querySelectorAll('[data-token-inputs] input').forEach(function (input) {
                input.disabled = disabled;
            });
        }

        function setPickerEmptySelectionLabel(picker, label) {
            picker.dispatchEvent(new CustomEvent('token-picker:set-empty-selection-label', {
                detail: { label: label },
                bubbles: true,
            }));
        }

        function programContextLabels(projectIds) {
            const contexts = {};

            Object.keys(programProjectIdsMap).forEach(function (programId) {
                const linkedProjectIds = Array.isArray(programProjectIdsMap[programId])
                    ? programProjectIdsMap[programId].map(String)
                    : [];
                const names = projectIds
                    .map(String)
                    .filter(function (projectId) {
                        return linkedProjectIds.includes(String(projectId));
                    })
                    .map(function (projectId) {
                        return projectNamesMap[String(projectId)] || '';
                    })
                    .filter(function (name) {
                        return name !== '';
                    });

                contexts[String(programId)] = names;
            });

            return contexts;
        }

        function updateProgramOptionContexts(projectIds) {
            programPicker.dispatchEvent(new CustomEvent('token-picker:update-option-contexts', {
                detail: programContextLabels(projectIds),
                bubbles: true,
            }));
        }

        function effectiveProgramIds(projectIds, programIds) {
            if (scopeModeEnabled && selectedScopeMode() !== 'specific') {
                return [];
            }

            if (programIds.length > 0) {
                return programIds.slice();
            }

            if (scopeModeEnabled || !expandEmptyPrograms) {
                return [];
            }

            const effective = [];

            if (projectIds.length === 0) {
                return effective;
            }

            projectIds.forEach(function (projectId) {
                const ids = Array.isArray(projectProgramMap[projectId]) ? projectProgramMap[projectId] : [];
                ids.forEach(function (programId) {
                    const normalized = String(programId);
                    if (!effective.includes(normalized)) {
                        effective.push(normalized);
                    }
                });
            });

            return externalAllowedProgramIds === null
                ? effective
                : effective.filter(function (programId) {
                    return externalAllowedProgramIds.has(String(programId));
                });
        }

        function updateEffectiveProgramSubmission(projectIds, programIds, effectiveIds) {
            if (!effectiveProgramInputs) {
                return;
            }

            effectiveProgramInputs.replaceChildren();

            if (scopeModeEnabled || !expandEmptyPrograms || programIds.length > 0 || projectIds.length === 0) {
                if (programStorageNotice) {
                    programStorageNotice.textContent = '';
                }
                return;
            }

            effectiveIds.forEach(function (programId) {
                const input = document.createElement('input');
                input.type = 'hidden';
                input.name = programFieldName;
                input.value = programId;
                effectiveProgramInputs.appendChild(input);
            });

            if (programStorageNotice) {
                programStorageNotice.textContent = effectiveIds.length > 0
                    ? ' No programs are individually selected, so all ' + effectiveIds.length + ' programs currently listed by the selected projects will be saved.'
                    : ' The selected projects currently contain no active programs, so no program scope will be saved.';
            }
        }

        function notifyScopeChange() {
            const projectIds = selectedIds(projectPicker);
            const programIds = selectedIds(programPicker);
            const effectiveIds = effectiveProgramIds(projectIds, programIds);

            updateEffectiveProgramSubmission(projectIds, programIds, effectiveIds);

            section.dispatchEvent(new CustomEvent('project-program-scope:change', {
                bubbles: true,
                detail: {
                    scopeMode: selectedScopeMode(),
                    projectIds: projectIds,
                    programIds: programIds,
                    effectiveProgramIds: effectiveIds,
                },
            }));
        }

        function refreshProgramPicker() {
            const scopeMode = selectedScopeMode();
            const projectIds = selectedIds(projectPicker);
            let allowedProgramIds = [];

            projectIds.forEach(function (projectId) {
                const programIds = Array.isArray(projectProgramMap[projectId]) ? projectProgramMap[projectId] : [];
                programIds.forEach(function (programId) {
                    if (!allowedProgramIds.includes(String(programId))) {
                        allowedProgramIds.push(String(programId));
                    }
                });
            });

            if (externalAllowedProgramIds !== null) {
                allowedProgramIds = allowedProgramIds.filter(function (programId) {
                    return externalAllowedProgramIds.has(String(programId));
                });
            }

            const specificMode = scopeMode === 'specific';

            setPickerEmptySelectionLabel(projectPicker, specificMode ? '' : defaultProjectEmptySelectionLabel);
            setPickerEmptySelectionLabel(programPicker, specificMode ? '' : defaultProgramEmptySelectionLabel);

            if (scopePickerFields) {
                scopePickerFields.classList.toggle('d-none', !specificMode);
            }

            setPickerInputsDisabled(projectPicker, !specificMode || forceProjectDisabled);
            setPickerInputsDisabled(programPicker, !specificMode || forceProgramDisabled || projectIds.length === 0);

            programPicker.dispatchEvent(new CustomEvent('token-picker:set-disabled', {
                detail: {
                    disabled: !specificMode || forceProgramDisabled || projectIds.length === 0,
                    placeholder: !specificMode
                        ? (scopeMode === 'all' ? 'All programs selected.' : 'No programs selected.')
                        : (forceProgramDisabled ? forcedProgramDisabledPlaceholder : defaultDisabledPlaceholder),
                },
                bubbles: true,
            }));

            programPicker.dispatchEvent(new CustomEvent('token-picker:restrict', {
                detail: allowedProgramIds,
                bubbles: true,
            }));

            updateProgramOptionContexts(projectIds);
            notifyScopeChange();
        }

        function refreshProjectPicker() {
            const scopeMode = selectedScopeMode();
            const specificMode = scopeMode === 'specific';

            projectPicker.dispatchEvent(new CustomEvent('token-picker:set-disabled', {
                detail: {
                    disabled: !specificMode || forceProjectDisabled,
                    placeholder: !specificMode
                        ? (scopeMode === 'all' ? 'Projects are not needed for all-program scope.' : 'Projects are not needed for no-program scope.')
                        : forcedProjectDisabledPlaceholder,
                },
                bubbles: true,
            }));

            setPickerInputsDisabled(projectPicker, !specificMode || forceProjectDisabled);
        }

        section.addEventListener('project-program-scope:restrict', function (event) {
            const detail = typeof event.detail === 'object' && event.detail !== null ? event.detail : {};
            externalAllowedProgramIds = Array.isArray(detail.programIds)
                ? new Set(detail.programIds.map(function (programId) {
                    return String(programId);
                }))
                : null;
            forceProjectDisabled = !!detail.forceProjectDisabled;
            forcedProjectDisabledPlaceholder = typeof detail.projectDisabledPlaceholder === 'string' && detail.projectDisabledPlaceholder.trim() !== ''
                ? detail.projectDisabledPlaceholder
                : defaultProjectDisabledPlaceholder;
            forceProgramDisabled = !!detail.forceProgramDisabled;
            forcedProgramDisabledPlaceholder = typeof detail.programDisabledPlaceholder === 'string' && detail.programDisabledPlaceholder.trim() !== ''
                ? detail.programDisabledPlaceholder
                : defaultDisabledPlaceholder;
            refreshProjectPicker();
            refreshProgramPicker();
        });

        section.querySelectorAll('input[name="' + scopeModeFieldName + '"]').forEach(function (input) {
            input.addEventListener('change', function () {
                refreshProjectPicker();
                refreshProgramPicker();
            });
        });

        projectPicker.addEventListener('token-picker:change', refreshProgramPicker);
        programPicker.addEventListener('token-picker:change', notifyScopeChange);
        refreshProjectPicker();
        refreshProgramPicker();
        section.dataset.projectProgramScopeInitialized = 'true';
    }

    document.addEventListener('DOMContentLoaded', function () {
        document.querySelectorAll('[data-project-program-scope]').forEach(function (section) {
            initializeProjectProgramScope(section);
        });
    });

    document.body.addEventListener('htmx:afterSwap', function (event) {
        event.target.querySelectorAll('[data-project-program-scope]').forEach(function (section) {
            initializeProjectProgramScope(section);
        });
    });
})();
</script>
@endonce

