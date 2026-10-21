// Links each x-form-field's control to its label, help text and error, and marks required and invalid state.
// (The component cannot add attributes to the control it wraps, so this runs over the rendered fields.)
function enhanceFields(root = document) {
    root.querySelectorAll('[data-form-field]:not(fieldset)').forEach((field) => {
        const control = findControl(field);

        if (!control) {
            return;
        }

        const label = field.querySelector(':scope > label[id]');

        if (label && !label.hasAttribute('for') && !control.hasAttribute('aria-labelledby')) {
            control.setAttribute('aria-labelledby', label.id);
        }

        const describedBy = new Set((control.getAttribute('aria-describedby') ?? '').split(' ').filter(Boolean));
        field.querySelectorAll('[data-field-help], [data-field-error]').forEach((node) => describedBy.add(node.id));

        if (describedBy.size > 0) {
            control.setAttribute('aria-describedby', [...describedBy].join(' '));
        }

        if (field.querySelector('[data-field-error]')) {
            control.setAttribute('aria-invalid', 'true');

            if (!control.matches('[data-token-search]')) {
                control.classList.add('is-invalid');
            }
        }

        if (field.hasAttribute('data-field-required') && !control.hasAttribute('aria-required')) {
            control.setAttribute('aria-required', 'true');
        }
    });
}

function findControl(field) {
    if (field.dataset.controlId) {
        return document.getElementById(field.dataset.controlId);
    }

    const picker = field.querySelector('[data-token-search]');

    if (picker) {
        return picker;
    }

    const candidates = field.querySelectorAll(
        'input:not([type="hidden"]):not([type="checkbox"]):not([type="radio"]):not([type="search"]), select, textarea',
    );

    return candidates.length === 1 ? candidates[0] : null;
}

// Labels with no `for` outside an x-form-field (the certificate requirement rows, for one): when the label's
// parent holds exactly one control, point the control at the label.
let bareLabelCount = 0;

function linkBareLabels(root = document) {
    root.querySelectorAll('label.form-label:not([for])').forEach((label) => {
        if (label.closest('[data-form-field]')) {
            return;
        }

        const controls = label.parentElement?.querySelectorAll(
            'input:not([type="hidden"]):not([type="checkbox"]):not([type="radio"]), select, textarea',
        ) ?? [];

        if (controls.length !== 1) {
            return;
        }

        const control = controls[0];

        if (control.hasAttribute('aria-labelledby') || control.labels?.length) {
            return;
        }

        label.id ||= `bare-label-${++bareLabelCount}`;
        control.setAttribute('aria-labelledby', label.id);
    });
}

function enhanceAll(root = document) {
    enhanceFields(root);
    linkBareLabels(root);
}

document.addEventListener('DOMContentLoaded', () => enhanceAll());
document.addEventListener('htmx:afterSettle', (event) => enhanceAll(event.target));

// Rows added by scripts (repeaters, cloned templates) need the same treatment.
new MutationObserver((records) => {
    records.forEach((record) => record.addedNodes.forEach((node) => {
        if (node.nodeType === 1 && (node.matches('label, [data-form-field]') || node.querySelector('label.form-label, [data-form-field]'))) {
            enhanceAll(node);
        }
    }));
}).observe(document.body, { childList: true, subtree: true });
