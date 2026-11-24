function element(tag, attributes = {}, children = []) {
    const node = document.createElement(tag);

    Object.entries(attributes).forEach(function ([key, value]) {
        if (value !== null && value !== undefined && value !== false) {
            node.setAttribute(key, value === true ? '' : String(value));
        }
    });

    node.append(...children.filter(Boolean));

    return node;
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

    function buildField(rowKey, field) {
        return element('div', { class: field.col || 'col-md-3' }, [
            field.label && element('label', { class: 'form-label small text-muted mb-1' }, [String(field.label)]),
            element('input', {
                type: field.type || 'text',
                class: 'form-control form-control-sm',
                name: name + '[' + rowKey + '][' + field.key + ']',
                value: '',
                placeholder: field.placeholder || '',
                maxlength: field.maxlength || null,
                'data-inline-record-input': field.key,
            }),
        ]);
    }

    function buildRow(rowKey) {
        const fieldName = name + '[' + rowKey + ']';

        return element('div', { class: 'inline-record-row card mb-2', 'data-inline-record-row': rowKey }, [
            element('div', { class: 'card-body py-2 px-3' }, [
                element('div', { class: 'd-flex align-items-start gap-2' }, [
                    sortable && element('span', { class: 'text-muted inline-record-drag-handle mt-2', 'data-drag-handle': true, 'aria-hidden': 'true' }, [
                        element('i', { class: 'bi bi-grip-vertical' }),
                    ]),
                    element('input', { type: 'hidden', name: fieldName + '[_delete]', value: '0', 'data-inline-record-delete': true }),
                    primaryField && element('input', { type: 'hidden', name: fieldName + '[' + primaryField + ']', value: '0', 'data-inline-record-primary-input': true }),
                    element('div', { class: 'row g-2 flex-grow-1' }, fields.map(function (field) { return buildField(rowKey, field); })),
                    element('div', { class: 'd-flex flex-column align-items-center gap-1 mt-1' }, [
                        primaryField && element('button', { type: 'button', class: 'btn btn-link p-0 inline-record-primary-toggle text-muted', 'data-inline-record-primary-toggle': true, 'aria-label': 'Mark as primary' }, [
                            element('i', { class: 'bi bi-star' }),
                        ]),
                        element('button', { type: 'button', class: 'btn btn-link p-0 text-danger', 'data-inline-record-remove': true, 'aria-label': 'Remove' }, [
                            element('i', { class: 'bi bi-x-circle' }),
                        ]),
                    ]),
                ]),
            ]),
        ]);
    }

    function attachSortable() {
        if (!sortable || !window.Sortable || rowsContainer._sortableInstance) {
            return;
        }

        rowsContainer._sortableInstance = window.Sortable.create(rowsContainer, {
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
        const row = buildRow(rowKey);
        rowsContainer.append(row);
        row.querySelector('[data-inline-record-input]')?.focus();
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
