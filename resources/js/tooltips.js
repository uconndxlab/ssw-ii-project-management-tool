export function initTooltips(scope) {
    if (!window.bootstrap?.Tooltip) {
        return;
    }

    const root = scope || document;
    root.querySelectorAll('[data-bs-toggle="tooltip"]').forEach(function (element) {
        window.bootstrap.Tooltip.getOrCreateInstance(element);
    });
}

export function disposeTooltips(scope) {
    if (!window.bootstrap?.Tooltip) {
        return;
    }

    const root = scope || document;
    root.querySelectorAll('[data-bs-toggle="tooltip"]').forEach(function (element) {
        const tooltip = window.bootstrap.Tooltip.getInstance(element);
        if (tooltip) {
            tooltip.hide();
            tooltip.dispose();
        }
    });
}
