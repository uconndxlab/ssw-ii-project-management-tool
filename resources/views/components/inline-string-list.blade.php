@props([
    'listId',
    'name',
    'rows' => [],
    'label' => null,
    'addButtonText' => 'Add Item',
    'emptyMessage' => 'No items added yet.',
    'suggestions' => [],
    'inputPlaceholder' => 'Enter a value...',
])

@php
    $normalizedRows = collect($rows)
        ->filter(fn ($row) => is_array($row))
        ->map(function ($row, $index) {
            $rowKey = (string) ($row['row_key'] ?? ('row-' . $index));

            return [
                'row_key' => $rowKey,
                'id' => $row['id'] ?? '',
                'value' => (string) ($row['value'] ?? ''),
                '_delete' => !empty($row['_delete']) ? '1' : '0',
            ];
        })
        ->values();

    $suggestions = collect($suggestions)
        ->filter(fn ($value) => filled($value))
        ->map(fn ($value) => (string) $value)
        ->unique()
        ->values();

    $errorMessages = collect($errors->get($name))
        ->flatten()
        ->merge(collect($errors->get($name . '.*.value'))->flatten())
        ->unique()
        ->values();

    $datalistId = $listId . '-suggestions';
@endphp

<div id="{{ $listId }}"
     class="inline-string-list"
     data-inline-string-list
     data-name="{{ $name }}"
     data-input-placeholder="{{ $inputPlaceholder }}"
     data-empty-message="{{ $emptyMessage }}"
     data-datalist-id="{{ $datalistId }}">
    @if($label)
        <label class="form-label">{{ $label }}</label>
    @endif

    <div class="table-responsive mb-3">
        <table class="table table-sm align-middle mb-0">
            <thead>
                <tr>
                    <th>Name</th>
                    <th class="text-end">Actions</th>
                </tr>
            </thead>
            <tbody data-inline-string-list-body>
                @if($normalizedRows->where('_delete', '0')->isNotEmpty())
                    @foreach($normalizedRows->where('_delete', '0') as $row)
                        <tr data-inline-string-row="{{ $row['row_key'] }}">
                            <td>
                                <div data-inline-string-display>{{ $row['value'] }}</div>
                                <div class="d-none" data-inline-string-edit-wrap>
                                    <input type="text"
                                           class="form-control form-control-sm"
                                           value="{{ $row['value'] }}"
                                           list="{{ $datalistId }}"
                                           data-inline-string-input
                                           placeholder="{{ $inputPlaceholder }}">
                                    <div class="text-danger small mt-1 d-none" data-inline-string-error></div>
                                </div>
                            </td>
                            <td class="text-end text-nowrap">
                                <div class="btn-group btn-group-sm" role="group" aria-label="String item actions" data-inline-string-actions>
                                    <button type="button"
                                            class="btn btn-outline-secondary"
                                            data-inline-string-edit
                                            data-bs-toggle="tooltip"
                                            data-bs-title="Edit item"
                                            aria-label="Edit item">
                                        <i class="bi bi-pencil-square"></i>
                                    </button>
                                    <button type="button"
                                            class="btn btn-outline-success d-none"
                                            data-inline-string-save
                                            data-bs-toggle="tooltip"
                                            data-bs-title="Save item"
                                            aria-label="Save item">
                                        <i class="bi bi-check-lg"></i>
                                    </button>
                                    <button type="button"
                                            class="btn btn-outline-secondary d-none"
                                            data-inline-string-cancel
                                            data-bs-toggle="tooltip"
                                            data-bs-title="Cancel editing"
                                            aria-label="Cancel editing">
                                        <i class="bi bi-x-lg"></i>
                                    </button>
                                    <button type="button"
                                            class="btn btn-outline-danger"
                                            data-inline-string-remove
                                            data-bs-toggle="tooltip"
                                            data-bs-title="Remove item"
                                            aria-label="Remove item">
                                        <i class="bi bi-trash"></i>
                                    </button>
                                </div>
                            </td>
                        </tr>
                    @endforeach
                @endif

                <tr class="{{ $normalizedRows->where('_delete', '0')->isNotEmpty() ? 'd-none' : '' }}" data-inline-string-empty>
                    <td colspan="2" class="text-center text-muted py-4 small">
                        {{ $emptyMessage }}
                    </td>
                </tr>
            </tbody>
        </table>
    </div>

    <div class="d-flex justify-content-end mt-3">
        <button type="button" class="btn btn-sm btn-outline-primary" data-inline-string-add>{{ $addButtonText }}</button>
    </div>

    @if($errorMessages->isNotEmpty())
        @foreach($errorMessages as $message)
            <div class="text-danger small mt-1">{{ $message }}</div>
        @endforeach
    @endif

    <div data-inline-string-hidden-inputs>
        @foreach($normalizedRows as $row)
            <div data-inline-string-hidden-row="{{ $row['row_key'] }}">
                @if($row['id'] !== '')
                    <input type="hidden" name="{{ $name }}[{{ $row['row_key'] }}][id]" value="{{ $row['id'] }}">
                @endif
                <input type="hidden" name="{{ $name }}[{{ $row['row_key'] }}][value]" value="{{ $row['value'] }}">
                <input type="hidden" name="{{ $name }}[{{ $row['row_key'] }}][_delete]" value="{{ $row['_delete'] }}">
            </div>
        @endforeach
    </div>

    <script type="application/json" data-inline-string-rows>
        @json($normalizedRows->values())
    </script>

    @if($suggestions->isNotEmpty())
        <datalist id="{{ $datalistId }}">
            @foreach($suggestions as $suggestion)
                <option value="{{ $suggestion }}"></option>
            @endforeach
        </datalist>
    @endif
</div>

@once
<script>
(function () {
    function parseJson(node, fallback) {
        try {
            return JSON.parse(node.textContent || '');
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
            .replace(/'/g, '&#039;');
    }

    function initializeInlineStringList(list) {
        if (list.dataset.inlineStringListReady === 'true') {
            return;
        }

        const rowsNode = list.querySelector('[data-inline-string-rows]');
        const body = list.querySelector('[data-inline-string-list-body]');
        const hiddenInputs = list.querySelector('[data-inline-string-hidden-inputs]');
        const addButton = list.querySelector('[data-inline-string-add]');
        const emptyState = list.querySelector('[data-inline-string-empty]');

        if (!rowsNode || !body || !hiddenInputs || !addButton || !emptyState) {
            return;
        }

        const name = list.dataset.name;
        const inputPlaceholder = list.dataset.inputPlaceholder || 'Enter a value...';
        const datalistId = list.dataset.datalistId || '';
        const rows = parseJson(rowsNode, []).reduce(function (carry, row) {
            if (!row || !row.row_key) {
                return carry;
            }

            carry[String(row.row_key)] = {
                row_key: String(row.row_key),
                id: row.id ?? '',
                value: String(row.value ?? ''),
                _delete: String(row._delete ?? '0'),
                isEditing: false,
                isNew: String(row.id ?? '') === '',
                originalValue: String(row.value ?? ''),
            };

            return carry;
        }, {});

        let nextTempId = 1;

        function visibleRows() {
            return Object.values(rows).filter(function (row) {
                return row._delete !== '1';
            });
        }

        function rowMarkup(row) {
            const displayValue = escapeHtml(row.value);

            return `
                <tr data-inline-string-row="${escapeHtml(row.row_key)}">
                    <td>
                        <div class="${row.isEditing ? 'd-none' : ''}" data-inline-string-display>${displayValue}</div>
                        <div class="${row.isEditing ? '' : 'd-none'}" data-inline-string-edit-wrap>
                            <input type="text"
                                   class="form-control form-control-sm"
                                   value="${displayValue}"
                                   ${datalistId ? `list="${escapeHtml(datalistId)}"` : ''}
                                   data-inline-string-input
                                   placeholder="${escapeHtml(inputPlaceholder)}">
                            <div class="text-danger small mt-1 d-none" data-inline-string-error></div>
                        </div>
                    </td>
                    <td class="text-end text-nowrap">
                        <div class="btn-group btn-group-sm" role="group" aria-label="String item actions" data-inline-string-actions>
                            <button type="button" class="btn btn-outline-secondary ${row.isEditing ? 'd-none' : ''}" data-inline-string-edit data-bs-toggle="tooltip" data-bs-title="Edit item" aria-label="Edit item">
                                <i class="bi bi-pencil-square"></i>
                            </button>
                            <button type="button" class="btn btn-outline-success ${row.isEditing ? '' : 'd-none'}" data-inline-string-save data-bs-toggle="tooltip" data-bs-title="Save item" aria-label="Save item">
                                <i class="bi bi-check-lg"></i>
                            </button>
                            <button type="button" class="btn btn-outline-secondary ${row.isEditing ? '' : 'd-none'}" data-inline-string-cancel data-bs-toggle="tooltip" data-bs-title="Cancel editing" aria-label="Cancel editing">
                                <i class="bi bi-x-lg"></i>
                            </button>
                            <button type="button" class="btn btn-outline-danger" data-inline-string-remove data-bs-toggle="tooltip" data-bs-title="Remove item" aria-label="Remove item">
                                <i class="bi bi-trash"></i>
                            </button>
                        </div>
                    </td>
                </tr>
            `;
        }

        function hiddenMarkup(row) {
            const idInput = row.id !== ''
                ? `<input type="hidden" name="${escapeHtml(name)}[${escapeHtml(row.row_key)}][id]" value="${escapeHtml(row.id)}">`
                : '';

            return `
                <div data-inline-string-hidden-row="${escapeHtml(row.row_key)}">
                    ${idInput}
                    <input type="hidden" name="${escapeHtml(name)}[${escapeHtml(row.row_key)}][value]" value="${escapeHtml(row.value)}">
                    <input type="hidden" name="${escapeHtml(name)}[${escapeHtml(row.row_key)}][_delete]" value="${escapeHtml(row._delete)}">
                </div>
            `;
        }

        function syncHiddenInputs() {
            hiddenInputs.innerHTML = '';

            Object.values(rows).forEach(function (row) {
                hiddenInputs.insertAdjacentHTML('beforeend', hiddenMarkup(row));
            });
        }

        function syncEmptyState() {
            emptyState.classList.toggle('d-none', visibleRows().length > 0);
        }

        function render() {
            Array.from(body.querySelectorAll('[data-inline-string-row]')).forEach(function (rowEl) {
                disposeTooltips(rowEl);
                rowEl.remove();
            });

            visibleRows().forEach(function (row) {
                emptyState.insertAdjacentHTML('beforebegin', rowMarkup(row));
            });

            syncEmptyState();
            syncHiddenInputs();
            initTooltips(body);

            visibleRows().forEach(function (row) {
                if (!row.isEditing) {
                    return;
                }

                const rowEl = body.querySelector(`[data-inline-string-row="${CSS.escape(row.row_key)}"]`);
                const input = rowEl?.querySelector('[data-inline-string-input]');

                if (input) {
                    input.focus();
                    input.select();
                }
            });
        }

        function createRowKey() {
            return 'row-new-' + Date.now() + '-' + (nextTempId++);
        }

        function saveRow(rowKey) {
            const rowEl = body.querySelector(`[data-inline-string-row="${CSS.escape(rowKey)}"]`);
            const input = rowEl?.querySelector('[data-inline-string-input]');
            const errorEl = rowEl?.querySelector('[data-inline-string-error]');

            if (!rowEl || !input || !rows[rowKey]) {
                return;
            }

            const value = input.value.trim();

            if (value === '') {
                input.classList.add('is-invalid');
                if (errorEl) {
                    errorEl.textContent = 'A value is required.';
                    errorEl.classList.remove('d-none');
                }
                return;
            }

            input.classList.remove('is-invalid');
            if (errorEl) {
                errorEl.textContent = '';
                errorEl.classList.add('d-none');
            }

            rows[rowKey].value = value;
            rows[rowKey].originalValue = value;
            rows[rowKey].isEditing = false;
            render();
        }

        function cancelRow(rowKey) {
            if (!rows[rowKey]) {
                return;
            }

            if (rows[rowKey].isNew && rows[rowKey].originalValue === '') {
                delete rows[rowKey];
                render();
                return;
            }

            rows[rowKey].value = rows[rowKey].originalValue;
            rows[rowKey].isEditing = false;
            render();
        }

        function editRow(rowKey) {
            if (!rows[rowKey]) {
                return;
            }

            rows[rowKey].isEditing = true;
            render();
        }

        function removeRow(rowKey) {
            if (!rows[rowKey]) {
                return;
            }

            if (rows[rowKey].id !== '') {
                rows[rowKey]._delete = '1';
                rows[rowKey].isEditing = false;
            } else {
                delete rows[rowKey];
            }

            render();
        }

        addButton.addEventListener('click', function () {
            const rowKey = createRowKey();
            rows[rowKey] = {
                row_key: rowKey,
                id: '',
                value: '',
                _delete: '0',
                isEditing: true,
                isNew: true,
                originalValue: '',
            };

            render();
        });

        body.addEventListener('click', function (event) {
            const actionButton = event.target.closest('[data-inline-string-edit], [data-inline-string-save], [data-inline-string-cancel], [data-inline-string-remove]');

            if (!actionButton) {
                return;
            }

            const rowEl = actionButton.closest('[data-inline-string-row]');
            const rowKey = rowEl?.dataset.inlineStringRow;

            if (!rowKey) {
                return;
            }

            if (actionButton.hasAttribute('data-inline-string-edit')) {
                editRow(rowKey);
            } else if (actionButton.hasAttribute('data-inline-string-save')) {
                saveRow(rowKey);
            } else if (actionButton.hasAttribute('data-inline-string-cancel')) {
                cancelRow(rowKey);
            } else if (actionButton.hasAttribute('data-inline-string-remove')) {
                removeRow(rowKey);
            }
        });

        body.addEventListener('keydown', function (event) {
            if (!event.target.matches('[data-inline-string-input]')) {
                return;
            }

            const rowEl = event.target.closest('[data-inline-string-row]');
            const rowKey = rowEl?.dataset.inlineStringRow;

            if (!rowKey) {
                return;
            }

            if (event.key === 'Enter') {
                event.preventDefault();
                saveRow(rowKey);
            }

            if (event.key === 'Escape') {
                event.preventDefault();
                cancelRow(rowKey);
            }
        });

        list.dataset.inlineStringListReady = 'true';
        render();
    }

    document.addEventListener('DOMContentLoaded', function () {
        document.querySelectorAll('[data-inline-string-list]').forEach(initializeInlineStringList);
    });
})();
</script>
@endonce
