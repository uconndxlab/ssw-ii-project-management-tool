<style>
    /*
     * Repeater chrome lives here (layout include) rather than the component:
     * row templates are pre-rendered via view()->render(), which would consume
     * a component-level @@once and strip these styles from the page.
     *
     * Layers: white section card → inset well (#e8eaed) → white chips.
     * Nested lists skip the well and sit on the tinted card body instead.
     */
    .repeater {
        --repeater-well: #d6dbe2;
        --repeater-chip: #fff;
        --repeater-body: #f3f4f6;
        --repeater-line: rgba(15, 23, 42, 0.1);
        --repeater-muted: var(--bs-secondary-color, #6c757d);
    }

    .repeater--well {
        background: var(--repeater-well);
        border: 1px solid var(--repeater-line);
        border-radius: 0.65rem;
    }

    .repeater-rows {
        display: flex;
        flex-direction: column;
        gap: 0.5rem;
        padding: 0.55rem;
        max-height: 26rem;
        overflow-y: auto;
        scrollbar-width: thin;
    }

    .repeater-rows.is-empty {
        display: none;
        padding: 0;
    }

    .repeater-rows--unbounded {
        max-height: none;
        gap: 0.75rem;
    }

    .repeater-add {
        display: flex;
        width: 100%;
        align-items: center;
        justify-content: center;
        gap: 0.4rem;
        margin: 0;
        padding: 0.55rem 0.75rem;
        border: 0;
        border-top: 1px solid var(--repeater-line);
        background: transparent;
        color: var(--repeater-muted);
        font-size: 0.8125rem;
        font-weight: 600;
        line-height: 1.2;
    }

    .repeater-add:hover,
    .repeater-add:focus-visible {
        color: var(--bs-body-color);
        outline: 0;
    }

    .repeater--well > .repeater-add {
        width: calc(100% - 1.1rem);
        margin: 0 0.55rem 0.55rem;
        border: 1px dashed rgba(15, 23, 42, 0.22);
        border-radius: 0.45rem;
        background: #fff;
        color: var(--repeater-muted);
    }

    .repeater--well > .repeater-add:hover,
    .repeater--well > .repeater-add:focus-visible {
        border-color: rgba(var(--bs-primary-rgb), 0.55);
        background: #fff;
        color: var(--bs-primary);
    }

    .repeater--well > .repeater-rows.is-empty ~ .repeater-add {
        margin-top: 0.55rem;
    }

    .repeater--nested {
        background: transparent;
        border: 0;
        border-radius: 0;
        overflow: visible;
    }

    .repeater--nested .repeater-rows {
        padding: 0;
        gap: 0.5rem;
    }

    .repeater--nested .repeater-rows:not(.repeater-rows--flat):not(.repeater-rows--unbounded) {
        max-height: none;
    }

    .repeater--nested .repeater-add {
        justify-content: flex-start;
        width: auto;
        margin: 0;
        padding: 0.35rem 0.15rem;
        border: 0;
        background: transparent;
    }

    .repeater--nested .repeater-add:hover,
    .repeater--nested .repeater-add:focus-visible {
        background: transparent;
        color: var(--bs-primary);
    }

    .repeater-row-card {
        background: var(--repeater-chip);
        border: 1px solid var(--repeater-line);
        border-radius: 0.5rem;
        box-shadow: 0 1px 2px rgba(15, 23, 42, 0.08);
    }

    .repeater-row-toolbar {
        display: flex;
        align-items: center;
        gap: 0.45rem;
        min-height: 2.5rem;
        padding: 0.4rem 0.5rem 0.4rem 0.35rem;
    }

    .repeater-row-summary {
        flex: 1 1 auto;
        min-width: 0;
        display: flex;
        align-items: center;
        gap: 0.4rem;
        flex-wrap: wrap;
    }

    .repeater-row-title {
        font-size: 0.875rem;
        font-weight: 600;
        line-height: 1.3;
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
    }

    .repeater-row-meta {
        display: inline-flex;
        align-items: center;
        gap: 0.25em;
        padding: 0.1rem 0.45rem;
        border: 1px solid var(--repeater-line);
        border-radius: 999px;
        background: var(--repeater-body);
        color: var(--repeater-muted);
        font-size: 0.6875rem;
        font-weight: 600;
        letter-spacing: 0.01em;
        line-height: 1.4;
        white-space: nowrap;
    }

    .repeater-row-actions {
        display: flex;
        align-items: center;
        gap: 0.1rem;
        flex-shrink: 0;
        margin-left: auto;
    }

    .repeater-row-drag {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        flex-shrink: 0;
        width: 1.5rem;
        height: 1.75rem;
        color: var(--repeater-muted);
        cursor: grab;
        border-radius: 0.3rem;
    }

    .repeater-row-drag:hover {
        background: var(--repeater-body);
        color: var(--bs-body-color);
    }

    .repeater-row-drag:active {
        cursor: grabbing;
    }

    .repeater-icon-btn {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        width: 1.75rem;
        height: 1.75rem;
        padding: 0;
        border: 0;
        border-radius: 0.3rem;
        background: transparent;
        color: var(--repeater-muted);
        line-height: 1;
    }

    .repeater-icon-btn:hover,
    .repeater-icon-btn:focus-visible {
        background: var(--repeater-body);
        color: var(--bs-body-color);
        outline: 0;
    }

    .repeater-icon-btn-danger {
        color: var(--bs-danger);
    }

    .repeater-icon-btn-danger:hover,
    .repeater-icon-btn-danger:focus-visible {
        background: rgba(var(--bs-danger-rgb), 0.1);
        color: var(--bs-danger);
    }

    .repeater-row-body {
        padding: 0.75rem 0.8rem 0.85rem;
        background: #fff;
        border-top: 1px solid var(--repeater-line);
        border-radius: 0 0 0.5rem 0.5rem;
    }

    .repeater-row-body--form {
        padding: 1.05rem 1.15rem 1.2rem;
        display: flex;
        flex-direction: column;
        gap: 1.15rem;
    }

    .repeater-row-body [data-options-list],
    .repeater-row-body [data-rules-list] {
        padding: 0.65rem 0.7rem 0.4rem;
        border: 1px solid var(--repeater-line);
        border-radius: 0.45rem;
        background: #f4f6f8;
    }

    .repeater-row-inline {
        display: flex;
        align-items: center;
        gap: 0.45rem;
        padding: 0.4rem 0.45rem 0.4rem 0.3rem;
    }

    .repeater-row-inline.is-labeled {
        align-items: flex-end;
        padding: 0.55rem 0.5rem 0.55rem 0.3rem;
    }

    .repeater-inline-fields {
        flex: 1 1 auto;
        min-width: 0;
        display: flex;
        align-items: center;
        gap: 0.45rem;
    }

    .repeater-row-inline.is-labeled .repeater-inline-fields {
        align-items: flex-end;
        display: grid;
        grid-template-columns: minmax(0, 1.4fr) minmax(0, 1fr) minmax(5.5rem, 0.7fr);
        gap: 0.5rem;
    }

    .repeater-rows--flat {
        max-height: 14rem;
    }

    .repeater-rows--flat > [data-repeater-row] {
        display: flex;
        align-items: center;
        gap: 0.35rem;
        padding: 0.25rem 0.35rem 0.25rem 0.15rem;
        background: var(--repeater-chip);
        border: 1px solid var(--repeater-line);
        border-radius: 0.4rem;
    }

    [data-repeater-collapsible][data-repeater-mode="display"] [data-row-edit-fields] {
        display: none;
    }

    [data-repeater-collapsible][data-repeater-mode="display"] > .repeater-row-toolbar {
        cursor: pointer;
    }

    [data-repeater-collapsible][data-repeater-mode="display"]:hover {
        border-color: rgba(15, 23, 42, 0.16);
        box-shadow: 0 2px 8px rgba(15, 23, 42, 0.08);
    }

    [data-repeater-mode="display"] [data-icon-collapse] {
        display: none;
    }

    [data-repeater-mode="edit"] [data-icon-edit] {
        display: none;
    }

    .repeater-sortable-ghost {
        opacity: 0.55;
        border-style: dashed !important;
        background: rgba(var(--bs-primary-rgb), 0.08) !important;
        box-shadow: none;
    }

    @@media (max-width: 767.98px) {
        .repeater-row-inline.is-labeled .repeater-inline-fields {
            grid-template-columns: 1fr;
        }
    }
</style>
<script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.6/Sortable.min.js"></script>
<script>
(function () {
    function directTemplate(wrapper) {
        return Array.from(wrapper.children).find(function (el) {
            return el.tagName === 'TEMPLATE' && el.hasAttribute('data-repeater-template');
        });
    }

    function directRowsContainer(wrapper) {
        return Array.from(wrapper.children).find(function (el) {
            return el.hasAttribute('data-repeater-rows');
        });
    }

    function rowOwns(row, el) {
        return el.closest('[data-repeater-row]') === row;
    }

    function summaryValueFromSource(source) {
        if (!source) {
            return '';
        }

        if (source.type === 'checkbox') {
            return source.checked ? 'Active' : 'Inactive';
        }

        if (source.tagName === 'SELECT') {
            const option = source.options[source.selectedIndex];

            return option ? option.text.trim() : '';
        }

        return String(source.value ?? '').trim();
    }

    function refreshRowSummary(row) {
        if (!row || !row.hasAttribute('data-repeater-collapsible')) {
            return;
        }

        row.querySelectorAll('[data-summary-source]').forEach(function (source) {
            if (!rowOwns(row, source)) {
                return;
            }

            const key = source.getAttribute('data-summary-source');

            row.querySelectorAll('[data-summary-value="' + key + '"]').forEach(function (target) {
                if (!rowOwns(row, target)) {
                    return;
                }

                let value = summaryValueFromSource(source);
                const fallback = target.getAttribute('data-summary-fallback') || '';

                if (!value && fallback) {
                    value = fallback;
                }

                target.textContent = value;
            });
        });

        row.querySelectorAll('[data-summary-count]').forEach(function (el) {
            if (!rowOwns(row, el)) {
                return;
            }

            const rootSelector = el.getAttribute('data-summary-count-root');
            const container = rootSelector ? (row.querySelector(rootSelector) || row) : row;
            const count = Array.from(container.querySelectorAll('[data-repeater-row]')).filter(function (nestedRow) {
                return nestedRow.style.display !== 'none'
                    && nestedRow.parentElement
                    && nestedRow.parentElement.closest('[data-repeater-row]') === row;
            }).length;

            el.textContent = String(count);
        });

        row.querySelectorAll('[data-summary-selected-options]').forEach(function (el) {
            if (!rowOwns(row, el)) {
                return;
            }

            const dimensionId = row.querySelector('[data-dimension-select]')?.value;
            const group = dimensionId
                ? row.querySelector('[data-option-group][data-dimension-id="' + dimensionId + '"]')
                : null;

            if (!group) {
                el.textContent = '0 of 0';
                return;
            }

            const selected = group.querySelectorAll('input[type="checkbox"]:checked').length;
            const total = group.querySelectorAll('input[type="checkbox"]').length;

            el.textContent = selected + ' of ' + total;
        });

        row.querySelectorAll('[data-summary-dimension-options]').forEach(function (el) {
            if (!rowOwns(row, el)) {
                return;
            }

            const dimensionId = row.querySelector('[data-dimension-select]')?.value;
            const group = dimensionId
                ? row.querySelector('[data-option-group][data-dimension-id="' + dimensionId + '"]')
                : null;
            const total = group ? group.querySelectorAll('input[type="checkbox"]').length : 0;

            el.textContent = String(total);
        });
    }

    function setRowMode(row, mode) {
        if (!row || !row.hasAttribute('data-repeater-collapsible')) {
            return;
        }

        row.setAttribute('data-repeater-mode', mode);
        refreshRowSummary(row);
    }

    function toggleRowMode(row) {
        setRowMode(row, row.getAttribute('data-repeater-mode') === 'edit' ? 'display' : 'edit');
    }

    function initSortableForRepeater(wrapper) {
        if (!wrapper || !wrapper.hasAttribute('data-repeater-sortable') || wrapper._repeaterSortable) {
            return;
        }

        const rowsContainer = directRowsContainer(wrapper);

        if (!rowsContainer || !window.Sortable) {
            return;
        }

        wrapper._repeaterSortable = Sortable.create(rowsContainer, {
            animation: 150,
            ghostClass: 'repeater-sortable-ghost',
            handle: '[data-repeater-drag]',
            draggable: '[data-repeater-row]',
            filter: '[data-repeater-remove], [data-row-edit], input, select, textarea, button, a',
            preventOnFilter: false,
        });
    }

    function initSortables(root) {
        (root || document).querySelectorAll('[data-repeater-sortable]').forEach(initSortableForRepeater);
    }

    function initCollapsibleRows(root) {
        (root || document).querySelectorAll('[data-repeater-collapsible]').forEach(function (row) {
            if (row.dataset.repeaterCollapsibleInit === 'true') {
                return;
            }

            row.dataset.repeaterCollapsibleInit = 'true';

            if (!row.getAttribute('data-repeater-mode')) {
                row.setAttribute('data-repeater-mode', row.getAttribute('data-existing') === '1' ? 'display' : 'edit');
            }

            refreshRowSummary(row);
        });
    }

    function updateEmptyStates(root) {
        (root || document).querySelectorAll('[data-repeater-rows]').forEach(function (container) {
            const visibleRows = Array.from(container.children).filter(function (child) {
                return child.nodeType === 1
                    && child.hasAttribute('data-repeater-row')
                    && child.style.display !== 'none';
            }).length;

            container.classList.toggle('is-empty', visibleRows === 0);
        });
    }

    function afterRowAdded(row) {
        initSortables(row);
        initCollapsibleRows(row);
        updateEmptyStates(row);

        if (row.matches('[data-repeater-collapsible]')) {
            setRowMode(row, 'edit');
        }

        const focusable = row.querySelector('input:not([type="hidden"]), select, textarea');

        if (focusable) {
            focusable.focus();
        }
    }

    function refreshSummariesInTree(root) {
        let el = root && root.nodeType === 1 ? root : null;

        if (el && !el.matches('[data-repeater-collapsible]')) {
            el = el.closest('[data-repeater-collapsible]');
        }

        while (el) {
            refreshRowSummary(el);
            el = el.parentElement ? el.parentElement.closest('[data-repeater-collapsible]') : null;
        }
    }

    document.addEventListener('click', function (e) {
        const editBtn = e.target.closest('[data-row-edit]');

        if (editBtn) {
            e.preventDefault();
            const row = editBtn.closest('[data-repeater-row]');

            if (row) {
                toggleRowMode(row);
            }

            return;
        }

        const displayClick = e.target.closest('[data-row-display-click]');

        if (displayClick && !e.target.closest('[data-repeater-drag], [data-repeater-remove], [data-row-edit]')) {
            const row = displayClick.closest('[data-repeater-row]');

            if (row && row.getAttribute('data-repeater-mode') === 'display') {
                setRowMode(row, 'edit');
            }

            return;
        }

        const addBtn = e.target.closest('[data-repeater-add]');

        if (addBtn) {
            e.preventDefault();
            const wrapper = addBtn.closest('[data-repeater]');

            if (!wrapper) {
                return;
            }

            const template = directTemplate(wrapper);
            const rowsContainer = directRowsContainer(wrapper);

            if (!template || !rowsContainer) {
                return;
            }

            const token = wrapper.getAttribute('data-index-token') || '__INDEX__';
            const nextIndex = parseInt(wrapper.getAttribute('data-next-index') || '0', 10);

            const temp = document.createElement('div');
            temp.appendChild(template.content.cloneNode(true));
            temp.innerHTML = temp.innerHTML.split(token).join(String(nextIndex));

            Array.from(temp.children).forEach(function (child) {
                rowsContainer.appendChild(child);
                afterRowAdded(child);
            });

            wrapper.setAttribute('data-next-index', String(nextIndex + 1));
            updateEmptyStates(wrapper);
            refreshSummariesInTree(wrapper.closest('[data-repeater-row]') || wrapper);

            return;
        }

        const removeBtn = e.target.closest('[data-repeater-remove]');

        if (removeBtn) {
            e.preventDefault();
            const row = removeBtn.closest('[data-repeater-row]');

            if (!row) {
                return;
            }

            const wrapper = row.closest('[data-repeater]');
            const parentRow = row.parentElement ? row.parentElement.closest('[data-repeater-row]') : null;

            if (row.getAttribute('data-existing') === '1') {
                const deleteInput = row.querySelector('[data-repeater-delete]');

                if (deleteInput) {
                    deleteInput.value = '1';
                }

                row.style.display = 'none';
            } else {
                row.remove();
            }

            updateEmptyStates(wrapper || document);
            refreshSummariesInTree(parentRow || document);

            return;
        }
    });

    document.addEventListener('change', function (e) {
        const row = e.target.closest ? e.target.closest('[data-repeater-collapsible]') : null;

        if (row) {
            refreshRowSummary(row);
        }
    });

    document.addEventListener('input', function (e) {
        const row = e.target.closest ? e.target.closest('[data-repeater-collapsible]') : null;

        if (row && e.target.hasAttribute('data-summary-source')) {
            refreshRowSummary(row);
        }
    });

    document.addEventListener('DOMContentLoaded', function () {
        initSortables(document);
        initCollapsibleRows(document);
        updateEmptyStates(document);

        if (@json($errors->any())) {
            document.querySelectorAll('[data-repeater-collapsible]').forEach(function (row) {
                setRowMode(row, 'edit');
            });
        }
    });
})();
</script>
