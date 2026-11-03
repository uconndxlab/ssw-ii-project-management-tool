// A submit button with data-busy-label locks after the first submit and shows a spinner, so a double click sends one request.
document.addEventListener('submit', (event) => {
    const button = event.submitter;

    if (event.defaultPrevented || !button?.matches('[data-busy-label]')) {
        return;
    }

    button.dataset.idleHtml = button.innerHTML;
    button.disabled = true;
    button.setAttribute('aria-busy', 'true');
    button.innerHTML = '<span class="spinner-border spinner-border-sm me-2" aria-hidden="true"></span>';
    button.append(button.dataset.busyLabel);
});

// The back button can restore the page from cache with the button still locked.
window.addEventListener('pageshow', (event) => {
    if (!event.persisted) {
        return;
    }

    document.querySelectorAll('[data-busy-label][aria-busy="true"]').forEach((button) => {
        button.innerHTML = button.dataset.idleHtml;
        button.disabled = false;
        button.removeAttribute('aria-busy');
    });
});
