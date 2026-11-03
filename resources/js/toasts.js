// Toasts: success and info close themselves after a timeout (shown as a bar along the bottom that pauses on hover,
// focus and while the tab is hidden); warnings and errors stay until closed. The stack's data-toast-timeout sets the
// default; window.appToast(message, { tone, title, timeout }) shows one from script, and timeout 0 keeps it open.
const icons = {
    success: 'bi-check-circle-fill',
    info: 'bi-info-circle-fill',
    warning: 'bi-exclamation-triangle-fill',
    danger: 'bi-x-circle-fill',
};

function dismiss(toast) {
    if (toast.classList.contains('is-leaving')) {
        return;
    }

    if (toast.contains(document.activeElement)) {
        document.getElementById('main')?.focus({ preventScroll: true });
    }

    toast.classList.add('is-leaving');
    window.setTimeout(() => toast.remove(), 150);
}

function showToast(content, { tone = 'success', title = '', timeout } = {}) {
    const stack = document.querySelector('[data-app-toasts]');
    if (!stack) {
        return null;
    }

    const sticky = tone === 'warning' || tone === 'danger';

    const toast = document.createElement('div');
    toast.className = `app-toast app-toast--${icons[tone] ? tone : 'info'}`;
    if (tone === 'danger') {
        toast.setAttribute('role', 'alert');
    }
    toast.addEventListener('keydown', (event) => {
        if (event.key === 'Escape') {
            dismiss(toast);
        }
    });

    const icon = document.createElement('i');
    icon.className = `bi ${icons[tone] || icons.info} app-toast-icon`;
    icon.setAttribute('aria-hidden', 'true');

    const body = document.createElement('div');
    body.className = 'app-toast-body';
    if (title) {
        const heading = document.createElement('div');
        heading.className = 'app-toast-title';
        heading.textContent = title;
        body.append(heading);
    }
    if (content instanceof Node) {
        body.append(content);
    } else {
        const message = document.createElement('div');
        message.textContent = String(content);
        body.append(message);
    }

    const close = document.createElement('button');
    close.type = 'button';
    close.className = 'btn-close app-toast-close';
    close.setAttribute('aria-label', 'Dismiss notification');
    close.addEventListener('click', () => dismiss(toast));

    toast.append(icon, body, close);

    // A toast with its own link or button never closes by itself, so the control cannot vanish mid-reach.
    const interactive = body.querySelector('a, button, input, select, textarea') !== null;
    const duration = sticky || interactive ? (timeout ?? 0) : (timeout ?? (Number(stack.dataset.toastTimeout) || 6000));

    if (duration > 0) {
        const timer = document.createElement('div');
        timer.className = 'app-toast-timer';
        timer.style.animationDuration = `${duration}ms`;
        timer.addEventListener('animationend', () => dismiss(toast));
        toast.append(timer);
    }

    stack.append(toast);

    // Errors announce through role="alert"; everything else through the page's polite live region.
    const live = document.getElementById('app-live');
    if (tone !== 'danger' && live) {
        live.textContent = body.textContent.replace(/\s+/g, ' ').trim();
    }

    return toast;
}

window.appToast = showToast;

// Flashed toasts wait until just after load: screen readers skip live-region changes made while the page loads.
window.addEventListener('load', () => window.setTimeout(() => {
    const stack = document.querySelector('[data-app-toasts]');
    if (!stack) {
        return;
    }

    document.addEventListener('visibilitychange', () => {
        stack.classList.toggle('is-paused', document.hidden);
    });

    document.querySelectorAll('template[data-toast]').forEach((template) => {
        showToast(template.content.cloneNode(true), {
            tone: template.dataset.toast,
            title: template.dataset.toastTitle || '',
        });
        template.remove();
    });
}, 300));
