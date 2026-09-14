@props([
    'listId',
    'name',
    'fields' => [],
    'rows' => [],
    'maxRows' => null,
    'sortable' => false,
    'primaryField' => null,
    'primaryLabel' => 'Primary',
    'addButtonText' => 'Add Item',
    'emptyMessage' => 'None added yet.',
])

@php
    $fields = collect($fields)
        ->map(fn ($field) => array_merge([
            'key' => null,
            'label' => null,
            'type' => 'text',
            'placeholder' => '',
            'col' => 'col-md-3',
            'maxlength' => null,
        ], $field))
        ->filter(fn ($field) => filled($field['key']))
        ->values();

    $normalizedRows = collect($rows)
        ->filter(fn ($row) => is_array($row))
        ->map(function ($row, $index) use ($fields, $primaryField) {
            $rowKey = (string) ($row['row_key'] ?? ('row-' . $index));
            $values = [];

            foreach ($fields as $field) {
                $values[$field['key']] = (string) ($row[$field['key']] ?? '');
            }

            return [
                'row_key' => $rowKey,
                'id' => (string) ($row['id'] ?? ''),
                '_delete' => !empty($row['_delete']) ? '1' : '0',
                'is_primary' => ($primaryField && !empty($row[$primaryField])) ? '1' : '0',
                'values' => $values,
            ];
        })
        ->values();

    $visibleRows = $normalizedRows->where('_delete', '0');
    $atMax = $maxRows && $visibleRows->count() >= $maxRows;

    $rowFieldError = fn ($rowKey, $fieldKey) => $errors->first("{$name}.{$rowKey}.{$fieldKey}");

    $generalErrors = collect($errors->get($name))->flatten()->unique()->values();
@endphp

<div id="{{ $listId }}"
     class="inline-record-list"
     data-inline-record-list
     data-name="{{ $name }}"
     data-fields='@json($fields->values())'
     data-max-rows="{{ $maxRows ?? '' }}"
     data-sortable="{{ $sortable ? 'true' : 'false' }}"
     data-primary-field="{{ $primaryField }}">

    <div data-inline-record-rows>
        @foreach($normalizedRows as $row)
            <div class="inline-record-row card mb-2 {{ $row['_delete'] === '1' ? 'd-none' : '' }} {{ $row['is_primary'] === '1' ? 'border-primary bg-primary-subtle' : '' }}"
                 data-inline-record-row="{{ $row['row_key'] }}">
                <div class="card-body py-2 px-3">
                    <div class="d-flex align-items-start gap-2">
                        @if($sortable)
                            <span class="text-muted inline-record-drag-handle mt-2" data-drag-handle aria-hidden="true">
                                <i class="bi bi-grip-vertical"></i>
                            </span>
                        @endif

                        @if($row['id'] !== '')
                            <input type="hidden" name="{{ $name }}[{{ $row['row_key'] }}][id]" value="{{ $row['id'] }}">
                        @endif
                        <input type="hidden" name="{{ $name }}[{{ $row['row_key'] }}][_delete]" value="{{ $row['_delete'] }}" data-inline-record-delete>
                        @if($primaryField)
                            <input type="hidden" name="{{ $name }}[{{ $row['row_key'] }}][{{ $primaryField }}]" value="{{ $row['is_primary'] }}" data-inline-record-primary-input>
                        @endif

                        <div class="row g-2 flex-grow-1">
                            @foreach($fields as $field)
                                @php $fieldError = $rowFieldError($row['row_key'], $field['key']); @endphp
                                <div class="{{ $field['col'] }}">
                                    @if($field['label'])
                                        <label class="form-label small text-muted mb-1">{{ $field['label'] }}</label>
                                    @endif
                                    <input type="{{ $field['type'] }}"
                                           class="form-control form-control-sm @if($fieldError) is-invalid @endif"
                                           name="{{ $name }}[{{ $row['row_key'] }}][{{ $field['key'] }}]"
                                           value="{{ $row['values'][$field['key']] }}"
                                           placeholder="{{ $field['placeholder'] }}"
                                           @if($field['maxlength']) maxlength="{{ $field['maxlength'] }}" @endif
                                           data-inline-record-input="{{ $field['key'] }}">
                                    @if($fieldError)
                                        <div class="invalid-feedback">{{ $fieldError }}</div>
                                    @endif
                                </div>
                            @endforeach
                        </div>

                        <div class="d-flex flex-column align-items-center gap-1 mt-1">
                            @if($primaryField)
                                <button type="button"
                                        class="btn btn-link p-0 inline-record-primary-toggle {{ $row['is_primary'] === '1' ? 'text-primary' : 'text-muted' }}"
                                        data-inline-record-primary-toggle
                                        title="Mark as {{ $primaryLabel }}"
                                        aria-label="Mark as {{ $primaryLabel }}">
                                    <i class="bi {{ $row['is_primary'] === '1' ? 'bi-star-fill' : 'bi-star' }}"></i>
                                </button>
                            @endif
                            <button type="button" class="btn btn-link p-0 text-danger" data-inline-record-remove aria-label="Remove">
                                <i class="bi bi-x-circle"></i>
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        @endforeach
    </div>

    <div class="text-muted small mt-1 {{ $visibleRows->isNotEmpty() ? 'd-none' : '' }}" data-inline-record-empty>
        {{ $emptyMessage }}
    </div>

    <div class="mt-2">
        <button type="button" class="btn btn-outline-primary" data-inline-record-add @if($atMax) disabled @endif>
            + {{ $addButtonText }}
        </button>
        @if($maxRows)
            <div class="form-text small {{ $atMax ? '' : 'd-none' }}" data-inline-record-max-hint>
                Maximum of {{ $maxRows }} reached.
            </div>
        @endif
    </div>

    @if($generalErrors->isNotEmpty())
        @foreach($generalErrors as $message)
            <div class="text-danger small mt-1">{{ $message }}</div>
        @endforeach
    @endif
</div>

@once
<style>
    .inline-record-drag-handle {
        cursor: grab;
    }

    .inline-record-drag-handle:active {
        cursor: grabbing;
    }

    .inline-record-list-ghost {
        opacity: 0.65;
        background-color: rgba(var(--bs-primary-rgb), 0.08);
    }

    .inline-record-primary-toggle:hover {
        color: var(--bs-warning) !important;
    }
</style>
<script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.6/Sortable.min.js"></script>
<script>
(function () {
    function escapeHtml(value) {
        return String(value ?? '')
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#39;');
    }

    function parseJson(value, fallback) {
        try {
            return JSON.parse(value || '');
        } catch (error) {
            return fallback;
        }
    }

    function initializeInlineRecordList(list) {
        if (list.dataset.inlineRecordListReady === 'true') {
            return;
        }

        const rowsContainer = list.querySelector('[data-inline-record-rows]');
        const addButton = list.querySelector('[data-inline-record-add]');
        const emptyState = list.querySelector('[data-inline-record-empty]');
        const maxHint = list.querySelector('[data-inline-record-max-hint]');
        const name = list.dataset.name;
        const fields = parseJson(list.dataset.fields, []);
        const maxRows = list.dataset.maxRows ? parseInt(list.dataset.maxRows, 10) : null;
        const sortable = list.dataset.sortable === 'true';
        const primaryField = list.dataset.primaryField || '';

        if (!rowsContainer || !addButton || !name) {
            return;
        }

        let nextIndex = rowsContainer.querySelectorAll('[data-inline-record-row]').length;

        function visibleRows() {
            return Array.from(rowsContainer.querySelectorAll('[data-inline-record-row]')).filter(function (row) {
                return !row.classList.contains('d-none');
            });
        }

        function syncState() {
            const visibleCount = visibleRows().length;
            emptyState?.classList.toggle('d-none', visibleCount > 0);

            if (maxRows) {
                const reached = visibleCount >= maxRows;
                addButton.disabled = reached;
                maxHint?.classList.toggle('d-none', !reached);
            }
        }

        function fieldMarkup(rowKey, field) {
            const label = field.label ? '<label class="form-label small text-muted mb-1">' + escapeHtml(field.label) + '</label>' : '';
            const maxlength = field.maxlength ? ' maxlength="' + escapeHtml(field.maxlength) + '"' : '';

            return ''
                + '<div class="' + escapeHtml(field.col || 'col-md-3') + '">'
                + label
                + '<input type="' + escapeHtml(field.type || 'text') + '"'
                + ' class="form-control form-control-sm"'
                + ' name="' + escapeHtml(name) + '[' + escapeHtml(rowKey) + '][' + escapeHtml(field.key) + ']"'
                + ' value=""'
                + ' placeholder="' + escapeHtml(field.placeholder || '') + '"'
                + maxlength
                + ' data-inline-record-input="' + escapeHtml(field.key) + '">'
                + '</div>';
        }

        function rowMarkup(rowKey) {
            const handle = sortable
                ? '<span class="text-muted inline-record-drag-handle mt-2" data-drag-handle aria-hidden="true"><i class="bi bi-grip-vertical"></i></span>'
                : '';
            const primaryInput = primaryField
                ? '<input type="hidden" name="' + escapeHtml(name) + '[' + escapeHtml(rowKey) + '][' + escapeHtml(primaryField) + ']" value="0" data-inline-record-primary-input>'
                : '';
            const primaryToggle = primaryField
                ? '<button type="button" class="btn btn-link p-0 inline-record-primary-toggle text-muted" data-inline-record-primary-toggle aria-label="Mark as primary"><i class="bi bi-star"></i></button>'
                : '';

            return ''
                + '<div class="inline-record-row card mb-2" data-inline-record-row="' + escapeHtml(rowKey) + '">'
                + '  <div class="card-body py-2 px-3">'
                + '    <div class="d-flex align-items-start gap-2">'
                + handle
                + '      <input type="hidden" name="' + escapeHtml(name) + '[' + escapeHtml(rowKey) + '][_delete]" value="0" data-inline-record-delete>'
                + primaryInput
                + '      <div class="row g-2 flex-grow-1">'
                + fields.map(function (field) { return fieldMarkup(rowKey, field); }).join('')
                + '      </div>'
                + '      <div class="d-flex flex-column align-items-center gap-1 mt-1">'
                + primaryToggle
                + '        <button type="button" class="btn btn-link p-0 text-danger" data-inline-record-remove aria-label="Remove"><i class="bi bi-x-circle"></i></button>'
                + '      </div>'
                + '    </div>'
                + '  </div>'
                + '</div>';
        }

        function attachSortable() {
            if (!sortable || !window.Sortable || rowsContainer._sortableInstance) {
                return;
            }

            rowsContainer._sortableInstance = Sortable.create(rowsContainer, {
                animation: 150,
                ghostClass: 'inline-record-list-ghost',
                handle: '[data-drag-handle]',
            });
        }

        function addRow() {
            if (maxRows && visibleRows().length >= maxRows) {
                return;
            }

            const rowKey = 'row-new-' + Date.now() + '-' + (nextIndex++);
            rowsContainer.insertAdjacentHTML('beforeend', rowMarkup(rowKey));
            const row = rowsContainer.querySelector('[data-inline-record-row="' + CSS.escape(rowKey) + '"]');
            row?.querySelector('[data-inline-record-input]')?.focus();
            attachSortable();
            syncState();
        }

        function setPrimary(targetRow) {
            rowsContainer.querySelectorAll('[data-inline-record-row]').forEach(function (row) {
                const input = row.querySelector('[data-inline-record-primary-input]');
                const toggle = row.querySelector('[data-inline-record-primary-toggle]');
                const icon = toggle?.querySelector('i');
                const isTarget = row === targetRow;

                if (input) {
                    input.value = isTarget ? '1' : '0';
                }

                row.classList.toggle('border-primary', isTarget);
                row.classList.toggle('bg-primary-subtle', isTarget);
                toggle?.classList.toggle('text-primary', isTarget);
                toggle?.classList.toggle('text-muted', !isTarget);
                icon?.classList.toggle('bi-star-fill', isTarget);
                icon?.classList.toggle('bi-star', !isTarget);
            });

            rowsContainer.prepend(targetRow);
        }

        addButton.addEventListener('click', function () {
            addRow();
        });

        rowsContainer.addEventListener('click', function (event) {
            const removeButton = event.target.closest('[data-inline-record-remove]');
            const primaryToggle = event.target.closest('[data-inline-record-primary-toggle]');

            if (removeButton) {
                const row = removeButton.closest('[data-inline-record-row]');

                if (!row) {
                    return;
                }

                const idInput = row.querySelector('input[name$="[id]"]');
                const deleteInput = row.querySelector('[data-inline-record-delete]');

                if (idInput && idInput.value) {
                    if (deleteInput) {
                        deleteInput.value = '1';
                    }
                    row.classList.add('d-none');
                    row.querySelectorAll('input, textarea, select').forEach(function (field) {
                        if (field !== deleteInput && field !== idInput) {
                            field.disabled = true;
                        }
                    });
                } else {
                    row.remove();
                }

                syncState();

                return;
            }

            if (primaryToggle) {
                const row = primaryToggle.closest('[data-inline-record-row]');

                if (row) {
                    setPrimary(row);
                }
            }
        });

        list.dataset.inlineRecordListReady = 'true';
        attachSortable();
        syncState();
    }

    document.addEventListener('DOMContentLoaded', function () {
        document.querySelectorAll('[data-inline-record-list]').forEach(initializeInlineRecordList);
    });
})();
</script>
@endonce
