@props([
    'fields' => collect(),
    'selectedFieldIds' => [],
    'requiredFieldIds' => [],
    'fieldIdInputName',
    'requiredInputName',
    'pickerId' => null,
])

@php
    $pickerId ??= uniqid('logging-field-assignment-picker-', false);

    $fieldData = collect($fields)
        ->map(function ($field) {
            return [
                'id' => (string) $field->id,
                'name' => (string) $field->name,
                'type_label' => (string) $field->fieldTypeLabel(),
                'description' => (string) ($field->help_text ?? ''),
                'full_width' => (bool) $field->is_full_width,
                'program_ids' => $field->programs->pluck('id')->map(fn ($id) => (string) $id)->values()->all(),
            ];
        })
        ->values()
        ->all();

    $normalizedSelectedFieldIds = collect($selectedFieldIds)
        ->map(fn ($id) => (string) $id)
        ->filter()
        ->unique()
        ->values()
        ->all();

    $normalizedRequiredFieldIds = collect($requiredFieldIds)
        ->map(fn ($id) => (string) $id)
        ->filter()
        ->intersect($normalizedSelectedFieldIds)
        ->values()
        ->all();
@endphp

<div id="{{ $pickerId }}"
     class="logging-field-assignment-picker"
     data-logging-field-assignment-picker
     data-fields='@json($fieldData)'
     data-selected-field-ids='@json($normalizedSelectedFieldIds)'
     data-required-field-ids='@json($normalizedRequiredFieldIds)'
     data-field-id-input-name="{{ $fieldIdInputName }}"
     data-required-input-name="{{ $requiredInputName }}">
    <div class="row g-3">
        <div class="col-lg-6">
            <div class="logging-field-assignment-card h-100">
                <div class="mb-3">
                    <h6 class="mb-1">Available Logging Fields</h6>
                    <small class="text-muted">Click a field to add it.</small>
                </div>
                <div class="list-group" data-available-list></div>
                <div class="text-muted text-center small py-4 d-none" data-available-empty-state>
                    No logging fields are available for the current program scope.
                </div>
            </div>
        </div>
        <div class="col-lg-6 logging-field-assignment-divider-column">
            <div class="logging-field-assignment-card h-100">
                <div class="mb-3">
                    <h6 class="mb-1">Selected Logging Fields</h6>
                    <small class="text-muted">Drag to reorder. This order is used on activity entry and activity details.</small>
                </div>
                <div class="list-group" data-selected-list></div>
                <div class="text-muted text-center small py-4 d-none" data-selected-empty-state>
                    No logging fields selected yet.
                </div>
            </div>
        </div>
    </div>
    <div data-hidden-inputs></div>
</div>

@once
<style>
    .logging-field-assignment-card {
        padding-left: 1rem;
    }

    .logging-field-assignment-divider-column {
        border-left: 1px solid var(--bs-border-color);
    }

    .logging-field-assignment-item {
        cursor: pointer;
    }

    .logging-field-assignment-item:hover {
        background-color: var(--bs-light);
    }

    .logging-field-assignment-item .badge {
        font-weight: 600;
    }

    .logging-field-assignment-drag-handle {
        cursor: grab;
    }

    .logging-field-assignment-drag-handle:active {
        cursor: grabbing;
    }

    .logging-field-assignment-ghost {
        opacity: 0.65;
        background-color: rgba(var(--bs-primary-rgb), 0.08);
    }
</style>
<script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.6/Sortable.min.js"></script>
<script>
(function () {
    function parseJson(value, fallback) {
        try {
            return JSON.parse(value || '');
        } catch (error) {
            return fallback;
        }
    }

    function escapeHtml(value) {
        return String(value ?? '')
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#39;');
    }

    function scopeSectionFor(root) {
        return root.closest('form')?.querySelector('[data-project-program-scope]') || null;
    }

    function selectedHiddenValues(scopeSection, name) {
        return Array.from(scopeSection?.querySelectorAll('input[type="hidden"][name="' + name + '[]"]') || []).map(function (input) {
            return String(input.value);
        });
    }

    function initialEffectiveProgramIds(root) {
        const scopeSection = scopeSectionFor(root);

        if (!scopeSection) {
            return [];
        }

        const selectedScopeMode = scopeSection.querySelector('input[name="program_scope_mode"]:checked')?.value || 'all';

        if (selectedScopeMode !== 'specific') {
            return [];
        }

        return selectedHiddenValues(scopeSection, 'program_ids');
    }

    function fieldIsVisible(field, effectiveProgramIds) {
        if (!Array.isArray(effectiveProgramIds) || effectiveProgramIds.length === 0) {
            return true;
        }

        if (!Array.isArray(field.program_ids) || field.program_ids.length === 0) {
            return true;
        }

        return field.program_ids.some(function (programId) {
            return effectiveProgramIds.includes(String(programId));
        });
    }

    function syncHiddenInputs(root) {
        const hiddenInputs = root.querySelector('[data-hidden-inputs]');
        const fieldIdInputName = root.dataset.fieldIdInputName;
        const requiredInputName = root.dataset.requiredInputName;

        hiddenInputs.replaceChildren();

        root._pickerState.selectedIds.forEach(function (fieldId) {
            const selectedInput = document.createElement('input');
            selectedInput.type = 'hidden';
            selectedInput.name = fieldIdInputName + '[]';
            selectedInput.value = fieldId;
            hiddenInputs.appendChild(selectedInput);

            if (root._pickerState.requiredIds.has(fieldId)) {
                const requiredInput = document.createElement('input');
                requiredInput.type = 'hidden';
                requiredInput.name = requiredInputName + '[]';
                requiredInput.value = fieldId;
                hiddenInputs.appendChild(requiredInput);
            }
        });
    }

    function fieldBadges(field) {
        const badges = [
            '<span class="badge text-bg-light border">Type: ' + escapeHtml(field.type_label) + '</span>'
        ];

        if (field.full_width) {
            badges.push('<span class="badge text-bg-secondary">Full width</span>');
        }

        return badges.join(' ');
    }

    function availableItemMarkup(field) {
        return ''
            + '<div class="list-group-item logging-field-assignment-item" data-available-item data-field-id="' + escapeHtml(field.id) + '">'
            + '  <div class="d-flex justify-content-between align-items-start gap-3">'
            + '    <div class="d-flex align-items-start gap-2 flex-grow-1 min-w-0">'
            + '      <button type="button" class="btn btn-link p-0 mt-1 text-primary" data-action="select" aria-label="Add field">'
            + '        <i class="bi bi-plus-circle"></i>'
            + '      </button>'
            + '      <div class="min-w-0">'
            + '        <div class="d-flex flex-wrap align-items-center gap-2 mb-1">'
            + '          <div class="fw-semibold">' + escapeHtml(field.name) + '</div>'
            + fieldBadges(field)
            + '        </div>'
            + '        <div class="small text-muted">' + escapeHtml(field.description || 'No description provided.') + '</div>'
            + '      </div>'
            + '    </div>'
            + '    <div class="form-check form-switch m-0">'
            + '      <input class="form-check-input" type="checkbox" role="switch" data-action="toggle-required-available" aria-label="Mark required and add field">'
            + '      <label class="form-check-label small">Required</label>'
            + '    </div>'
            + '  </div>'
            + '</div>';
    }

    function selectedItemMarkup(field, required) {
        return ''
            + '<div class="list-group-item" data-selected-item data-field-id="' + escapeHtml(field.id) + '">'
            + '  <div class="d-flex justify-content-between align-items-start gap-3">'
            + '    <div class="d-flex align-items-start gap-2 flex-grow-1 min-w-0">'
            + '      <span class="text-muted logging-field-assignment-drag-handle mt-1" data-drag-handle aria-hidden="true">'
            + '        <i class="bi bi-grip-vertical"></i>'
            + '      </span>'
            + '      <div class="min-w-0">'
            + '        <div class="d-flex flex-wrap align-items-center gap-2 mb-1">'
            + '          <div class="fw-semibold">' + escapeHtml(field.name) + '</div>'
            + fieldBadges(field)
            + '        </div>'
            + '        <div class="small text-muted">' + escapeHtml(field.description || 'No description provided.') + '</div>'
            + '      </div>'
            + '    </div>'
            + '    <div class="d-flex align-items-center gap-3">'
            + '      <div class="form-check form-switch m-0">'
            + '        <input class="form-check-input" type="checkbox" role="switch" data-action="toggle-required-selected" ' + (required ? 'checked' : '') + ' aria-label="Toggle required">'
            + '        <label class="form-check-label small">Required</label>'
            + '      </div>'
            + '      <button type="button" class="btn btn-link p-0 text-danger" data-action="remove" aria-label="Remove field">'
            + '        <i class="bi bi-x-circle"></i>'
            + '      </button>'
            + '    </div>'
            + '  </div>'
            + '</div>';
    }

    function visibleFields(root) {
        return root._pickerState.fields.filter(function (field) {
            return fieldIsVisible(field, root._pickerState.effectiveProgramIds);
        });
    }

    function pruneHiddenSelections(root) {
        const visibleIds = new Set(visibleFields(root).map(function (field) {
            return String(field.id);
        }));

        root._pickerState.selectedIds = root._pickerState.selectedIds.filter(function (fieldId) {
            return visibleIds.has(String(fieldId));
        });

        root._pickerState.requiredIds = new Set(Array.from(root._pickerState.requiredIds).filter(function (fieldId) {
            return visibleIds.has(String(fieldId)) && root._pickerState.selectedIds.includes(String(fieldId));
        }));
    }

    function render(root) {
        pruneHiddenSelections(root);

        const availableList = root.querySelector('[data-available-list]');
        const selectedList = root.querySelector('[data-selected-list]');
        const availableEmptyState = root.querySelector('[data-available-empty-state]');
        const selectedEmptyState = root.querySelector('[data-selected-empty-state]');
        const fieldsById = new Map(root._pickerState.fields.map(function (field) {
            return [String(field.id), field];
        }));
        const selectedIdSet = new Set(root._pickerState.selectedIds);
        const visible = visibleFields(root);
        const available = visible.filter(function (field) {
            return !selectedIdSet.has(String(field.id));
        });
        const selected = root._pickerState.selectedIds.map(function (fieldId) {
            return fieldsById.get(String(fieldId)) || null;
        }).filter(function (field) {
            return !!field && fieldIsVisible(field, root._pickerState.effectiveProgramIds);
        });

        availableList.innerHTML = available.map(availableItemMarkup).join('');
        selectedList.innerHTML = selected.map(function (field) {
            return selectedItemMarkup(field, root._pickerState.requiredIds.has(String(field.id)));
        }).join('');

        availableEmptyState.classList.toggle('d-none', available.length !== 0);
        selectedEmptyState.classList.toggle('d-none', selected.length !== 0);

        if (root._sortable) {
            root._sortable.destroy();
            root._sortable = null;
        }

        if (selected.length > 0 && window.Sortable) {
            root._sortable = Sortable.create(selectedList, {
                animation: 150,
                ghostClass: 'logging-field-assignment-ghost',
                handle: '[data-drag-handle]',
                onEnd: function () {
                    root._pickerState.selectedIds = Array.from(selectedList.querySelectorAll('[data-selected-item]')).map(function (item) {
                        return String(item.dataset.fieldId);
                    });
                    syncHiddenInputs(root);
                },
            });
        }

        syncHiddenInputs(root);
    }

    function addField(root, fieldId, required) {
        const normalizedId = String(fieldId);

        if (!root._pickerState.selectedIds.includes(normalizedId)) {
            root._pickerState.selectedIds.push(normalizedId);
        }

        if (required) {
            root._pickerState.requiredIds.add(normalizedId);
        }

        render(root);
    }

    function removeField(root, fieldId) {
        const normalizedId = String(fieldId);

        root._pickerState.selectedIds = root._pickerState.selectedIds.filter(function (selectedId) {
            return selectedId !== normalizedId;
        });
        root._pickerState.requiredIds.delete(normalizedId);

        render(root);
    }

    function initializePicker(root) {
        if (!root || root.dataset.loggingFieldAssignmentInitialized === 'true') {
            return;
        }

        root._pickerState = {
            fields: parseJson(root.dataset.fields, []).map(function (field) {
                return Object.assign({}, field, {
                    id: String(field.id),
                    program_ids: Array.isArray(field.program_ids) ? field.program_ids.map(String) : [],
                });
            }),
            selectedIds: parseJson(root.dataset.selectedFieldIds, []).map(String),
            requiredIds: new Set(parseJson(root.dataset.requiredFieldIds, []).map(String)),
            effectiveProgramIds: initialEffectiveProgramIds(root).map(String),
        };

        root.addEventListener('click', function (event) {
            const actionTarget = event.target.closest('[data-action]');
            const availableItem = event.target.closest('[data-available-item]');
            const selectedItem = event.target.closest('[data-selected-item]');

            if (actionTarget) {
                const item = actionTarget.closest('[data-field-id]');
                const fieldId = item?.dataset.fieldId;

                if (!fieldId) {
                    return;
                }

                if (actionTarget.dataset.action === 'select') {
                    addField(root, fieldId, false);
                }

                if (actionTarget.dataset.action === 'remove') {
                    removeField(root, fieldId);
                }

                return;
            }

            if (availableItem && !event.target.closest('.form-check')) {
                addField(root, availableItem.dataset.fieldId, false);
            }

            if (selectedItem && event.target.closest('.form-check')) {
                return;
            }
        });

        root.addEventListener('change', function (event) {
            const toggle = event.target.closest('[data-action]');
            const item = event.target.closest('[data-field-id]');
            const fieldId = item?.dataset.fieldId;

            if (!toggle || !fieldId) {
                return;
            }

            if (toggle.dataset.action === 'toggle-required-available' && event.target.checked) {
                addField(root, fieldId, true);
                return;
            }

            if (toggle.dataset.action === 'toggle-required-selected') {
                if (event.target.checked) {
                    root._pickerState.requiredIds.add(String(fieldId));
                } else {
                    root._pickerState.requiredIds.delete(String(fieldId));
                }

                syncHiddenInputs(root);
            }
        });

        document.addEventListener('project-program-scope:change', function (event) {
            if (!root.closest('form')?.contains(event.target)) {
                return;
            }

            root._pickerState.effectiveProgramIds = Array.isArray(event.detail?.effectiveProgramIds)
                ? event.detail.effectiveProgramIds.map(String)
                : [];
            render(root);
        });

        root.dataset.loggingFieldAssignmentInitialized = 'true';
        render(root);
    }

    document.addEventListener('DOMContentLoaded', function () {
        document.querySelectorAll('[data-logging-field-assignment-picker]').forEach(initializePicker);
    });
})();
</script>
@endonce
