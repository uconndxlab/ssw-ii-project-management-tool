function normalizeKfs(value) {
    return String(value || '').trim().toUpperCase();
}

function isValidKfs(value) {
    return /^[A-Za-z0-9]{1,7}$/.test(value);
}

function initializeAgreementKfsSection(section) {
    if (section.dataset.agreementKfsInitialized === 'true') {
        return;
    }

    const addButton = section.querySelector('[data-kfs-add-button]');
    const picker = section.querySelector('[data-token-picker]');
    const input = picker ? picker.querySelector('[data-token-search]') : null;
    const list = section.querySelector('[data-kfs-list]');
    const inlineError = section.querySelector('[data-kfs-inline-error]');
    if (!picker || !input || !list) {
        return;
    }

    function selectedNumbers() {
        return Array.from(picker.querySelectorAll('[data-token-inputs] input[type="hidden"]')).map(function (hiddenInput) {
            return normalizeKfs(hiddenInput.value);
        }).filter(Boolean);
    }

    function renderSelectedList() {
        const numbers = selectedNumbers();

        list.innerHTML = '';

        numbers.forEach(function (number) {
            const badge = document.createElement('span');
            const removeButton = document.createElement('button');

            badge.className = 'badge text-bg-light border d-inline-flex align-items-center gap-1';
            badge.innerHTML = '<span class="d-inline-flex flex-wrap align-items-baseline gap-0"></span>';
            badge.querySelector('span').textContent = number;

            removeButton.type = 'button';
            removeButton.className = 'btn-close';
            removeButton.setAttribute('aria-label', 'Remove KFS account');
            removeButton.addEventListener('click', function () {
                picker.dispatchEvent(new CustomEvent('token-picker:set', {
                    detail: numbers.filter(function (value) {
                        return value !== number;
                    }),
                    bubbles: true,
                }));
            });

            badge.appendChild(removeButton);
            list.appendChild(badge);
        });
    }

    function dispatchChange() {
        section.dispatchEvent(new CustomEvent('agreement-kfs:change', {
            bubbles: true,
            detail: {
                numbers: selectedNumbers(),
            },
        }));
    }

    function showError(message) {
        if (!inlineError) {
            return;
        }

        inlineError.textContent = message;
        inlineError.classList.remove('d-none');
    }

    function clearError() {
        if (!inlineError) {
            return;
        }

        inlineError.textContent = '';
        inlineError.classList.add('d-none');
    }

    function tryAddCurrentValue() {
        const number = normalizeKfs(input.value);

        clearError();

        if (number === '') {
            return;
        }

        if (!isValidKfs(number)) {
            showError('KFS numbers must be 1-7 alphanumeric characters.');
            return;
        }

        picker.dispatchEvent(new CustomEvent('token-picker:add-option', {
            detail: {
                option: {
                    value: number,
                    label: number,
                    search: number,
                },
                select: true,
            },
            bubbles: true,
        }));

        input.value = '';
    }

    addButton?.addEventListener('click', tryAddCurrentValue);
    input?.addEventListener('keydown', function (event) {
        if (event.key !== 'Enter') {
            return;
        }

        event.preventDefault();
        tryAddCurrentValue();
    });

    picker.addEventListener('token-picker:change', function () {
        clearError();
        renderSelectedList();
        dispatchChange();
    });

    renderSelectedList();
    dispatchChange();
    section.dataset.agreementKfsInitialized = 'true';
}

document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('[data-agreement-kfs-section]').forEach(function (section) {
        initializeAgreementKfsSection(section);
    });
});
