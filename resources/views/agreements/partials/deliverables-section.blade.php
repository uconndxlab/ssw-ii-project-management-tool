@php
    $rawDeliverableRows = old('deliverables');
    $deliverableRows = [];
    $hasDeliverableRows = false;

    if (is_array($rawDeliverableRows)) {
        foreach ($rawDeliverableRows as $key => $row) {
            if (!is_array($row)) {
                continue;
            }

            $rowKey = is_string($key) ? $key : 'row-' . $key;
            $contactFamilyLabel = $contactFamilies->firstWhere('id', $row['contact_family_id'] ?? null)?->name;
            $activityTypeLabel = $activityTypes->firstWhere('id', $row['activity_type_id'] ?? null)?->name;

            $deliverableRows[] = array_merge($row, [
                'row_key' => $rowKey,
                'contact_family_label' => $contactFamilyLabel,
                'activity_type_label' => $activityTypeLabel,
                'assigned_user_names' => $users->whereIn('id', $row['user_ids'] ?? [])->pluck('name')->all(),
            ]);
        }
        $hasDeliverableRows = !empty($deliverableRows);
    } elseif ($agreement?->deliverables) {
        foreach ($agreement->deliverables as $deliverable) {
            $deliverableRows[] = [
                'row_key' => 'existing-' . $deliverable->id,
                'id' => $deliverable->id,
                'activity_type_id' => $deliverable->activity_type_id,
                'contact_family_id' => $deliverable->contact_family_id,
                'required_hours' => $deliverable->required_hours,
                'required_activities' => $deliverable->required_activities,
                'notes' => $deliverable->notes,
                'user_ids' => $deliverable->assignedUsers->pluck('id')->all(),
                'contact_family_label' => $deliverable->contactFamily?->name,
                'activity_type_label' => $deliverable->activityType?->name,
                'assigned_user_names' => $deliverable->assignedUsers->pluck('name')->all(),
            ];
        }
        $hasDeliverableRows = !empty($deliverableRows);
    }

    $editorDefaults = [
        'id' => '',
        'contact_family_id' => '',
        'activity_type_id' => '',
        'required_hours' => '',
        'required_activities' => '',
        'notes' => '',
        'user_ids' => [],
    ];
@endphp

<div class="card mb-4">
    <div class="card-body">
        <div class="d-flex flex-wrap justify-content-between align-items-start gap-3 mb-3">
            <div>
                <h5 class="mb-1">Deliverables</h5>
                <p class="text-muted small mb-0">Use the table to manage rows.</p>
            </div>
        </div>

        <div class="table-responsive mb-3">
            <table class="table table-sm align-middle">
                <thead>
                    <tr>
                        <th>Contact</th>
                        <th>Notes</th>
                        <th>Assignments</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody id="deliverable-table-body">
                    @if($hasDeliverableRows)
                        @foreach($deliverableRows as $row)
                            @include('agreements.partials.deliverable-table-row', ['row' => $row])
                        @endforeach
                    @else
                        <tr class="deliverable-empty-row">
                            <td colspan="4" class="text-center text-muted py-4 small">
                                Click "+ Add Deliverable" to create a deliverable for this agreement.
                            </td>
                        </tr>
                    @endif
                </tbody>
            </table>
        </div>

        <div class="d-flex justify-content-between align-items-center mb-3">
            <div class="form-text mb-0">Rows are staged locally and saved only when the agreement form is submitted.</div>
            <button type="button" class="btn btn-sm btn-outline-primary" id="deliverable-add-button-bottom">
                + Add Deliverable
            </button>
        </div>

        <div id="deliverable-hidden-inputs">
            @foreach($deliverableRows as $row)
                @php
                    $rowKey = $row['row_key'];
                @endphp
                <div data-deliverable-hidden-row="{{ $rowKey }}">
                    @if(!empty($row['id']))
                        <input type="hidden" name="deliverables[{{ $rowKey }}][id]" value="{{ $row['id'] }}">
                    @endif
                    <input type="hidden" name="deliverables[{{ $rowKey }}][_delete]" value="{{ !empty($row['_delete']) ? 1 : 0 }}">
                    <input type="hidden" name="deliverables[{{ $rowKey }}][contact_family_id]" value="{{ $row['contact_family_id'] ?? '' }}">
                    <input type="hidden" name="deliverables[{{ $rowKey }}][activity_type_id]" value="{{ $row['activity_type_id'] ?? '' }}">
                    <input type="hidden" name="deliverables[{{ $rowKey }}][required_hours]" value="{{ $row['required_hours'] ?? '' }}">
                    <input type="hidden" name="deliverables[{{ $rowKey }}][required_activities]" value="{{ $row['required_activities'] ?? '' }}">
                    <input type="hidden" name="deliverables[{{ $rowKey }}][notes]" value="{{ $row['notes'] ?? '' }}">
                    @foreach($row['user_ids'] ?? [] as $userId)
                        <input type="hidden" name="deliverables[{{ $rowKey }}][user_ids][]" value="{{ $userId }}">
                    @endforeach
                </div>
            @endforeach
        </div>

        <div class="modal fade" id="deliverable-editor-modal" tabindex="-1" aria-labelledby="deliverable-editor-modal-label" aria-hidden="true">
            <div class="modal-dialog modal-xl modal-dialog-scrollable">
                <div class="modal-content">
                    <div class="modal-header">
                        <div>
                            <h5 class="modal-title mb-0" id="deliverable-editor-modal-label">Deliverable Form</h5>
                            <div class="text-muted small">Add a new deliverable or edit the selected row, then save it into the table.</div>
                        </div>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <input type="hidden" id="deliverable-editor-key" value="">
                        <div data-deliverable-editor-fields>
                            @include('agreements.partials.deliverable-fields', [
                                'row' => $editorDefaults,
                                'fieldPrefix' => 'deliverable_editor',
                            ])
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-outline-secondary" id="deliverable-clear-button">Clear</button>
                        <button type="button" class="btn btn-primary" id="deliverable-save-button">Save Deliverable</button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

@once
<script>
(function () {
    document.addEventListener('DOMContentLoaded', function () {
        const tableBody = document.getElementById('deliverable-table-body');
        const hiddenInputs = document.getElementById('deliverable-hidden-inputs');
        const addButtons = [
            document.getElementById('deliverable-add-button'),
            document.getElementById('deliverable-add-button-bottom'),
        ].filter(Boolean);
        const saveButton = document.getElementById('deliverable-save-button');
        const clearButton = document.getElementById('deliverable-clear-button');
        const editorKeyInput = document.getElementById('deliverable-editor-key');
        const editorModalEl = document.getElementById('deliverable-editor-modal');
        const editorCard = editorModalEl ? editorModalEl.querySelector('.modal-content') : null;
        const editorFieldset = editorModalEl ? editorModalEl.querySelector('[data-deliverable-editor-fields]') : null;
        if (!tableBody || !hiddenInputs || !saveButton || !clearButton || !editorKeyInput || !editorFieldset) return;

        const userLookup = @json($users->pluck('name', 'id'));
        const contactFamilyLookup = @json($contactFamilies->pluck('name', 'id'));
        const activityTypeLookup = @json($activityTypes->pluck('name', 'id'));
        const userProgramMap = @json($users->mapWithKeys(fn ($user) => [(string) $user->id => $user->programs->pluck('id')->map(fn ($id) => (string) $id)->values()->all()]));
        const contactFamilyProgramMap = @json($contactFamilies->mapWithKeys(fn ($family) => [(string) $family->id => $family->programs->pluck('id')->map(fn ($id) => (string) $id)->values()->all()]));
        const activityTypeProgramMap = @json($activityTypes->mapWithKeys(fn ($type) => [(string) $type->id => $type->programs->pluck('id')->map(fn ($id) => (string) $id)->values()->all()]));
        let currentKey = null;
        let nextTempId = 1;
        const rowStore = {};
        const editorModal = window.bootstrap ? bootstrap.Modal.getOrCreateInstance(editorModalEl) : null;

        function selectedProgramIds() {
            const picker = document.getElementById('agreement-scope-programs');

            if (!picker) {
                return [];
            }

            return Array.from(picker.querySelectorAll('[data-token-inputs] input')).map(function (input) {
                return String(input.value);
            });
        }

        function isAllowedByPrograms(programIds, allowGlobal, activeProgramIds) {
            const normalizedProgramIds = Array.isArray(programIds) ? programIds.map(String) : [];
            const selectedPrograms = new Set((activeProgramIds || []).map(String));

            if (normalizedProgramIds.length === 0) {
                return allowGlobal;
            }

            if (selectedPrograms.size === 0) {
                return false;
            }

            return normalizedProgramIds.some(function (programId) {
                return selectedPrograms.has(String(programId));
            });
        }

        function initTooltips(scope) {
            if (!window.bootstrap || !bootstrap.Tooltip) {
                return;
            }

            (scope || document).querySelectorAll('[data-bs-toggle="tooltip"]').forEach(function (element) {
                bootstrap.Tooltip.getOrCreateInstance(element);
            });
        }

        function disposeTooltips(scope) {
            if (!window.bootstrap || !bootstrap.Tooltip) {
                return;
            }

            (scope || document).querySelectorAll('[data-bs-toggle="tooltip"]').forEach(function (element) {
                const tooltip = bootstrap.Tooltip.getInstance(element);
                if (tooltip) {
                    tooltip.hide();
                    tooltip.dispose();
                }
            });
        }

        function escapeHtml(value) {
            return String(value ?? '')
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;')
                .replace(/'/g, '&#039;');
        }

        function newRowKey() {
            return 'row-new-' + Date.now() + '-' + (nextTempId++);
        }

        function emptyStateRowMarkup() {
            return `
                <tr class="deliverable-empty-row">
                    <td colspan="4" class="text-center text-muted py-4 small">
                        Click "+ Add Deliverable" to create a deliverable for this agreement.
                    </td>
                </tr>
            `;
        }

        function hasVisibleDeliverableRows() {
            return Array.from(tableBody.querySelectorAll('[data-deliverable-row]')).some(function (row) {
                return window.getComputedStyle(row).display !== 'none';
            });
        }

        function renderEmptyStateIfNeeded() {
            const emptyRow = tableBody.querySelector('.deliverable-empty-row');
            if (hasVisibleDeliverableRows()) {
                if (emptyRow) {
                    emptyRow.remove();
                }
                return;
            }

            if (!emptyRow) {
                tableBody.insertAdjacentHTML('beforeend', emptyStateRowMarkup());
            }
        }

        function collectEditorData() {
            const fieldPrefix = 'deliverable_editor';
            const formData = new FormData(editorCard.closest('form'));

            const userIds = [];
            editorFieldset.querySelectorAll(`input[name="${fieldPrefix}[user_ids][]"]:checked`).forEach(function (checkbox) {
                userIds.push(checkbox.value);
            });

            return {
                id: editorCard.querySelector('[name="deliverable_editor[id]"]')?.value || '',
                _delete: '0',
                contact_family_id: formData.get(`${fieldPrefix}[contact_family_id]`) || '',
                activity_type_id: formData.get(`${fieldPrefix}[activity_type_id]`) || '',
                required_hours: formData.get(`${fieldPrefix}[required_hours]`) || '',
                required_activities: formData.get(`${fieldPrefix}[required_activities]`) || '',
                notes: formData.get(`${fieldPrefix}[notes]`) || '',
                user_ids: userIds,
            };
        }

        function setEditorData(rowKey, rowData) {
            currentKey = rowKey;
            editorKeyInput.value = rowKey || '';

            const fieldPrefix = 'deliverable_editor';
            editorCard.querySelector(`[name="${fieldPrefix}[id]"]`)?.remove();

            const idInput = document.createElement('input');
            idInput.type = 'hidden';
            idInput.name = `${fieldPrefix}[id]`;
            idInput.value = rowData.id || '';
            editorCard.querySelector('[data-deliverable-editor-fields]')?.prepend(idInput);

            editorCard.querySelector(`[name="${fieldPrefix}[contact_family_id]"]`).value = rowData.contact_family_id || '';
            editorCard.querySelector(`[name="${fieldPrefix}[activity_type_id]"]`).value = rowData.activity_type_id || '';
            editorCard.querySelector(`[name="${fieldPrefix}[required_hours]"]`).value = rowData.required_hours || '';
            editorCard.querySelector(`[name="${fieldPrefix}[required_activities]"]`).value = rowData.required_activities || '';
            editorCard.querySelector(`[name="${fieldPrefix}[notes]"]`).value = rowData.notes || '';

            editorFieldset.querySelectorAll(`input[name="${fieldPrefix}[user_ids][]"]`).forEach(function (checkbox) {
                checkbox.checked = Array.isArray(rowData.user_ids) && rowData.user_ids.map(String).includes(String(checkbox.value));
            });

            syncActivityTypeOptions();

            if (editorModal) {
                editorModal.show();
            }
        }

        function clearEditor(showModal = true) {
            currentKey = null;
            editorKeyInput.value = '';
            editorCard.querySelectorAll('[name^="deliverable_editor["]').forEach(function (input) {
                if (input.type === 'checkbox') {
                    input.checked = false;
                } else if (input.name.endsWith('[contact_family_id]') || input.name.endsWith('[activity_type_id]') || input.name.endsWith('[required_hours]') || input.name.endsWith('[required_activities]') || input.name.endsWith('[notes]')) {
                    input.value = '';
                }
            });
            editorCard.querySelector('[name="deliverable_editor[id]"]')?.remove();
            syncActivityTypeOptions();

            if (showModal && editorModal) {
                editorModal.show();
            }
        }

        function syncActivityTypeOptions() {
            const contactFamilySelect = editorCard.querySelector('[name="deliverable_editor[contact_family_id]"]');
            const activityTypeSelect = editorCard.querySelector('[name="deliverable_editor[activity_type_id]"]');
            if (!contactFamilySelect || !activityTypeSelect) return;

            const activeProgramIds = selectedProgramIds();
            const currentValue = activityTypeSelect.value;

            Array.from(contactFamilySelect.options).forEach(function (option) {
                if (!option.value) {
                    option.hidden = false;
                    option.disabled = false;
                    return;
                }

                const visible = isAllowedByPrograms(contactFamilyProgramMap[String(option.value)] || [], true, activeProgramIds);
                option.hidden = !visible;
                option.disabled = !visible;
            });

            if (contactFamilySelect.value) {
                const selectedFamilyOption = contactFamilySelect.querySelector(`option[value="${CSS.escape(contactFamilySelect.value)}"]`);
                if (!selectedFamilyOption || selectedFamilyOption.hidden) {
                    contactFamilySelect.value = '';
                }
            }

            Array.from(activityTypeSelect.options).forEach(function (option) {
                if (!option.value) {
                    option.hidden = false;
                    option.disabled = false;
                    return;
                }
                const matchesFamily = !contactFamilySelect.value || option.dataset.contactFamilyId === contactFamilySelect.value;
                const matchesPrograms = isAllowedByPrograms(activityTypeProgramMap[String(option.value)] || [], true, activeProgramIds);
                const matches = matchesFamily && matchesPrograms;
                option.hidden = !matches;
                option.disabled = !matches;
            });

            if (currentValue) {
                const selectedOption = activityTypeSelect.querySelector(`option[value="${CSS.escape(currentValue)}"]`);
                if (!selectedOption || selectedOption.hidden) {
                    activityTypeSelect.value = '';
                }
            }

            editorFieldset.querySelectorAll('input[name="deliverable_editor[user_ids][]"]').forEach(function (checkbox) {
                const visible = isAllowedByPrograms(userProgramMap[String(checkbox.value)] || [], false, activeProgramIds);
                checkbox.disabled = !visible;
                checkbox.checked = visible ? checkbox.checked : false;
                const wrapper = checkbox.closest('.form-check');
                if (wrapper) {
                    wrapper.classList.toggle('d-none', !visible);
                }
            });
        }

        function readHiddenRowData(hiddenRow) {
            const rowKey = hiddenRow.dataset.deliverableHiddenRow;

            function findValue(field) {
                return hiddenRow.querySelector(`input[name="deliverables[${CSS.escape(rowKey)}][${field}]"]`)?.value || '';
            }

            return {
                rowKey: rowKey,
                id: findValue('id'),
                _delete: findValue('_delete') || '0',
                contact_family_id: findValue('contact_family_id'),
                activity_type_id: findValue('activity_type_id'),
                required_hours: findValue('required_hours'),
                required_activities: findValue('required_activities'),
                notes: findValue('notes'),
                user_ids: Array.from(hiddenRow.querySelectorAll(`input[name="deliverables[${CSS.escape(rowKey)}][user_ids][]"]`)).map(function (input) {
                    return input.value;
                }),
            };
        }

        function syncStoredRowsToPrograms() {
            const activeProgramIds = selectedProgramIds();

            Array.from(hiddenInputs.querySelectorAll('[data-deliverable-hidden-row]')).forEach(function (hiddenRow) {
                const rowData = readHiddenRowData(hiddenRow);
                const rowKey = rowData.rowKey;

                if (!rowKey || rowData._delete === '1') {
                    return;
                }

                const familyAllowed = !rowData.contact_family_id || isAllowedByPrograms(contactFamilyProgramMap[String(rowData.contact_family_id)] || [], true, activeProgramIds);
                const typeAllowed = !rowData.activity_type_id || isAllowedByPrograms(activityTypeProgramMap[String(rowData.activity_type_id)] || [], true, activeProgramIds);

                if (!familyAllowed || !typeAllowed) {
                    if (rowData.id) {
                        markRowDeleted(rowKey);
                    } else {
                        deleteRow(rowKey);
                    }

                    delete rowStore[rowKey];
                    return;
                }

                const filteredUserIds = (rowData.user_ids || []).filter(function (userId) {
                    return isAllowedByPrograms(userProgramMap[String(userId)] || [], false, activeProgramIds);
                });

                if (filteredUserIds.length !== (rowData.user_ids || []).length) {
                    rowData.user_ids = filteredUserIds;
                    syncTableRow(rowKey, rowData);
                    syncHiddenRow(rowKey, rowData);
                    return;
                }

                rowStore[rowKey] = rowData;
            });
        }

        function rowMarkup(rowKey, rowData) {
            const assignedNames = (rowData.user_ids || []).map(function (id) {
                return userLookup[id] || id;
            });

            const contactFamilyLabel = contactFamilyLookup[rowData.contact_family_id] || '—';
            const activityTypeLabel = activityTypeLookup[rowData.activity_type_id] || 'Any activity type';
            const badges = assignedNames.length
                ? assignedNames.map(function (name) { return `<span class="badge bg-secondary me-1 mb-1">${escapeHtml(name)}</span>`; }).join('')
                : '<span class="text-muted small">—</span>';

            return `
                <tr data-deliverable-row data-row-key="${escapeHtml(rowKey)}">
                    <td>
                        <div class="fw-semibold">${escapeHtml(contactFamilyLabel)}</div>
                        <div class="text-muted small">${escapeHtml(activityTypeLabel)}</div>
                    </td>
                    <td class="text-wrap" style="min-width: 320px; max-width: 100%; white-space: normal;">${rowData.notes ? escapeHtml(rowData.notes) : '—'}</td>
                    <td>${badges}</td>
                    <td class="text-end text-nowrap">
                        <div class="btn-group btn-group-sm" role="group" aria-label="Deliverable actions">
                            <button type="button" class="btn btn-outline-secondary" data-deliverable-edit data-bs-toggle="tooltip" data-bs-title="Edit deliverable" aria-label="Edit deliverable">
                                <i class="bi bi-pencil-square"></i>
                            </button>
                            <button type="button" class="btn btn-outline-secondary" data-deliverable-duplicate data-bs-toggle="tooltip" data-bs-title="Duplicate deliverable" aria-label="Duplicate deliverable">
                                <i class="bi bi-files"></i>
                            </button>
                            <button type="button" class="btn btn-outline-danger" data-deliverable-remove data-bs-toggle="tooltip" data-bs-title="Remove deliverable" aria-label="Remove deliverable">
                                <i class="bi bi-trash"></i>
                            </button>
                        </div>
                    </td>
                </tr>
            `;
        }

        function hiddenMarkup(rowKey, rowData) {
            const userInputs = (rowData.user_ids || []).map(function (id) {
                return `<input type="hidden" name="deliverables[${escapeHtml(rowKey)}][user_ids][]" value="${escapeHtml(id)}">`;
            }).join('');

            return `
                <div data-deliverable-hidden-row="${escapeHtml(rowKey)}">
                    ${rowData.id ? `<input type="hidden" name="deliverables[${escapeHtml(rowKey)}][id]" value="${escapeHtml(rowData.id)}">` : ''}
                    <input type="hidden" name="deliverables[${escapeHtml(rowKey)}][_delete]" value="${escapeHtml(rowData._delete || '0')}">
                    <input type="hidden" name="deliverables[${escapeHtml(rowKey)}][contact_family_id]" value="${escapeHtml(rowData.contact_family_id || '')}">
                    <input type="hidden" name="deliverables[${escapeHtml(rowKey)}][activity_type_id]" value="${escapeHtml(rowData.activity_type_id || '')}">
                    <input type="hidden" name="deliverables[${escapeHtml(rowKey)}][required_hours]" value="${escapeHtml(rowData.required_hours || '')}">
                    <input type="hidden" name="deliverables[${escapeHtml(rowKey)}][required_activities]" value="${escapeHtml(rowData.required_activities || '')}">
                    <input type="hidden" name="deliverables[${escapeHtml(rowKey)}][notes]" value="${escapeHtml(rowData.notes || '')}">
                    ${userInputs}
                </div>
            `;
        }

        function syncHiddenRow(rowKey, rowData) {
            const existing = hiddenInputs.querySelector(`[data-deliverable-hidden-row="${CSS.escape(rowKey)}"]`);
            if (existing) {
                existing.outerHTML = hiddenMarkup(rowKey, rowData);
            } else {
                hiddenInputs.insertAdjacentHTML('beforeend', hiddenMarkup(rowKey, rowData));
            }
            rowStore[rowKey] = rowData;
        }

        function syncTableRow(rowKey, rowData) {
            const emptyRow = tableBody.querySelector('.deliverable-empty-row');
            if (emptyRow) {
                emptyRow.remove();
            }

            const existing = tableBody.querySelector(`[data-row-key="${CSS.escape(rowKey)}"]`);
            if (existing) {
                disposeTooltips(existing);
            }
            const markup = rowMarkup(rowKey, rowData);
            if (existing) {
                existing.outerHTML = markup;
            } else {
                tableBody.insertAdjacentHTML('beforeend', markup);
            }
            initTooltips(tableBody);
            rowStore[rowKey] = rowData;
        }

        function deleteRow(rowKey) {
            const row = tableBody.querySelector(`[data-row-key="${CSS.escape(rowKey)}"]`);
            const hidden = hiddenInputs.querySelector(`[data-deliverable-hidden-row="${CSS.escape(rowKey)}"]`);
            if (row) {
                disposeTooltips(row);
            }
            if (row) row.remove();
            if (hidden) hidden.remove();

            renderEmptyStateIfNeeded();
        }

        function markRowDeleted(rowKey) {
            const hidden = hiddenInputs.querySelector(`[data-deliverable-hidden-row="${CSS.escape(rowKey)}"]`);
            if (!hidden) return;
            const deleteInput = hidden.querySelector('input[name$="[_delete]"]');
            if (deleteInput) deleteInput.value = '1';

            const row = tableBody.querySelector(`[data-row-key="${CSS.escape(rowKey)}"]`);
            if (row) {
                disposeTooltips(row);
                row.classList.add('table-active', 'text-muted');
                row.style.display = 'none';
            }

            renderEmptyStateIfNeeded();
        }

        function bindRows() {
            tableBody.querySelectorAll('[data-deliverable-row]').forEach(function (row) {
                const rowKey = row.dataset.rowKey;
                if (rowKey && !rowStore[rowKey] && row.dataset.deliverableRowData) {
                    try {
                        rowStore[rowKey] = JSON.parse(row.dataset.deliverableRowData);
                    } catch (error) {
                        rowStore[rowKey] = {};
                    }
                }
            });

            initTooltips(tableBody);
        }

        tableBody.addEventListener('click', function (event) {
            const actionButton = event.target.closest('[data-deliverable-edit], [data-deliverable-duplicate], [data-deliverable-remove]');
            if (!actionButton) return;

            const row = actionButton.closest('[data-deliverable-row]');
            if (!row) return;

            const rowKey = row.dataset.rowKey;
            const payload = rowStore[rowKey] || {};

            if (actionButton.matches('[data-deliverable-edit]')) {
                setEditorData(rowKey, payload);
                return;
            }

            if (actionButton.matches('[data-deliverable-duplicate]')) {
                const duplicateKey = newRowKey();
                const duplicate = { ...payload, id: '', _delete: '0' };
                delete duplicate.row_key;
                syncTableRow(duplicateKey, duplicate);
                syncHiddenRow(duplicateKey, duplicate);
                return;
            }

            if (actionButton.matches('[data-deliverable-remove]')) {
                if (payload.id) {
                    markRowDeleted(rowKey);
                    return;
                }

                deleteRow(rowKey);
                if (currentKey === rowKey) {
                    clearEditor(false);
                }
            }
        });

        addButtons.forEach(function (button) {
            button.addEventListener('click', function () {
                const newKey = newRowKey();
                currentKey = newKey;
                clearEditor();
                editorKeyInput.value = newKey;
                editorModal?.show();
            });
        });

        clearButton.addEventListener('click', function () {
            clearEditor();
        });

        saveButton.addEventListener('click', function () {
            const rowData = collectEditorData();
            const rowKey = currentKey || newRowKey();

            if (rowData.id === '' && rowData.contact_family_id === '' && rowData.activity_type_id === '' && rowData.required_hours === '' && rowData.required_activities === '' && rowData.notes === '' && rowData.user_ids.length === 0) {
                return;
            }

            syncTableRow(rowKey, rowData);
            syncHiddenRow(rowKey, rowData);
            setEditorData(rowKey, rowData);
            editorModal?.hide();
        });

        editorCard.querySelector('[name="deliverable_editor[contact_family_id]"]')?.addEventListener('change', syncActivityTypeOptions);

        document.addEventListener('agreement-scope:change', function () {
            syncActivityTypeOptions();
            syncStoredRowsToPrograms();
        });

        bindRows();
        syncActivityTypeOptions();
        syncStoredRowsToPrograms();
    });
})();
</script>
@endonce
