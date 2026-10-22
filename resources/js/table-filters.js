// Collapses index filter forms behind a "Filters (n)" button below the md breakpoint.
// The first column of each form's row is the search box and stays visible; every other column is a filter.
const ignoredNames = new Set(['sort', 'direction', 'page', 'per_page', 'partial', 'search']);
const openForms = new Set();

function activeFilterCount(form) {
    let count = 0;

    form.querySelectorAll('select, input').forEach((field) => {
        if (!field.name || ignoredNames.has(field.name) || field.disabled || field.type === 'hidden') {
            return;
        }

        if ((field.type === 'checkbox' || field.type === 'radio') && !field.checked) {
            return;
        }

        if (String(field.value || '').trim() !== '') {
            count += 1;
        }
    });

    return count;
}

function initTableFilters(scope = document) {
    scope.querySelectorAll('form[data-table-filter-form]').forEach((form) => {
        const target = form.getAttribute('hx-target');
        const pageSize = target && document.querySelector(target)?.querySelector('[data-table-page-size]');
        const pageSizeInput = form.querySelector('input[name="per_page"]');

        if (pageSize && pageSizeInput) {
            pageSizeInput.value = pageSize.value;

            const clear = form.querySelector('[data-table-filter-clear-wrap] a');

            if (clear) {
                const url = new URL(clear.href);
                url.searchParams.set('per_page', pageSize.value);
                clear.href = url.toString();
            }
        }

        const row = form.querySelector(':scope > .row');

        if (!row || row.classList.contains('app-filters-js') || row.children.length < 2) {
            return;
        }

        const [searchCol, ...fieldCols] = Array.from(row.children);
        const panelId = `${form.id || 'filters'}-fields`;
        const toggleCol = document.createElement('div');

        toggleCol.className = 'app-filters-toggle-col d-md-none';
        toggleCol.innerHTML = `<button type="button" class="btn btn-outline-secondary btn-sm app-filters-toggle" aria-expanded="false" aria-controls="${panelId}">
            <i class="bi bi-sliders" aria-hidden="true"></i> Filters <span class="app-filters-count"></span>
        </button>`;

        searchCol.classList.add('app-filters-search');
        searchCol.after(toggleCol);
        fieldCols.forEach((col) => col.classList.add('app-filter-field'));
        fieldCols[0].id = panelId;
        row.classList.add('app-filters-js');

        const button = toggleCol.querySelector('button');
        const count = toggleCol.querySelector('.app-filters-count');
        const sync = () => {
            const open = row.classList.contains('is-open');
            const active = activeFilterCount(form);

            button.setAttribute('aria-expanded', String(open));
            count.textContent = active > 0 ? String(active) : '';
        };

        button.addEventListener('click', () => {
            const open = row.classList.toggle('is-open');

            if (form.id) {
                open ? openForms.add(form.id) : openForms.delete(form.id);
            }

            sync();
        });

        form.addEventListener('change', sync);

        // A filter change can swap the whole form in; keep the panel open across that.
        if (form.id && openForms.has(form.id)) {
            row.classList.add('is-open');
        }

        sync();
    });
}

document.addEventListener('DOMContentLoaded', () => initTableFilters());
document.body.addEventListener('htmx:afterSettle', () => initTableFilters());
