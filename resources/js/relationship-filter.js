document.querySelectorAll('[data-relationship-filter]').forEach((input) => {
    const list = input.closest('[data-relationship-list]');
    const rows = [...list.querySelectorAll('[data-ledger-item]')];
    const status = list.querySelector('[data-ledger-scroll-status]');
    const empty = list.querySelector('[data-filter-empty]');
    const names = rows.map((row) => row.querySelector('.app-ledger-name'));
    const original = names.map((name) => name.textContent);
    input.addEventListener('input', () => {
        const query = input.value.trim().toLocaleLowerCase();
        const body = list.querySelector('.app-ledger-body');
        // Hold the list's height while filtering so the cards below don't jump.
        if (query && !body.style.height) body.style.height = `${body.clientHeight}px`;
        if (!query) body.style.removeProperty('height');
        let count = 0;
        rows.forEach((row, index) => {
            const name = names[index];
            name.textContent = original[index];
            row.hidden = query !== '' && !`${row.dataset.ledgerTitle} ${row.querySelector('.app-ledger-meta')?.textContent ?? ''}`.toLocaleLowerCase().includes(query);
            if (!row.hidden) count++;
            const start = original[index].toLocaleLowerCase().indexOf(query);
            if (query && start >= 0) {
                const mark = document.createElement('mark');
                mark.textContent = original[index].slice(start, start + query.length);
                name.replaceChildren(document.createTextNode(original[index].slice(0, start)), mark, document.createTextNode(original[index].slice(start + query.length)));
            }
        });
        list.querySelectorAll('[data-ledger-group]').forEach((group) => {
            group.hidden = !group.querySelector('[data-ledger-item]:not([hidden])');
        });
        list.dataset.filterActive = String(Boolean(query));
        status.hidden = !query;
        status.textContent = `${count} of ${rows.length} match`;
        empty.hidden = !query || count !== 0;
        empty.textContent = `No ${list.dataset.listKind} match “${input.value.trim()}”.`;
        list.querySelector('[data-filter-live]').textContent = query ? status.textContent : `${rows.length} ${list.dataset.listKind}`;
        list.querySelector('.app-ledger-body').scrollTop = 0;
        list.dispatchEvent(new CustomEvent('relationship-filter:change', { bubbles: true }));
    });
    input.addEventListener('keydown', (event) => {
        if (event.key === 'Escape') {
            input.value = '';
            input.dispatchEvent(new Event('input'));
        }
    });
});
