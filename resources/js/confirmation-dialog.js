const dialog = document.querySelector('[data-confirm-dialog]');
const acceptButton = dialog.querySelector('[data-confirm-accept]');
const approvedButtons = new WeakSet();

window.appConfirm = ({ title, record = '', message, action = 'Continue', tone = 'danger', trigger = document.activeElement }) => {
    if (dialog.open) {
        return Promise.resolve(false);
    }

    const returnFocus = trigger?.closest('.dropdown')?.querySelector('[data-bs-toggle="dropdown"]') ?? trigger;
    const destructive = tone !== 'primary';
    dialog.querySelector('#app-confirm-title').textContent = title;
    dialog.querySelector('#app-confirm-record').textContent = record;
    dialog.querySelector('#app-confirm-record').hidden = !record;
    dialog.querySelector('#app-confirm-message').textContent = message;
    dialog.querySelector('[data-confirm-icon]').className = destructive ? 'bi bi-trash3' : 'bi bi-files';
    dialog.classList.toggle('app-confirm-dialog--primary', !destructive);
    acceptButton.className = destructive ? 'btn btn-danger' : 'btn btn-primary';
    acceptButton.textContent = action;
    dialog.returnValue = 'cancel';

    return new Promise((resolve) => {
        dialog.addEventListener('close', () => {
            document.body.classList.remove('app-confirm-open');
            if (returnFocus?.isConnected) {
                returnFocus.focus({ preventScroll: true });
            }
            resolve(dialog.returnValue === 'confirm');
        }, { once: true });

        document.body.classList.add('app-confirm-open');
        dialog.showModal();
        dialog.querySelector('[value="cancel"]').focus();
    });
};

dialog.querySelector('[data-confirm-close]').addEventListener('click', () => dialog.close('cancel'));
dialog.addEventListener('keydown', (event) => {
    if (event.key === 'Escape') {
        event.stopPropagation();
    }
});

document.addEventListener('submit', async (event) => {
    const button = event.submitter;
    if (!button?.matches('[data-confirm]')) {
        return;
    }

    if (approvedButtons.has(button)) {
        approvedButtons.delete(button);
        return;
    }

    event.preventDefault();
    event.stopImmediatePropagation();
    const form = event.target;
    const confirmed = await window.appConfirm({
        title: button.dataset.confirmTitle,
        record: button.dataset.confirmRecord,
        message: button.dataset.confirmMessage,
        action: button.dataset.confirmAction,
        tone: button.dataset.confirmTone,
        trigger: button,
    });

    if (confirmed && button.isConnected && form.isConnected) {
        approvedButtons.add(button);
        form.requestSubmit(button);
        button.disabled = true;
        button.setAttribute('aria-busy', 'true');
    }
}, true);
