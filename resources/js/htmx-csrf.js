// Attach CSRF token to every HTMX request globally
document.addEventListener('htmx:configRequest', function (evt) {
    const meta = document.querySelector('meta[name="csrf-token"]');
    if (meta) evt.detail.headers['X-CSRF-TOKEN'] = meta.getAttribute('content');
});
