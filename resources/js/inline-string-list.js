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

    function rowMarkup(rowKey) {
        const listAttr = datalistId ? ` list="${escapeHtml(datalistId)}"` : '';

        return `
            <div data-inline-string-row="${escapeHtml(rowKey)}">
                <input type="hidden" name="${escapeHtml(name)}[${escapeHtml(rowKey)}][_delete]" value="0" data-inline-string-delete>
                <div class="d-flex align-items-center gap-2">
                    <input type="text"
                           class="form-control"
                           name="${escapeHtml(name)}[${escapeHtml(rowKey)}][${escapeHtml(valueField)}]"
                           value=""
                           placeholder="${escapeHtml(inputPlaceholder)}"
                           ${listAttr}
                           data-inline-string-input>
                    <button type="button" class="btn btn-outline-danger flex-shrink-0" aria-label="Remove" data-inline-string-remove>-</button>
                </div>
            </div>
        `;
    }

    function addRow() {
        const rowKey = 'row-new-' + Date.now() + '-' + (nextIndex++);
        rowsContainer.insertAdjacentHTML('beforeend', rowMarkup(rowKey));
        const input = rowsContainer.querySelector('[data-inline-string-row="' + CSS.escape(rowKey) + '"] [data-inline-string-input]');
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
