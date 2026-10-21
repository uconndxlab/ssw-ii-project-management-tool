// Tells assistive tech which column a table is sorted by (the sort links carry the state).
function syncSortState(root = document) {
    root.querySelectorAll('th').forEach((cell) => {
        const link = cell.querySelector('a[data-sort-state]');

        if (link) {
            cell.setAttribute('aria-sort', link.dataset.sortState);
        }
    });
}

document.addEventListener('DOMContentLoaded', () => syncSortState());
document.addEventListener('htmx:afterSettle', (event) => syncSortState(event.target));

// While an htmx request replaces a table, dim it and mark it busy; afterwards say what the table now shows.
const loadingTarget = (event) => event.detail?.target ?? event.target;
const stopLoading = (event) => {
    const target = loadingTarget(event);

    target?.classList?.remove('is-loading');
    target?.removeAttribute?.('aria-busy');
};

function announce(message) {
    const live = document.getElementById('app-live');

    if (!live) {
        return;
    }

    live.textContent = '';
    setTimeout(() => {
        live.textContent = message;
    }, 50);
}

document.addEventListener('htmx:beforeRequest', (event) => {
    const target = loadingTarget(event);

    if (target?.querySelector?.('table, [data-result-count]')) {
        target.classList.add('is-loading');
        target.setAttribute('aria-busy', 'true');
    }
});

['htmx:responseError', 'htmx:sendError', 'htmx:sendAbort', 'htmx:timeout'].forEach((name) => {
    document.addEventListener(name, stopLoading);
});

document.addEventListener('htmx:afterSettle', (event) => {
    stopLoading(event);

    const summary = loadingTarget(event)?.querySelector?.('[data-result-count]');

    if (summary) {
        announce(summary.textContent.trim().replace(/\s+/g, ' '));
    }
});
