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

document.addEventListener('DOMContentLoaded', () => enhanceFields());
document.addEventListener('htmx:afterSettle', (event) => enhanceFields(event.target));
