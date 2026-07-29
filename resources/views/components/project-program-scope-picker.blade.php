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
    'disabledPlaceholder' => 'Select at least one project first...',
    'projectHelpText' => null,
    'programHelpText' => null,
    'projectHeight' => '260px',
    'programHeight' => '260px',
    'projectEmptySelectionLabel' => null,
    'programEmptySelectionLabel' => null,
    'expandEmptyPrograms' => true,
])

@php
    use App\Support\EntityBadge;

    $projectContextBadgeClass = EntityBadge::relationClasses('project');
@endphp

@php($scopePicker = App\Support\ProjectProgramScope::scopePickerViewData(collect($projects ?? []), $selectedProjectIds, $selectedProgramIds, $scopeId, $projectContextBadgeClass))

<div data-project-program-scope
     data-scope-id="{{ $scopeId }}"
     data-program-field-name="{{ $programFieldName }}"
     data-expand-empty-programs="{{ $expandEmptyPrograms ? 'true' : 'false' }}"
     data-project-program-map='@json($scopePicker['projectProgramMap'])'
     data-program-project-ids-map='@json($scopePicker['programProjectIdsMap'])'
     data-project-names-map='@json($scopePicker['projectNamesMap'])'>
    <div class="row g-3">
        <div class="col-md-6">
            <label class="form-label">{{ $projectLabel }}</label>
            <x-token-picker
                :picker-id="$scopePicker['projectPickerId']"
                :name="$projectFieldName"
                :items="$scopePicker['scopeProjects']"
                :selected-ids="$scopePicker['selectedProjectIds']"
                :placeholder="$projectPlaceholder"
                :height="$projectHeight"
                :empty-selection-label="$projectEmptySelectionLabel ?? ''"
                entity-kind="project"
            />
            @if($projectHelpText)
                <div class="form-text">{{ $projectHelpText }}</div>
            @endif
            @error($projectErrorKey)
                <div class="text-danger small mt-1">{{ $message }}</div>
            @enderror
        </div>

        <div class="col-md-6">
            <label class="form-label">{{ $programLabel }}</label>
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
                entity-kind="program"
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
        const defaultDisabledPlaceholder = programPicker.dataset.disabledPlaceholder || 'Select at least one project first...';
        const expandEmptyPrograms = section.dataset.expandEmptyPrograms === 'true';
        const programFieldName = section.dataset.programFieldName || 'program_ids[]';
        const effectiveProgramInputs = section.querySelector('[data-effective-program-inputs]');
        const programStorageNotice = section.querySelector('[data-program-storage-notice]');
        let externalAllowedProgramIds = null;
        let forceProgramDisabled = false;
        let forcedProgramDisabledPlaceholder = defaultDisabledPlaceholder;

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
            if (programIds.length > 0) {
                return programIds.slice();
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

            if (!expandEmptyPrograms || programIds.length > 0 || projectIds.length === 0) {
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
                    projectIds: projectIds,
                    programIds: programIds,
                    effectiveProgramIds: effectiveIds,
                },
            }));
        }

        function refreshProgramPicker() {
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

            programPicker.dispatchEvent(new CustomEvent('token-picker:set-disabled', {
                detail: {
                    disabled: forceProgramDisabled || projectIds.length === 0,
                    placeholder: forceProgramDisabled ? forcedProgramDisabledPlaceholder : defaultDisabledPlaceholder,
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

        section.addEventListener('project-program-scope:restrict', function (event) {
            const detail = typeof event.detail === 'object' && event.detail !== null ? event.detail : {};
            externalAllowedProgramIds = Array.isArray(detail.programIds)
                ? new Set(detail.programIds.map(function (programId) {
                    return String(programId);
                }))
                : null;
            forceProgramDisabled = !!detail.forceProgramDisabled;
            forcedProgramDisabledPlaceholder = typeof detail.programDisabledPlaceholder === 'string' && detail.programDisabledPlaceholder.trim() !== ''
                ? detail.programDisabledPlaceholder
                : defaultDisabledPlaceholder;
            refreshProgramPicker();
        });

        projectPicker.addEventListener('token-picker:change', refreshProgramPicker);
        programPicker.addEventListener('token-picker:change', notifyScopeChange);
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
