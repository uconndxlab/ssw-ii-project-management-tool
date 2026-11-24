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

function initializeInlineStringList(list) {
    if (list.dataset.inlineStringListReady === 'true') {
        return;
    }

    const rowsContainer = list.querySelector('[data-inline-string-rows]');
    const addButton = list.querySelector('[data-inline-string-add]');
    const emptyState = list.querySelector('[data-inline-string-empty]');
    const name = list.dataset.name;
    const valueField = list.dataset.valueField || 'value';
    const inputPlaceholder = list.dataset.inputPlaceholder || 'Enter a value…';
    const datalistId = list.dataset.datalistId || '';

    if (!rowsContainer || !addButton || !name) {
        return;
    }

    let nextIndex = rowsContainer.querySelectorAll('[data-inline-string-row]').length;

    function visibleRows() {
        return Array.from(rowsContainer.querySelectorAll('[data-inline-string-row]')).filter(function (row) {
            return !row.classList.contains('d-none');
        });
    }

    function syncEmptyState() {
        emptyState?.classList.toggle('d-none', visibleRows().length > 0);
    }

    function buildRow(rowKey) {
        const fieldName = name + '[' + rowKey + ']';

        return element('div', { 'data-inline-string-row': rowKey }, [
            element('input', { type: 'hidden', name: fieldName + '[_delete]', value: '0', 'data-inline-string-delete': true }),
            element('div', { class: 'd-flex align-items-center gap-2' }, [
                element('input', {
                    type: 'text',
                    class: 'form-control',
                    name: fieldName + '[' + valueField + ']',
                    value: '',
                    placeholder: inputPlaceholder,
                    list: datalistId || null,
                    'data-inline-string-input': true,
                }),
                element('button', { type: 'button', class: 'btn btn-outline-danger flex-shrink-0', 'aria-label': 'Remove', 'data-inline-string-remove': true }, ['-']),
            ]),
        ]);
    }

    function addRow() {
        const rowKey = 'row-new-' + Date.now() + '-' + (nextIndex++);
        const row = buildRow(rowKey);
        rowsContainer.append(row);
        const input = row.querySelector('[data-inline-string-input]');
        input?.focus();
        syncEmptyState();

        return input;
    }

    addButton.addEventListener('click', function () {
        addRow();
    });

    rowsContainer.addEventListener('keydown', function (event) {
        if (event.key !== 'Enter') {
            return;
        }

        const input = event.target.closest('[data-inline-string-input]');

        if (!input) {
            return;
        }

        event.preventDefault();

        const allInputs = visibleRows().map(function (row) {
            return row.querySelector('[data-inline-string-input]');
        }).filter(Boolean);
        const nextInput = allInputs[allInputs.indexOf(input) + 1];

        if (nextInput) {
            nextInput.focus();
            nextInput.select();

            return;
        }

        addRow();
    });

    rowsContainer.addEventListener('click', function (event) {
        const button = event.target.closest('[data-inline-string-remove]');

        if (!button) {
            return;
        }

        const row = button.closest('[data-inline-string-row]');

        if (!row) {
            return;
        }

        const idInput = row.querySelector('input[name$="[id]"]');
        const deleteInput = row.querySelector('[data-inline-string-delete]');

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

        syncEmptyState();
    });

    list.dataset.inlineStringListReady = 'true';
    syncEmptyState();
}

document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('[data-inline-string-list]').forEach(initializeInlineStringList);
});
