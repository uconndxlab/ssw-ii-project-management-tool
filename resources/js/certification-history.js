const form = document.getElementById('history-form');

if (form) {
    const { months, existingCount, hasProgress, cycle } = JSON.parse(form.querySelector('[data-certification-history-config]').textContent);
    const format = value => value ? new Date(value + 'T12:00:00').toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' }) : '';
    const activeRows = () => [...form.querySelectorAll('[data-history-row]')].filter(row => !row.hidden && row.style.display !== 'none');
    function recalculate() {
        let previous = '';
        const rows = activeRows();
        rows.forEach(row => {
            const date = row.querySelector('[data-award-date]').value;
            const field = row.querySelector('[data-date-override]');
            if (!field) return;
            let calculated = '';
            if (date) {
                const base = new Date((previous > date ? previous : date) + 'T12:00:00');
                const day = base.getDate();
                base.setDate(1); base.setMonth(base.getMonth() + months);
                base.setDate(Math.min(day, new Date(base.getFullYear(), base.getMonth() + 1, 0).getDate()));
                calculated = `${base.getFullYear()}-${String(base.getMonth()+1).padStart(2,'0')}-${String(base.getDate()).padStart(2,'0')}`;
            }
            field.dataset.calculated = calculated;
            const input = field.querySelector('[data-date-input]');
            if (field.querySelector('[data-date-flag]').value !== '1') {
                input.value = calculated;
                field.querySelector('[data-date-display]').textContent = calculated ? format(calculated) : 'Set from award date';
            }
            previous = input.value;
        });
        const warning = form.querySelector('[data-progress-warning]');
        warning.hidden = !hasProgress || (existingCount > 0 && rows.length <= existingCount);
        warning.textContent = `This candidate has progress in cycle ${cycle}. After saving, they'll be in ${months === null ? 'cycle 1' : 'renewal cycle ' + (rows.length + 1)}, and only activity after ${format(rows.at(-1)?.querySelector('[data-award-date]').value) || 'the last award date'} will count. Confirmations from cycle ${cycle} stay with that cycle.`;
        return { count: rows.length, expiry: previous };
    }
    form.addEventListener('input', recalculate);
    form.addEventListener('change', recalculate);
    new MutationObserver(recalculate).observe(form.querySelector('[data-repeater-rows]'), { childList: true, subtree: false, attributes: true });
    form.addEventListener('click', event => { if(event.target.closest('[data-repeater-remove]')) setTimeout(recalculate, 0); });
    let confirmed = false;
    form.addEventListener('submit', async event => {
        if (confirmed) return;
        event.preventDefault();
        event.stopImmediatePropagation();
        const { count, expiry } = recalculate();
        const today = new Date().toLocaleDateString('en-CA');
        const summary = `${count} award${count === 1 ? '' : 's'}, ${expiry ? (expiry <= today ? 'expired ' : 'valid until ') + format(expiry) : 'no expiry'}, ${months === null ? 'stays in cycle 1' : 'moves to renewal cycle ' + (count + 1)}.`;
        if (await window.appConfirm({ title: 'Save award history?', record: summary, message: "These awards can't be removed once saved. Dates and details can still be edited. Check everything is correct.", action: 'Save history', tone: 'warning', icon: 'bi-clock-history' })) {
            confirmed = true; form.requestSubmit();
        }
    }, true);
    recalculate();
}
