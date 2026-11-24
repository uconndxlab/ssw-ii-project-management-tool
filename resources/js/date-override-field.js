document.addEventListener('click', event => {
    const button = event.target.closest('[data-date-change], [data-date-revert]');
    if (!button) return;
    const field = button.closest('[data-date-override]');
    const editing = button.hasAttribute('data-date-change');
    const input = field.querySelector('[data-date-input]');
    input.hidden = !editing;
    input.readOnly = !editing;
    field.querySelector('[data-date-display]').hidden = editing;
    field.querySelector('[data-date-change]').hidden = editing;
    field.querySelector('[data-date-revert]').hidden = !editing;
    const flag = field.querySelector('[data-date-flag]');
    if (flag) flag.value = editing ? '1' : '0';
    if (!editing) input.value = field.dataset.calculated;
    input.dispatchEvent(new Event('change', { bubbles: true }));
    (editing ? input : field.querySelector('[data-date-change]')).focus();
});
