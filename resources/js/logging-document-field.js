const rootOf = (el) => el?.closest('[data-logging-document]');

function toggle(root, hasFile, fileName) {
    const name = root.querySelector('[data-logging-document-name]');
    const row = root.querySelector('[data-logging-document-file]');
    const drop = root.querySelector('[data-logging-document-dropzone-wrap]');
    const flag = root.querySelector('[data-logging-document-clear]');
    const input = root.querySelector('[data-logging-document-input]');

    if (hasFile) {
        if (name && fileName) {
            name.textContent = fileName;
            name.removeAttribute('href');
            name.removeAttribute('target');
        }
        row?.classList.remove('d-none');
        drop?.classList.add('d-none');
        if (flag) flag.value = '0';
        return;
    }

    row?.classList.add('d-none');
    drop?.classList.remove('d-none');
    if (input) input.value = '';
    if (flag) flag.value = '1';
}

document.addEventListener('click', function (event) {
    if (event.target.closest('[data-logging-document-name]')) return;

    const remove = event.target.closest('[data-logging-document-remove]');
    const zone = event.target.closest('[data-logging-document-dropzone]');
    const root = rootOf(remove || zone);
    if (!root) return;

    event.preventDefault();
    if (remove) {
        toggle(root, false);
        return;
    }
    root.querySelector('[data-logging-document-input]')?.click();
});

document.addEventListener('keydown', function (event) {
    if (event.key !== 'Enter' && event.key !== ' ') return;
    const root = rootOf(event.target.closest('[data-logging-document-dropzone]'));
    if (!root) return;
    event.preventDefault();
    root.querySelector('[data-logging-document-input]')?.click();
});

document.addEventListener('dragover', function (event) {
    const zone = event.target.closest('[data-logging-document-dropzone]');
    if (!zone) return;
    event.preventDefault();
    zone.classList.add('is-dragover');
});

document.addEventListener('dragleave', function (event) {
    event.target.closest('[data-logging-document-dropzone]')?.classList.remove('is-dragover');
});

document.addEventListener('drop', function (event) {
    const zone = event.target.closest('[data-logging-document-dropzone]');
    const root = rootOf(zone);
    if (!root) return;

    event.preventDefault();
    zone.classList.remove('is-dragover');

    const file = event.dataTransfer?.files?.[0];
    const input = root.querySelector('[data-logging-document-input]');
    if (!file || !input) return;

    const transfer = new DataTransfer();
    transfer.items.add(file);
    input.files = transfer.files;
    toggle(root, true, file.name);
});

document.addEventListener('change', function (event) {
    const input = event.target;
    if (!(input instanceof HTMLInputElement) || !input.matches('[data-logging-document-input]')) return;
    const file = input.files?.[0];
    const root = rootOf(input);
    if (root && file) toggle(root, true, file.name);
});
