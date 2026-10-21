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
