@props([
    'formMode' => 'create',
    'agreements',
    'organizations',
    'states',
    'contactFamilies',
    'currentContactFamilyId' => null,
    'selectedAgreementIds' => [],
    'selectedOrganizationIds' => [],
    'selectedStateIds' => [],
    'selectedActivityTypeId' => null,
    'agreementLoggingData' => [],
    'contactFamilyLoggingData' => [],
    'engagementDateValue' => null,
    'internalOnlyChecked' => false,
    'activity' => null,
])

@php
    $agreementConfigs = $agreements->mapWithKeys(function ($agreement) {
        $deliverables = $agreement->deliverables ?? collect();

        return [
            $agreement->id => [
                'organization_ids' => $agreement->organizations->pluck('id')->map(fn($id) => (string) $id)->values()->all(),
                'state_ids' => $agreement->states->pluck('id')->map(fn($id) => (string) $id)->values()->all(),
                'contact_family_ids' => $deliverables
                    ->flatMap(function ($deliverable) {
                        $ids = [];

                        if ($deliverable->contact_family_id) {
                            $ids[] = (string) $deliverable->contact_family_id;
                        }

                        if ($deliverable->activityType?->contact_family_id) {
                            $ids[] = (string) $deliverable->activityType->contact_family_id;
                        }

                        return $ids;
                    })
                    ->unique()
                    ->values()
                    ->all(),
            ]
        ];
    });

    $isEditMode = $formMode === 'edit';
    $pageTitle = $isEditMode ? 'Edit Activity' : 'Log Activity';
    $pageSubtitle = $isEditMode
        ? 'Fast update mode for existing records.'
        : 'Fast entry mode for daily operational logging.';
    $formId = $isEditMode ? 'activity-edit-form' : 'activity-create-form';
    $formAction = $isEditMode ? route('activities.update', $activity) : route('activities.store');
    $saveStatusDefault = $isEditMode ? 'Saved' : 'Ready';
    $agreementsWithLoggingFields = $agreements->filter(fn ($agreement) => $agreement->agreementLoggingFields->isNotEmpty())->values();
@endphp

<div class="container-fluid py-4">
    <div class="row g-4 mb-2">
        <div class="col-12">
            <h1 class="h3 mb-1">{{ $pageTitle }}</h1>
            <p class="text-muted mb-0">{{ $pageSubtitle }}</p>
        </div>
    </div>

    @if ($errors->any())
        <div class="alert alert-danger py-2">
            <strong>Please fix the highlighted fields.</strong>
        </div>
    @endif

    <form method="POST" action="{{ $formAction }}" id="{{ $formId }}" enctype="multipart/form-data">
        @csrf
        @if ($isEditMode)
            @method('PUT')
        @endif

        <div class="row g-4">
            <div class="col-lg-8">
                <div class="d-grid gap-4">
                    <x-section-card title="Agreements & Coverage">
                        <div class="mb-3">
                            <label class="form-label fw-semibold">Agreements</label>
                            <x-token-picker
                                picker-id="activity-agreements-picker"
                                name="agreement_ids[]"
                                :items="$agreements"
                                :selected-ids="$selectedAgreementIds"
                                placeholder="Search agreements..."
                                empty-message="No agreements match your search."
                                :open-on-focus="false"
                            />
                            @error('agreement_ids')
                                <div class="text-danger small mt-1">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label">Organizations</label>
                                <x-token-picker
                                    picker-id="activity-organizations-picker"
                                    name="organization_ids[]"
                                    :items="$organizations"
                                    :selected-ids="$selectedOrganizationIds"
                                    placeholder="Search organizations..."
                                    :open-on-focus="false"
                                />
                                @error('organization_ids')
                                    <div class="text-danger small mt-1">{{ $message }}</div>
                                @enderror
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">States</label>
                                <x-token-picker
                                    picker-id="activity-states-picker"
                                    name="state_ids[]"
                                    :items="$states"
                                    :selected-ids="$selectedStateIds"
                                    placeholder="Search states..."
                                    :open-on-focus="false"
                                />
                                @error('state_ids')
                                    <div class="text-danger small mt-1">{{ $message }}</div>
                                @enderror
                            </div>
                        </div>
                    </x-section-card>

                    <x-section-card title="Activity Classification">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label for="contact_family_id" class="form-label fw-semibold">
                                    Contact Family <span class="text-danger">*</span>
                                </label>
                                <select class="form-select @error('contact_family_id') is-invalid @enderror"
                                        id="contact_family_id"
                                        name="contact_family_id"
                                        hx-get="{{ route('activity-types.by-family') }}"
                                        hx-target="#activity_type_id"
                                        hx-swap="innerHTML"
                                    hx-include="#{{ $formId }}, #activity_type_selected"
                                        hx-trigger="change, load"
                                        required>
                                    <option value="">Select contact family...</option>
                                    @foreach($contactFamilies as $family)
                                        <option value="{{ $family->id }}" {{ (string) $currentContactFamilyId === (string) $family->id ? 'selected' : '' }}>
                                            {{ $family->name }}
                                        </option>
                                    @endforeach
                                </select>
                                <input type="hidden" id="activity_type_selected" value="{{ $selectedActivityTypeId }}">
                                @error('contact_family_id')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>

                            <div class="col-md-6">
                                <label for="activity_type_id" class="form-label fw-semibold">
                                    Activity Type <span class="text-danger">*</span>
                                </label>
                                <select class="form-select @error('activity_type_id') is-invalid @enderror"
                                        id="activity_type_id"
                                        name="activity_type_id"
                                        {{ $currentContactFamilyId ? '' : 'disabled' }}
                                        required>
                                    <option value="">Select contact family first...</option>
                                </select>
                                @error('activity_type_id')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                        </div>

                        <div class="mt-4">
                            @foreach($contactFamilies as $family)
                                <div class="border rounded p-3 d-none" data-contact-family-logging-group="{{ $family->id }}">
                                    <div class="fw-semibold mb-2">{{ $family->name }} Logging Fields</div>

                                    @if($family->contactFamilyLoggingFields->isEmpty())
                                        <div class="text-muted small">No classification logging fields are assigned to this contact family.</div>
                                    @else
                                        <div class="row g-3">
                                            @foreach($family->contactFamilyLoggingFields as $field)
                                                <div class="{{ $field->is_full_width ? 'col-12' : 'col-md-6' }}">
                                                    @include('activities.partials.logging-field-input', [
                                                        'field' => $field,
                                                        'inputName' => "contact_family_logging_values[{$field->id}]",
                                                        'oldKey' => "contact_family_logging_values.{$field->id}",
                                                        'value' => data_get($contactFamilyLoggingData, (string) $field->id),
                                                        'inputId' => "contact_family_field_{$field->id}",
                                                        'isRequired' => (bool) $field->pivot->is_required,
                                                    ])
                                                </div>
                                            @endforeach
                                        </div>
                                    @endif
                                </div>
                            @endforeach
                        </div>
                    </x-section-card>

                    <div id="agreement-logging-section" class="{{ $agreementsWithLoggingFields->isEmpty() ? 'd-none' : '' }}">
                        <x-section-card title="Agreement Logging Fields">
                            <div id="agreement-logging-groups" class="d-grid gap-3">
                            @foreach($agreementsWithLoggingFields as $agreement)
                                <div class="border rounded p-3 d-none" data-agreement-logging-group="{{ $agreement->id }}">
                                    <div class="d-flex justify-content-between align-items-center mb-3">
                                        <div>
                                            <h5 class="mb-1">{{ $agreement->name }}</h5>
                                            <p class="small text-muted mb-0">Agreement-level logging fields</p>
                                        </div>
                                    </div>

                                    <div class="row g-3">
                                        @foreach($agreement->agreementLoggingFields as $field)
                                            <div class="{{ $field->is_full_width ? 'col-12' : 'col-md-6' }}">
                                                @include('activities.partials.logging-field-input', [
                                                    'field' => $field,
                                                    'inputName' => "agreement_logging_values[{$agreement->id}][{$field->id}]",
                                                    'oldKey' => "agreement_logging_values.{$agreement->id}.{$field->id}",
                                                    'value' => data_get($agreementLoggingData, "{$agreement->id}.{$field->id}"),
                                                    'inputId' => "agreement_{$agreement->id}_field_{$field->id}",
                                                    'isRequired' => (bool) $field->pivot->is_required,
                                                ])
                                            </div>
                                        @endforeach
                                    </div>
                                </div>
                            @endforeach
                            </div>
                        </x-section-card>
                    </div>
                </div>
            </div>

            <div class="col-lg-4">
                <div class="d-grid gap-4">
                    <x-section-card title="Details">
                        <div class="mb-3">
                            <label for="engagement_date" class="form-label fw-semibold">Date <span class="text-danger">*</span></label>
                            <input type="date"
                                   class="form-control @error('engagement_date') is-invalid @enderror"
                                   id="engagement_date"
                                   name="engagement_date"
                                   value="{{ $engagementDateValue }}"
                                   required>
                            @error('engagement_date')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="form-check form-switch mt-3 mb-3">
                            <input class="form-check-input"
                                   type="checkbox"
                                   id="internal_only"
                                   name="internal_only"
                                   value="1"
                                   {{ $internalOnlyChecked ? 'checked' : '' }}>
                            <label class="form-check-label" for="internal_only">
                                Internal only
                                <small class="text-muted d-block mt-1">Exclude this activity from external reports.</small>
                            </label>
                        </div>
                    </x-section-card>

                    @unless($isEditMode)
                        @include('activities.partials.recent-templates')
                    @endunless

                    <x-section-card title="Save Status" subtitle="Live status for unsaved/saving states.">
                        <div id="activity-save-status" class="small text-muted">{{ $saveStatusDefault }}</div>
                    </x-section-card>
                </div>
            </div>
        </div>
    </form>
</div>

<div id="activity-save-bar" class="position-fixed bottom-0 start-0 end-0 bg-white border-top shadow-lg" style="z-index: 1050;">
    <div class="container-fluid py-2 d-flex align-items-center justify-content-between gap-2">
        <div id="activity-save-bar-status" class="small text-muted">{{ $saveStatusDefault }}</div>
        <div class="d-flex align-items-center gap-2 flex-wrap justify-content-end">
            <a href="{{ route('activities.index') }}" class="btn btn-sm btn-outline-secondary">Cancel</a>
            <button type="submit" class="btn btn-sm btn-primary" form="{{ $formId }}" name="save_mode" value="save">Save Activity</button>
            <button type="submit" class="btn btn-sm btn-outline-primary" form="{{ $formId }}" name="save_mode" value="save_new">Save + New</button>
            <button type="submit" class="btn btn-sm btn-outline-primary" form="{{ $formId }}" name="save_mode" value="save_duplicate">Save + Duplicate</button>
        </div>
    </div>
</div>
<div style="height: 72px;"></div>

<script>
(function () {
    const agreementConfigs = @json($agreementConfigs);
    const form = document.getElementById(@json($formId));
    const statusTop = document.getElementById('activity-save-status');
    const statusBar = document.getElementById('activity-save-bar-status');
    const hasErrors = @json($errors->any());
    const isEditMode = @json($isEditMode);

    if (!form) return;

    function setStatus(html) {
        statusTop.innerHTML = html;
        statusBar.innerHTML = html;
    }

    function selectedValues(name) {
        return Array.from(form.querySelectorAll('input[type="hidden"][name="' + name + '"]')).map(i => i.value);
    }

    function firstSelectedAgreementId() {
        return selectedValues('agreement_ids[]')[0] || null;
    }

    function selectedAgreementConfigs() {
        return selectedValues('agreement_ids[]').map(function (agreementId) {
            return agreementConfigs[agreementId] || null;
        }).filter(Boolean);
    }

    function uniqueMergedIds(key) {
        const merged = new Set();

        selectedAgreementConfigs().forEach(function (config) {
            (config[key] || []).forEach(function (value) {
                merged.add(String(value));
            });
        });

        return Array.from(merged);
    }

    function restrictCoveragePickers() {
        const agreements = selectedValues('agreement_ids[]');
        const orgPicker = document.getElementById('activity-organizations-picker');
        const statePicker = document.getElementById('activity-states-picker');

        if (!agreements.length) {
            orgPicker?.dispatchEvent(new CustomEvent('token-picker:restrict', { detail: null }));
            statePicker?.dispatchEvent(new CustomEvent('token-picker:restrict', { detail: null }));
            return;
        }

        orgPicker?.dispatchEvent(new CustomEvent('token-picker:restrict', { detail: uniqueMergedIds('organization_ids') }));
        statePicker?.dispatchEvent(new CustomEvent('token-picker:restrict', { detail: uniqueMergedIds('state_ids') }));
    }

    function restrictClassificationOptions() {
        const family = document.getElementById('contact_family_id');
        const type = document.getElementById('activity_type_id');
        const selectedType = document.getElementById('activity_type_selected');
        const agreements = selectedValues('agreement_ids[]');

        if (!family || !type) return;

        const hasAgreementSelection = agreements.length > 0;
        const allowedFamilyIds = new Set(uniqueMergedIds('contact_family_ids'));
        const hasRestriction = hasAgreementSelection && allowedFamilyIds.size > 0;

        Array.from(family.options).forEach(function (option) {
            if (!option.value) {
                option.disabled = false;
                option.hidden = false;
                option.textContent = hasAgreementSelection && allowedFamilyIds.size === 0
                    ? 'No deliverable contact families for selected agreements...'
                    : 'Select contact family...';
                return;
            }

            const allowed = !hasRestriction || allowedFamilyIds.has(String(option.value));
            option.disabled = !allowed;
            option.hidden = !allowed;
        });

        if (hasRestriction && family.value && !allowedFamilyIds.has(String(family.value))) {
            family.value = '';
            if (selectedType) {
                selectedType.value = '';
            }
        }

        if (hasAgreementSelection && allowedFamilyIds.size === 0) {
            family.value = '';
            if (selectedType) {
                selectedType.value = '';
            }
            type.innerHTML = '<option value="">No activity types available for selected agreements...</option>';
        }

        updateActivityTypeState();
    }

    function refreshActivityTypes() {
        const family = document.getElementById('contact_family_id');
        const type = document.getElementById('activity_type_id');

        if (!family || !type) return;

        if (!family.value) {
            type.innerHTML = '<option value="">Select contact family first...</option>';
            type.disabled = true;
            return;
        }

        htmx.trigger(family, 'change');
    }

    function updateAgreementAutoFill() {
        const agreementId = firstSelectedAgreementId();
        const config = agreementConfigs[agreementId] || {};

        if (agreementId && config.organization_ids) {
            const orgPicker = document.getElementById('activity-organizations-picker');
            if (orgPicker && !selectedValues('organization_ids[]').length) {
                orgPicker.dispatchEvent(new CustomEvent('token-picker:set', { detail: config.organization_ids }));
            }
        }
        if (agreementId && config.state_ids) {
            const statePicker = document.getElementById('activity-states-picker');
            if (statePicker && !selectedValues('state_ids[]').length) {
                statePicker.dispatchEvent(new CustomEvent('token-picker:set', { detail: config.state_ids }));
            }
        }
    }

    function updateAgreementLoggingGroups() {
        const selected = new Set(selectedValues('agreement_ids[]'));
        const section = document.getElementById('agreement-logging-section');
        let visibleGroups = 0;

        document.querySelectorAll('[data-agreement-logging-group]').forEach(function (group) {
            const visible = selected.has(group.dataset.agreementLoggingGroup);
            group.classList.toggle('d-none', !visible);
            group.querySelectorAll('input, textarea, select').forEach(function (field) {
                field.disabled = !visible;
            });
            visibleGroups += visible ? 1 : 0;
        });

        section?.classList.toggle('d-none', visibleGroups === 0);
    }

    function updateContactFamilyLoggingGroups() {
        const familyId = document.getElementById('contact_family_id')?.value;

        document.querySelectorAll('[data-contact-family-logging-group]').forEach(function (group) {
            const visible = group.dataset.contactFamilyLoggingGroup === familyId;
            group.classList.toggle('d-none', !visible);
            group.querySelectorAll('input, textarea, select').forEach(function (field) {
                field.disabled = !visible;
            });
        });
    }

    function updateActivityTypeState() {
        const family = document.getElementById('contact_family_id');
        const type = document.getElementById('activity_type_id');
        if (!family || !type) return;
        type.disabled = !family.value;
    }

    function markDirty() {
        setStatus('<span class="text-warning fw-semibold">● Unsaved changes</span>');
    }

    function saveRecentTemplate() {
        const firstAgreementValue = selectedValues('agreement_ids[]')[0] || 'Template';
        const firstAgreementLabel = document.querySelector('#activity-agreements-picker [data-token-selected] span')?.textContent || firstAgreementValue;

        const payload = {
            name: firstAgreementLabel + ' · ' + new Date().toLocaleDateString(),
            agreement_ids: selectedValues('agreement_ids[]'),
            organization_ids: selectedValues('organization_ids[]'),
            state_ids: selectedValues('state_ids[]'),
            contact_family_id: document.getElementById('contact_family_id')?.value || '',
            activity_type_id: document.getElementById('activity_type_id')?.value || '',
            internal_only: document.getElementById('internal_only')?.checked ? 1 : 0,
        };

        const key = 'activity.recent.templates.v1';
        const current = JSON.parse(localStorage.getItem(key) || '[]');
        const next = [payload, ...current].slice(0, 5);
        localStorage.setItem(key, JSON.stringify(next));
    }

    function renderTemplates() {
        const key = 'activity.recent.templates.v1';
        const container = document.getElementById('activity-recent-templates');
        if (!container) return;

        const templates = JSON.parse(localStorage.getItem(key) || '[]');
        container.innerHTML = '';

        if (!templates.length) {
            container.innerHTML = '<span class="text-muted small">No recent templates yet.</span>';
            return;
        }

        templates.forEach(function (template, index) {
            const button = document.createElement('button');
            button.type = 'button';
            button.className = 'btn btn-sm btn-outline-secondary';
            button.textContent = template.name || ('Template ' + (index + 1));
            button.addEventListener('click', function () {
                form.querySelector('#activity-agreements-picker')?.dispatchEvent(new CustomEvent('token-picker:set', { detail: template.agreement_ids || [] }));
                form.querySelector('#activity-organizations-picker')?.dispatchEvent(new CustomEvent('token-picker:set', { detail: template.organization_ids || [] }));
                form.querySelector('#activity-states-picker')?.dispatchEvent(new CustomEvent('token-picker:set', { detail: template.state_ids || [] }));

                const family = document.getElementById('contact_family_id');
                const selectedType = document.getElementById('activity_type_selected');
                const internalOnly = document.getElementById('internal_only');

                if (family) family.value = template.contact_family_id || '';
                if (selectedType) selectedType.value = template.activity_type_id || '';
                if (internalOnly) internalOnly.checked = !!template.internal_only;

                if (family) htmx.trigger(family, 'change');

                markDirty();
                updateAgreementLoggingGroups();
            });
            container.appendChild(button);
        });
    }

    const agreementsPicker = document.getElementById('activity-agreements-picker');
    if (agreementsPicker) {
        agreementsPicker.addEventListener('token-picker:change', function () {
            restrictCoveragePickers();
            restrictClassificationOptions();
            refreshActivityTypes();
            updateAgreementAutoFill();
            updateAgreementLoggingGroups();
            markDirty();
        });
        agreementsPicker.addEventListener('token-picker:initialized', function () {
            restrictCoveragePickers();
            restrictClassificationOptions();
            refreshActivityTypes();
            if (!isEditMode) {
                updateAgreementAutoFill();
            }
            updateAgreementLoggingGroups();
        });
    }

    ['activity-organizations-picker', 'activity-states-picker'].forEach(function (id) {
        document.getElementById(id)?.addEventListener('token-picker:change', markDirty);
    });

    form.addEventListener('input', markDirty);
    form.addEventListener('change', function (event) {
        if (event.target && event.target.id === 'contact_family_id') {
            const selectedType = document.getElementById('activity_type_selected');
            if (selectedType) selectedType.value = '';
            updateActivityTypeState();
            updateContactFamilyLoggingGroups();
        }
        markDirty();
    });

    document.getElementById('contact_family_id')?.addEventListener('htmx:afterRequest', function () {
        const selectedType = document.getElementById('activity_type_selected');
        const type = document.getElementById('activity_type_id');
        if (selectedType && type && selectedType.value) {
            type.value = selectedType.value;
        }
        if (type) {
            const availableOptions = Array.from(type.options).filter(function (option) {
                return option.value !== '';
            });

            if (!type.value && availableOptions.length === 1) {
                type.value = availableOptions[0].value;
            }
        }
        if (type) type.dispatchEvent(new Event('change', { bubbles: true }));
    });

    form.addEventListener('submit', function () {
        setStatus('<span class="text-secondary">Saving…</span>');
        if (!isEditMode) {
            saveRecentTemplate();
        }
    });

    if (hasErrors) {
        setStatus('<span class="text-danger">⚠ Fix errors above to save</span>');
    } else {
        setStatus(isEditMode ? '<span class="text-muted">Saved</span>' : '<span class="text-muted">Ready</span>');
    }

    const family = document.getElementById('contact_family_id');
    if (family && family.value) htmx.trigger(family, 'change');

    updateActivityTypeState();
    restrictCoveragePickers();
    restrictClassificationOptions();
    updateAgreementLoggingGroups();
    updateContactFamilyLoggingGroups();

    if (!isEditMode) {
        renderTemplates();
    }
})();
</script>
