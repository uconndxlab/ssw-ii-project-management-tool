// Reset and show the modal before HTMX handles the trigger.
document.addEventListener('click', (event) => {
    if (!event.target.closest('[data-activity-action-log]')) {
        return;
    }

    const body = document.getElementById('activity-action-log-modal-body');
    if (body) {
        body.innerHTML = '<p class="text-muted small mb-0">Loading…</p>';
    }

    const count = document.getElementById('activity-action-log-modal-count');
    if (count) {
        count.classList.add('d-none');
        count.textContent = '';
    }

    const subtitle = document.getElementById('activity-action-log-modal-subtitle');
    if (subtitle) {
        subtitle.textContent = '';
    }

    const modal = document.getElementById('activity-action-log-modal');
    if (modal && window.bootstrap) {
        window.bootstrap.Modal.getOrCreateInstance(modal).show();
    }
}, true);
