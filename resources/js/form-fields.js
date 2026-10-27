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

let errorTargetCount = 0;

function linkErrorSummaries(root = document) {
    const items = root.querySelectorAll('[data-error-name]');
    if (items.length === 0) {
        return;
    }

    const scope = root.closest?.('.app-form-shell') ?? document;
    const normalize = (name) => name.replace(/\[([^\]]*)\]/g, '.$1').replace(/\.$/, '');
    const candidates = [...scope.querySelectorAll('[data-field-name], [data-token-picker], input[name], select[name], textarea[name]')];

    items.forEach((item) => {
        if (item.querySelector('a')) {
            return;
        }

        const name = item.dataset.errorName;
        const candidateName = (node) => normalize(node.dataset.fieldName ?? node.dataset.name ?? node.name);
        let field = candidates.find((node) => candidateName(node) === name)
            ?? candidates.find((node) => name.startsWith(`${candidateName(node)}.`));
        if (field?.type === 'hidden') {
            field = field.closest('[data-form-field], [data-token-picker]') ?? field;
        }
        const control = field?.matches('[data-form-field]')
            ? findControl(field) ?? field.querySelector('input:not([type="hidden"]), select, textarea')
            : field?.matches('[data-token-picker]') ? field.querySelector('[data-token-search]') : field;

        if (!control || control.type === 'hidden') {
            return;
        }

        control.id ||= `app-error-target-${++errorTargetCount}`;
        const link = document.createElement('a');
        link.href = `#${control.id}`;
        link.dataset.errorTarget = control.id;
        link.textContent = item.textContent;
        item.replaceChildren(link);
    });
}

document.addEventListener('click', (event) => {
    const link = event.target.closest('[data-error-target]');
    const control = link && document.getElementById(link.dataset.errorTarget);

    if (!control) {
        return;
    }

    event.preventDefault();
    control.dispatchEvent(new CustomEvent('form-error:focus', { bubbles: true }));
    for (let parent = control.parentElement; parent; parent = parent.parentElement) {
        if (parent.matches('details')) {
            parent.open = true;
        }
    }
    control.focus({ preventScroll: true });
    control.scrollIntoView({ block: 'center' });
});

function enhanceAll(root = document) {
    enhanceFields(root);
    linkBareLabels(root);
    linkErrorSummaries(root);
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
