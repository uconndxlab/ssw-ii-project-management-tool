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
        row.dispatchEvent(new CustomEvent('repeater:row-added', { bubbles: true }));

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
