@php
    $agreement = $agreement ?? null;
    $selectedStateIds = $selectedStateIds ?? [];
    $selectedOrganizationIds = old('organization_ids', $agreement?->organizations?->pluck('id')->toArray() ?? []);

    $selectedPayorSourceIds = old(
        'organization_payor_source_ids',
        $agreement?->organizations
            ?->filter(fn ($organization) => (bool) $organization->pivot->payor_source)
            ->pluck('id')
            ->toArray() ?? []
    );

    $selectedRecipientIds = old(
        'organization_recipient_ids',
        $agreement?->organizations
            ?->filter(fn ($organization) => (bool) $organization->pivot->recipient)
            ->pluck('id')
            ->toArray() ?? []
    );

    $organizationOptions = $organizations->map(function ($organization) {
        return [
            'value' => $organization->id,
            'label' => $organization->name,
            'search' => trim($organization->name . ' ' . ($organization->kfs_number ?? '')),
        ];
    });

    $organizationLabels = $organizations->pluck('name', 'id');
    $organizationKfsNumbers = $organizations->mapWithKeys(function ($organization) {
        return [(string) $organization->id => $organization->kfs_number];
    });
@endphp

<div class="mb-4">
    <div class="row g-4 align-items-stretch">
        <div class="col-lg-5 d-flex">
            <div class="d-flex flex-column w-100 h-100">
                <div>
                    <label class="form-label">Organizations</label>

                    <x-token-picker
                        picker-id="agreement-organizations"
                        name="organization_ids[]"
                        :options="$organizationOptions"
                        :selected-ids="$selectedOrganizationIds"
                        label-key="label"
                        value-key="value"
                        search-key="search"
                        placeholder="Search organizations..."
                        disabled-placeholder="Select at least one program and state first..."
                        :disabled="empty($selectedProgramIds) || empty($selectedStateIds)"
                        :open-on-focus="false"
                        :show-selected="false"
                        :height="'300px'"
                    />

                    <small class="text-muted d-block mt-2">
                        Available organizations must be linked to at least one selected program and one selected state. Classify each organization in the ledger.
                    </small>

                    @error('organization_ids')
                        <div class="text-danger small mt-2">{{ $message }}</div>
                    @enderror
                    @error('organization_payor_source_ids')
                        <div class="text-danger small mt-1">{{ $message }}</div>
                    @enderror
                    @error('organization_recipient_ids')
                        <div class="text-danger small mt-1">{{ $message }}</div>
                    @enderror
                </div>
            </div>
        </div>

        <div class="col-lg-7">
            <div class="border rounded overflow-hidden d-flex flex-column h-100" style="min-height: 300px; background-color: #e9ecef;">
                <div class="small text-muted px-3 py-2 border-bottom bg-body">
                    Selected organizations
                </div>

                <div class="flex-grow-1 overflow-auto" style="min-height: 0;">
                    <div class="m-3 mt-2 mb-2" data-agreement-organizations-section
                         data-organization-picker-id="agreement-organizations"
                         data-selected-payor-source-ids='@json(array_values(array_map("strval", $selectedPayorSourceIds)))'
                         data-selected-recipient-ids='@json(array_values(array_map("strval", $selectedRecipientIds)))'
                         data-organization-labels='@json($organizationLabels)'
                         data-organization-kfs-numbers='@json($organizationKfsNumbers)'>
                        <div data-organization-payor-source-inputs></div>
                        <div data-organization-recipient-inputs></div>
                        <div class="d-grid gap-2" data-organizations-ledger></div>

                        <div class="text-muted small py-3 d-none" data-organizations-summary-empty>
                            No organizations have been added yet.
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

@once
<script>
(function () {
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
        const organizationKfsNumbers = parseJson(section.dataset.organizationKfsNumbers, {});
        const selectedPayorSourceIds = new Set(getHiddenInputIds(section, '[data-organization-payor-source-inputs]'));
        const selectedRecipientIds = new Set(getHiddenInputIds(section, '[data-organization-recipient-inputs]'));
        const selectedOrganizationIds = getSelectedIdsFromTokenPicker(organizationPicker);

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

        selectedOrganizationIds.forEach(function (organizationId) {
            const card = document.createElement('div');
            card.className = 'border rounded overflow-hidden bg-body';

            const row = document.createElement('div');
            row.className = 'd-flex justify-content-between align-items-start gap-3 px-3 py-2';

            const titleWrap = document.createElement('div');
            titleWrap.className = 'min-w-0';

            const title = document.createElement('div');
            title.className = 'fw-semibold small';
            title.textContent = organizationLabels[String(organizationId)] || ('Organization ' + organizationId);
            titleWrap.appendChild(title);

            const kfsNumber = organizationKfsNumbers[String(organizationId)];
            if (kfsNumber) {
                const kfs = document.createElement('div');
                kfs.className = 'small text-muted';
                kfs.textContent = kfsNumber;
                titleWrap.appendChild(kfs);
            }

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

        refresh();
        section.dataset.organizationsSectionInitialized = 'true';
    }

    document.addEventListener('DOMContentLoaded', function () {
        document.querySelectorAll('[data-agreement-organizations-section]').forEach(function (section) {
            initializeOrganizationsSection(section);
        });
    });
})();
</script>
@endonce
