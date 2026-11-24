import { disposeTooltips, initTooltips } from './tooltips.js';

function parseJson(text, fallback) {
    try {
        return JSON.parse(text || '');
    } catch (error) {
        return fallback;
    }
}

function getSelectedIdsFromTokenPicker(tokenPicker) {
    return Array.from(tokenPicker.querySelectorAll('[data-token-inputs] input[type="hidden"]')).map(function (input) {
        return String(input.value);
    });
}

function setSelectedIdsOnTokenPicker(tokenPicker, values) {
    tokenPicker.dispatchEvent(new CustomEvent('token-picker:set', {
        detail: values,
        bubbles: true,
    }));
}

function getHiddenInputIds(section, containerSelector) {
    return Array.from(section.querySelectorAll(containerSelector + ' input[type="hidden"]')).map(function (input) {
        return String(input.value);
    });
}

function setHiddenInputIds(section, containerSelector, name, values) {
    const container = section.querySelector(containerSelector);

    if (!container) {
        return;
    }

    const uniqueValues = Array.from(new Set((values || []).map(function (value) {
        return String(value);
    })));

    container.innerHTML = '';

    uniqueValues.forEach(function (value) {
        const input = document.createElement('input');
        input.type = 'hidden';
        input.name = name;
        input.value = value;
        container.appendChild(input);
    });
}

function getOrganizationPicker(section) {
    const scope = section.closest('.card-body') || document;

    return scope.querySelector('#' + section.dataset.organizationPickerId);
}

function getAgreementKfsNumbers(section) {
    const scope = section.closest('.card-body') || document;
    const kfsSection = scope.querySelector('[data-agreement-kfs-section]');

    if (!kfsSection) {
        return [];
    }

    return Array.from(kfsSection.querySelectorAll('[data-token-inputs] input[type="hidden"]')).map(function (input) {
        return String(input.value);
    });
}

function getStoredOrganizationKfsSelections(section) {
    return parseJson(section.dataset.selectedOrganizationKfsNumbers, {});
}

function setStoredOrganizationKfsSelections(section, selections) {
    section.dataset.selectedOrganizationKfsNumbers = JSON.stringify(selections || {});
}

function syncClassificationSelections(section) {
    const organizationPicker = getOrganizationPicker(section);

    if (!organizationPicker) {
        return;
    }

    const selectedOrganizationIds = new Set(getSelectedIdsFromTokenPicker(organizationPicker));

    setHiddenInputIds(
        section,
        '[data-organization-payor-source-inputs]',
        'organization_payor_source_ids[]',
        getHiddenInputIds(section, '[data-organization-payor-source-inputs]').filter(function (id) {
            return selectedOrganizationIds.has(String(id));
        })
    );

    setHiddenInputIds(
        section,
        '[data-organization-recipient-inputs]',
        'organization_recipient_ids[]',
        getHiddenInputIds(section, '[data-organization-recipient-inputs]').filter(function (id) {
            return selectedOrganizationIds.has(String(id));
        })
    );
}

function renderOrganizationsLedger(section) {
    const organizationPicker = getOrganizationPicker(section);
    const body = section.querySelector('[data-organizations-ledger]');
    const emptyState = section.querySelector('[data-organizations-summary-empty]');

    if (!organizationPicker || !body || !emptyState) {
        return;
    }

    const organizationLabels = parseJson(section.dataset.organizationLabels, {});
    const organizationPoNumbers = parseJson(section.dataset.organizationPoNumbers, {});
    const selectedOrganizationKfsNumbers = getStoredOrganizationKfsSelections(section);
    const selectedPayorSourceIds = new Set(getHiddenInputIds(section, '[data-organization-payor-source-inputs]'));
    const selectedRecipientIds = new Set(getHiddenInputIds(section, '[data-organization-recipient-inputs]'));
    const selectedOrganizationIds = getSelectedIdsFromTokenPicker(organizationPicker);
    const agreementKfsNumbers = getAgreementKfsNumbers(section);
    const currentOrganizationKfsSelections = Object.assign({}, selectedOrganizationKfsNumbers);

    Object.keys(currentOrganizationKfsSelections).forEach(function (organizationId) {
        if (!selectedOrganizationIds.includes(String(organizationId))) {
            delete currentOrganizationKfsSelections[organizationId];
            return;
        }

        currentOrganizationKfsSelections[organizationId] = currentOrganizationKfsSelections[organizationId].filter(function (number) {
            return agreementKfsNumbers.includes(String(number));
        });
    });

    setStoredOrganizationKfsSelections(section, currentOrganizationKfsSelections);

    disposeTooltips(body);
    body.innerHTML = '';

    function createRemoveButton(organizationId) {
        const button = document.createElement('button');
        button.type = 'button';
        button.className = 'btn btn-sm btn-link text-danger text-decoration-none p-0 lh-1 fs-4';
        button.innerHTML = '&times;';
        button.setAttribute('aria-label', 'Remove organization');
        button.setAttribute('data-bs-toggle', 'tooltip');
        button.setAttribute('data-bs-title', 'Remove organization');
        button.addEventListener('click', function () {
            disposeTooltips(body);
            const nextOrganizationIds = getSelectedIdsFromTokenPicker(organizationPicker).filter(function (value) {
                return value !== String(organizationId);
            });

            setSelectedIdsOnTokenPicker(organizationPicker, nextOrganizationIds);
        });

        return button;
    }

    function createClassificationToggle(organizationId, label, isChecked, onChange) {
        const wrapper = document.createElement('label');
        wrapper.className = 'form-check form-switch m-0 d-inline-flex align-items-center gap-2';

        const input = document.createElement('input');
        input.className = 'form-check-input mt-0';
        input.type = 'checkbox';
        input.checked = isChecked;
        input.setAttribute('aria-label', label);
        input.addEventListener('change', onChange);

        const text = document.createElement('span');
        text.className = 'small text-muted';
        text.textContent = label;

        wrapper.appendChild(input);
        wrapper.appendChild(text);

        return wrapper;
    }

    function createOrganizationLabel(organizationId) {
        const wrap = document.createElement('div');
        wrap.className = 'd-grid gap-1 min-w-0';

        const title = document.createElement('div');
        title.className = 'fw-semibold small';
        title.textContent = organizationLabels[String(organizationId)] || ('Organization ' + organizationId);
        wrap.appendChild(title);

        const poNumber = organizationPoNumbers[String(organizationId)];

        if (poNumber) {
            const meta = document.createElement('div');
            meta.className = 'small text-muted';
            meta.textContent = 'PO: ' + poNumber;
            wrap.appendChild(meta);
        }

        return wrap;
    }

    function createKfsTokenButton(organizationId, number, isSelected) {
        const button = document.createElement('button');
        button.type = 'button';
        button.className = isSelected
            ? 'badge app-tag d-inline-flex align-items-center px-3 py-2 rounded-pill'
            : 'badge bg-white text-body border d-inline-flex align-items-center px-3 py-2 rounded-pill';
        button.textContent = number;
        button.style.cursor = 'pointer';
        button.setAttribute('aria-pressed', isSelected ? 'true' : 'false');
        button.addEventListener('click', function () {
            const nextSelections = currentOrganizationKfsSelections[String(organizationId)]
                ? currentOrganizationKfsSelections[String(organizationId)].slice()
                : [];
            const nextSet = new Set(nextSelections.map(function (value) {
                return String(value);
            }));

            if (nextSet.has(String(number))) {
                nextSet.delete(String(number));
            } else {
                nextSet.add(String(number));
            }

            currentOrganizationKfsSelections[String(organizationId)] = Array.from(nextSet);
            setStoredOrganizationKfsSelections(section, currentOrganizationKfsSelections);
            renderOrganizationsLedger(section);
        });

        return button;
    }

    selectedOrganizationIds.forEach(function (organizationId) {
        const card = document.createElement('div');
        card.className = 'border rounded overflow-hidden bg-body';

        const row = document.createElement('div');
        row.className = 'd-flex justify-content-between align-items-start gap-3 px-3 pt-2 pb-1';

        const titleWrap = document.createElement('div');
        titleWrap.className = 'min-w-0';
        titleWrap.appendChild(createOrganizationLabel(organizationId));

        const actions = document.createElement('div');
        actions.className = 'd-flex align-items-center gap-3 flex-shrink-0';

        actions.appendChild(createClassificationToggle(
            organizationId,
            'Payor source',
            selectedPayorSourceIds.has(String(organizationId)),
            function (event) {
                const nextIds = new Set(getHiddenInputIds(section, '[data-organization-payor-source-inputs]'));

                if (event.target.checked) {
                    nextIds.add(String(organizationId));
                } else {
                    nextIds.delete(String(organizationId));
                }

                setHiddenInputIds(
                    section,
                    '[data-organization-payor-source-inputs]',
                    'organization_payor_source_ids[]',
                    Array.from(nextIds)
                );

                renderOrganizationsLedger(section);
            }
        ));

        actions.appendChild(createClassificationToggle(
            organizationId,
            'Recipient',
            selectedRecipientIds.has(String(organizationId)),
            function (event) {
                const nextIds = new Set(getHiddenInputIds(section, '[data-organization-recipient-inputs]'));

                if (event.target.checked) {
                    nextIds.add(String(organizationId));
                } else {
                    nextIds.delete(String(organizationId));
                }

                setHiddenInputIds(
                    section,
                    '[data-organization-recipient-inputs]',
                    'organization_recipient_ids[]',
                    Array.from(nextIds)
                );
            }
        ));

        actions.appendChild(createRemoveButton(organizationId));

        row.appendChild(titleWrap);
        row.appendChild(actions);
        card.appendChild(row);

        if (selectedPayorSourceIds.has(String(organizationId))) {
            const kfsWrap = document.createElement('div');
            const divider = document.createElement('hr');
            const kfsSelected = currentOrganizationKfsSelections[String(organizationId)]
                || selectedOrganizationKfsNumbers[String(organizationId)]
                || [];

            kfsWrap.className = 'px-3 pb-3 pt-0';
            kfsWrap.setAttribute('data-organization-kfs-group', String(organizationId));
            divider.className = 'mt-1 mb-2';
            kfsWrap.appendChild(divider);

            if (agreementKfsNumbers.length === 0) {
                const empty = document.createElement('div');
                empty.className = 'small text-muted';
                empty.textContent = 'No KFS accounts are attached to this agreement yet.';
                kfsWrap.appendChild(empty);
            } else {
                const label = document.createElement('div');
                const tokenGrid = document.createElement('div');
                const hiddenInputWrap = document.createElement('div');

                label.className = 'small text-muted mb-2';
                label.textContent = 'Select KFS accounts for this payor organization';
                tokenGrid.className = 'd-flex flex-wrap gap-2';
                hiddenInputWrap.className = 'd-none';

                agreementKfsNumbers.forEach(function (number) {
                    const isSelected = kfsSelected.includes(String(number));
                    tokenGrid.appendChild(createKfsTokenButton(organizationId, number, isSelected));

                    if (isSelected) {
                        const hiddenInput = document.createElement('input');
                        hiddenInput.type = 'hidden';
                        hiddenInput.name = 'organization_kfs_numbers[' + organizationId + '][]';
                        hiddenInput.value = number;
                        hiddenInput.setAttribute('data-organization-kfs-input', 'true');
                        hiddenInputWrap.appendChild(hiddenInput);
                    }
                });

                kfsWrap.appendChild(label);
                kfsWrap.appendChild(tokenGrid);
                kfsWrap.appendChild(hiddenInputWrap);
            }

            card.appendChild(kfsWrap);
        }

        body.appendChild(card);
    });

    emptyState.classList.toggle('d-none', selectedOrganizationIds.length > 0);
    initTooltips(body);
}

function initializeOrganizationsSection(section) {
    if (section.dataset.organizationsSectionInitialized === 'true') {
        return;
    }

    const organizationPicker = getOrganizationPicker(section);

    if (!organizationPicker) {
        return;
    }

    const refresh = function () {
        syncClassificationSelections(section);
        renderOrganizationsLedger(section);
    };

    setHiddenInputIds(
        section,
        '[data-organization-payor-source-inputs]',
        'organization_payor_source_ids[]',
        parseJson(section.dataset.selectedPayorSourceIds, [])
    );
    setHiddenInputIds(
        section,
        '[data-organization-recipient-inputs]',
        'organization_recipient_ids[]',
        parseJson(section.dataset.selectedRecipientIds, [])
    );

    organizationPicker.addEventListener('token-picker:change', refresh);
    section.addEventListener('agreement-scope:change', refresh);
    document.addEventListener('agreement-kfs:change', refresh);

    refresh();
    section.dataset.organizationsSectionInitialized = 'true';
}

document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('[data-agreement-organizations-section]').forEach(function (section) {
        initializeOrganizationsSection(section);
    });
});
