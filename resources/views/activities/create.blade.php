@extends('layouts.app')

@section('title', 'Log Activity')

@section('content')

@php
    // Map agreement and contact family logging fields for JavaScript
    $agreementLoggingFields = $agreements->mapWithKeys(function ($agreement) {
        return [
            $agreement->id => $agreement->loggingFields->map(function ($field) {
                return [
                    'id' => $field->id,
                    'name' => $field->name,
                    'slug' => $field->slug,
                    'field_type' => $field->field_type,
                    'help_text' => $field->help_text,
                    'options_json' => $field->options_json,
                    'is_required' => $field->pivot->is_required,
                ];
            })->values()
        ];
    });
    
    $contactFamilyLoggingFields = $contactFamilies->mapWithKeys(function ($family) {
        return [
            $family->id => $family->loggingFields->map(function ($field) {
                return [
                    'id' => $field->id,
                    'name' => $field->name,
                    'slug' => $field->slug,
                    'field_type' => $field->field_type,
                    'help_text' => $field->help_text,
                    'options_json' => $field->options_json,
                    'is_required' => $field->pivot->is_required,
                ];
            })->values()
        ];
    });

    $prefill = $duplicateData ?? [];
    $selectedAgreementIds = old('agreement_ids', $prefill['agreement_ids'] ?? ($preselectedAgreementId ? [$preselectedAgreementId] : []));
    $selectedOrganizationIds = old('organization_ids', $prefill['organization_ids'] ?? []);
    $selectedStateIds = old('state_ids', $prefill['state_ids'] ?? []);
    $selectedProgramIds = old('program_ids', $prefill['program_ids'] ?? []);
    $selectedActivityTypeId = old('activity_type_id', $prefill['activity_type_id'] ?? null);
@endphp

<div class="container-fluid py-4">
    <div class="row g-4 mb-2">
        <div class="col-12">
            <h1 class="h3 mb-1">Log Activity</h1>
            <p class="text-muted mb-0">Fast entry mode for daily operational logging.</p>
        </div>
    </div>

    @if ($errors->any())
        <div class="alert alert-danger py-2">
            <strong>Please fix the highlighted fields.</strong>
        </div>
    @endif

    <form method="POST" action="{{ route('activities.store') }}" id="activity-create-form">
        @csrf

        <div class="row g-4">
            <div class="col-lg-8">
                <div class="d-grid gap-4">
                    <x-section-card title="1) Agreements & Coverage" subtitle="Select agreements first — organizations and states will auto-populate.">
                        <div class="mb-3">
                            <label class="form-label fw-semibold">
                                Agreements
                                <span class="badge bg-info text-white ms-2">Start here</span>
                            </label>
                            <x-token-picker
                                picker-id="activity-agreements-picker"
                                name="agreement_ids[]"
                                :items="$agreements"
                                :selected-ids="$selectedAgreementIds"
                                placeholder="Search agreements..."
                                empty-message="No agreements match your search."
                            />
                            <small class="text-muted d-block mt-1">
                                💡 Leave empty for internal-only activities (meetings, admin work, etc.)
                            </small>
                            @error('agreement_ids')
                                <div class="text-danger small mt-1">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="row g-3" 
                             id="org-state-container"
                             hx-get="{{ route('activities.orgs-states-for-agreements') }}"
                             hx-trigger="load, token-picker:change from:#activity-agreements-picker"
                             hx-swap="innerHTML"
                             hx-indicator="#htmx-loading-indicator">
                            {{-- Dynamic organization and state pickers loaded via HTMX --}}
                            @include('activities.partials.org-state-pickers', [
                                'agreementIds' => $selectedAgreementIds,
                                'selectedOrganizationIds' => $selectedOrganizationIds,
                                'selectedStateIds' => $selectedStateIds,
                            ])
                        </div>
                        
                        {{-- Loading indicator --}}
                        <div id="htmx-loading-indicator" class="htmx-indicator">
                            <div class="spinner-border spinner-border-sm text-primary mt-2" role="status">
                                <span class="visually-hidden">Loading...</span>
                            </div>
                            <small class="text-muted ms-2">Updating options...</small>
                        </div>
                    </x-section-card>

                    <x-section-card title="2) Activity Classification" subtitle="Contact family unlocks activity type.">
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
                                        hx-include="#contact_family_id, #activity_type_selected"
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
                    </x-section-card>

                    <x-section-card title="3) Internal Participants" subtitle="Select team members who participated.">
                        <div class="mb-3">
                            <label class="form-label">Internal Participants</label>
                            <div id="participants-container" class="border rounded p-2">
                                <small class="text-muted">Select an agreement first to load team members.</small>
                            </div>
                            @error('participant_user_ids')
                                <div class="text-danger small mt-1">{{ $message }}</div>
                            @enderror
                        </div>
                    </x-section-card>
                </div>
            </div>

            <div class="col-lg-4">
                <div class="d-grid gap-4">
                    <x-section-card title="4) Quick Details" subtitle="Core activity metadata.">
                        <div class="mb-3">
                            <label for="engagement_date" class="form-label fw-semibold">Date <span class="text-danger">*</span></label>
                            <input type="date"
                                   class="form-control @error('engagement_date') is-invalid @enderror"
                                   id="engagement_date"
                                   name="engagement_date"
                                   value="{{ old('engagement_date', $prefill['engagement_date'] ?? now()->format('Y-m-d')) }}"
                                   required>
                            @error('engagement_date')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <details class="mt-3">
                            <summary class="small text-muted">Programs (optional)</summary>
                            <div class="mt-2">
                                <x-token-picker
                                    picker-id="activity-programs-picker"
                                    name="program_ids[]"
                                    :items="$programs"
                                    :selected-ids="$selectedProgramIds"
                                    placeholder="Search programs..."
                                />
                                @error('program_ids')
                                    <div class="text-danger small mt-1">{{ $message }}</div>
                                @enderror
                            </div>
                        </details>

                        <div class="form-check form-switch mt-3 mb-3">
                            <input class="form-check-input"
                                   type="checkbox"
                                   id="internal_only"
                                   name="internal_only"
                                   value="1"
                                   {{ old('internal_only', $prefill['internal_only'] ?? false) ? 'checked' : '' }}>
                            <label class="form-check-label" for="internal_only">
                                Internal only
                                <small class="text-muted d-block mt-1">Exclude this activity from external reports.</small>
                            </label>
                        </div>
                    </x-section-card>

                    <!-- Dynamic Logging Fields: Single Source of Truth for Metrics -->
                    <x-section-card title="Activity Details" subtitle="Metrics and reporting fields from your selections." id="dynamic-logging-fields-card">
                        <div id="dynamic-logging-fields-container">
                            <p class="text-muted small mb-0">Select agreements and contact family above to see required fields.</p>
                        </div>
                    </x-section-card>

                    <x-section-card title="5) Time Tracking" subtitle="Choose how to track time for this activity.">
                        <fieldset>
                            <legend class="form-label fw-semibold mb-2">Time Tracking Method</legend>
                            
                            <div class="mb-3">
                                <div class="form-check">
                                    <input class="form-check-input"
                                           type="radio"
                                           id="time_tracking_engagement"
                                           name="time_tracking_mode"
                                           value="engagement"
                                           {{ old('time_tracking_mode', $prefill['time_tracking_mode'] ?? 'engagement') === 'engagement' ? 'checked' : '' }}>
                                    <label class="form-check-label" for="time_tracking_engagement">
                                        <strong>Time by Engagement</strong>
                                        <small class="text-muted d-block">One total time value for the entire activity.</small>
                                    </label>
                                </div>
                            </div>

                            <div class="mb-3">
                                <div class="form-check">
                                    <input class="form-check-input"
                                           type="radio"
                                           id="time_tracking_participant"
                                           name="time_tracking_mode"
                                           value="participant"
                                           {{ old('time_tracking_mode', $prefill['time_tracking_mode'] ?? 'engagement') === 'participant' ? 'checked' : '' }}>
                                    <label class="form-check-label" for="time_tracking_participant">
                                        <strong>Time by Participant</strong>
                                        <small class="text-muted d-block">Track individual time per team member.</small>
                                    </label>
                                </div>
                            </div>
                        </fieldset>

                        <!-- Participant times section (shown when participant mode is selected) -->
                        <div id="participant-times-section" class="d-none mt-4">
                            <hr class="my-3">
                            <label class="form-label fw-semibold mb-3">Participant Time Tracking</label>
                            
                            <x-participant-time-rows />

                            <div class="alert alert-info alert-sm mt-3" role="alert">
                                <small>Add each team member and their hours. Remove rows you don't need.</small>
                            </div>
                        </div>
                    </x-section-card>

                    @include('activities.partials.recent-templates')

                    <x-section-card title="Save Status" subtitle="Live status for unsaved/saving states.">
                        <div id="activity-save-status" class="small text-muted">Ready</div>
                    </x-section-card>
                </div>
            </div>
        </div>
    </form>
</div>

<div id="activity-save-bar" class="position-fixed bottom-0 start-0 end-0 bg-white border-top shadow-lg" style="z-index: 1050;">
    <div class="container-fluid py-2 d-flex align-items-center justify-content-between gap-2">
        <div id="activity-save-bar-status" class="small text-muted">Ready</div>
        <div class="d-flex align-items-center gap-2 flex-wrap justify-content-end">
            <a href="{{ route('activities.index') }}" class="btn btn-sm btn-outline-secondary">Cancel</a>
            <button type="submit" class="btn btn-sm btn-primary" form="activity-create-form" name="save_mode" value="save">Save Activity</button>
            <button type="submit" class="btn btn-sm btn-outline-primary" form="activity-create-form" name="save_mode" value="save_new">Save + New</button>
            <button type="submit" class="btn btn-sm btn-outline-primary" form="activity-create-form" name="save_mode" value="save_duplicate">Save + Duplicate</button>
        </div>
    </div>
</div>
<div style="height: 72px;"></div>

<script>
(function () {
    const agreementLoggingFields = @json($agreementLoggingFields);
    const contactFamilyLoggingFields = @json($contactFamilyLoggingFields);
    const participantsUrl = @json(route('activities.participants-for-agreement'));
    const form = document.getElementById('activity-create-form');
    const statusTop = document.getElementById('activity-save-status');
    const statusBar = document.getElementById('activity-save-bar-status');
    const hasErrors = @json($errors->any());
    const oldLoggingFieldData = @json(old('logging_field_data', $prefill['logging_field_data'] ?? []));

    // Global participant data for time tracking component
    window.activityParticipants = {};

    if (!form) return;

    // ============================================================================
    // Dynamic Logging Fields System
    // ============================================================================
    function calculateLoggingFieldsUnion() {
        const selectedAgreementIds = Array.from(document.querySelectorAll('input[name="agreement_ids[]"]:checked'))
            .map(el => parseInt(el.value));
        const selectedContactFamilyId = document.getElementById('contact_family_id')?.value;

        const fieldsMap = new Map(); // key: field.id, value: field with is_required aggregated

        // Collect fields from selected agreements
        selectedAgreementIds.forEach(agreementId => {
            const fields = agreementLoggingFields[agreementId] || [];
            fields.forEach(field => {
                if (fieldsMap.has(field.id)) {
                    // If any source marks it required, it's required
                    const existing = fieldsMap.get(field.id);
                    existing.is_required = existing.is_required || field.is_required;
                } else {
                    // Clone to avoid mutation
                    fieldsMap.set(field.id, { ...field });
                }
            });
        });

        // Collect fields from selected contact family
        if (selectedContactFamilyId) {
            const fields = contactFamilyLoggingFields[selectedContactFamilyId] || [];
            fields.forEach(field => {
                if (fieldsMap.has(field.id)) {
                    const existing = fieldsMap.get(field.id);
                    existing.is_required = existing.is_required || field.is_required;
                } else {
                    fieldsMap.set(field.id, { ...field });
                }
            });
        }

        return Array.from(fieldsMap.values());
    }

    function renderDynamicLoggingFields() {
        const fields = calculateLoggingFieldsUnion();
        const container = document.getElementById('dynamic-logging-fields-container');
        const card = document.getElementById('dynamic-logging-fields-card');

        if (!container || !card) return;

        // Always show the card
        card.style.display = 'block';

        if (fields.length === 0) {
            container.innerHTML = '<p class="text-muted small mb-0">Select agreements and contact family above to see required fields.</p>';
            return;
        }

        container.innerHTML = '';

        fields.forEach(field => {
            const fieldDiv = document.createElement('div');
            fieldDiv.className = 'mb-3';

            const label = document.createElement('label');
            label.className = 'form-label' + (field.is_required ? ' fw-semibold' : '');
            label.htmlFor = `logging_field_${field.slug}`;
            label.textContent = field.name;
            if (field.is_required) {
                const required = document.createElement('span');
                required.className = 'text-danger';
                required.textContent = ' *';
                label.appendChild(required);
            }

            fieldDiv.appendChild(label);

            // Render input based on field_type
            let input;
            const oldValue = oldLoggingFieldData[field.slug] ?? '';

            switch (field.field_type) {
                case 'number':
                    input = document.createElement('input');
                    input.type = 'number';
                    input.className = 'form-control';
                    input.id = `logging_field_${field.slug}`;
                    input.name = `logging_field_data[${field.slug}]`;
                    input.value = oldValue;
                    if (field.is_required) input.required = true;
                    break;

                case 'decimal':
                    input = document.createElement('input');
                    input.type = 'number';
                    input.step = '0.01';
                    input.className = 'form-control';
                    input.id = `logging_field_${field.slug}`;
                    input.name = `logging_field_data[${field.slug}]`;
                    input.value = oldValue;
                    if (field.is_required) input.required = true;
                    break;

                case 'text':
                    input = document.createElement('input');
                    input.type = 'text';
                    input.className = 'form-control';
                    input.id = `logging_field_${field.slug}`;
                    input.name = `logging_field_data[${field.slug}]`;
                    input.value = oldValue;
                    if (field.is_required) input.required = true;
                    break;

                case 'textarea':
                    input = document.createElement('textarea');
                    input.className = 'form-control';
                    input.rows = 3;
                    input.id = `logging_field_${field.slug}`;
                    input.name = `logging_field_data[${field.slug}]`;
                    input.value = oldValue;
                    if (field.is_required) input.required = true;
                    break;

                case 'checkbox':
                    const checkDiv = document.createElement('div');
                    checkDiv.className = 'form-check';
                    input = document.createElement('input');
                    input.type = 'checkbox';
                    input.className = 'form-check-input';
                    input.id = `logging_field_${field.slug}`;
                    input.name = `logging_field_data[${field.slug}]`;
                    input.value = '1';
                    if (oldValue == '1' || oldValue === true) input.checked = true;
                    const checkLabel = document.createElement('label');
                    checkLabel.className = 'form-check-label';
                    checkLabel.htmlFor = `logging_field_${field.slug}`;
                    checkLabel.textContent = 'Yes';
                    checkDiv.appendChild(input);
                    checkDiv.appendChild(checkLabel);
                    fieldDiv.removeChild(label);
                    fieldDiv.appendChild(checkDiv);
                    input = checkDiv;
                    break;

                case 'select':
                    input = document.createElement('select');
                    input.className = 'form-select';
                    input.id = `logging_field_${field.slug}`;
                    input.name = `logging_field_data[${field.slug}]`;
                    if (field.is_required) input.required = true;
                    
                    const emptyOption = document.createElement('option');
                    emptyOption.value = '';
                    emptyOption.textContent = '-- Select --';
                    input.appendChild(emptyOption);

                    if (field.options_json && Array.isArray(field.options_json)) {
                        field.options_json.forEach(option => {
                            const opt = document.createElement('option');
                            opt.value = option;
                            opt.textContent = option;
                            if (oldValue === option) opt.selected = true;
                            input.appendChild(opt);
                        });
                    }
                    break;

                default:
                    input = document.createElement('input');
                    input.type = 'text';
                    input.className = 'form-control';
                    input.id = `logging_field_${field.slug}`;
                    input.name = `logging_field_data[${field.slug}]`;
                    input.value = oldValue;
                    break;
            }

            if (field.field_type !== 'checkbox') {
                fieldDiv.appendChild(input);
            }

            // Add help text if present
            if (field.help_text) {
                const helpText = document.createElement('small');
                helpText.className = 'form-text text-muted';
                helpText.textContent = field.help_text;
                fieldDiv.appendChild(helpText);
            }

            container.appendChild(fieldDiv);
        });
    }

    // Listen for agreement selection changes
    document.addEventListener('change', function(e) {
        if (e.target.name === 'agreement_ids[]' || e.target.id === 'contact_family_id') {
            renderDynamicLoggingFields();
        }
    });

    // Initial render on page load
    renderDynamicLoggingFields();

    // ============================================================================
    // Organization & State Selection Preservation across HTMX swaps
    // ============================================================================
    (function() {
        let preservedOrgIds = [];
        let preservedStateIds = [];
        
        // Before HTMX swap, capture current selected values from token pickers
        document.body.addEventListener('htmx:beforeRequest', function(event) {
            const target = event.detail.target;
            if (target.id === 'org-state-container' || target.closest('#org-state-container')) {
                const orgPicker = document.getElementById('activity-organizations-picker');
                const statePicker = document.getElementById('activity-states-picker');
                
                if (orgPicker) {
                    const orgInputs = orgPicker.querySelectorAll('input[name="organization_ids[]"]');
                    preservedOrgIds = Array.from(orgInputs).map(input => input.value);
                }
                
                if (statePicker) {
                    const stateInputs = statePicker.querySelectorAll('input[name="state_ids[]"]');
                    preservedStateIds = Array.from(stateInputs).map(input => input.value);
                }
            }
        });
        
        // After HTMX settles, restore selections
        document.body.addEventListener('htmx:afterSettle', function(event) {
            const target = event.target;
            if (target.id === 'org-state-container' || target.closest('#org-state-container')) {
                // Wait for token pickers to be fully initialized before setting values
                function waitForPickersAndSet() {
                    const orgPicker = document.getElementById('activity-organizations-picker');
                    const statePicker = document.getElementById('activity-states-picker');
                    
                    // Check if pickers are initialized
                    const orgReady = orgPicker && orgPicker.dataset.tokenPickerInitialized === 'true';
                    const stateReady = statePicker && statePicker.dataset.tokenPickerInitialized === 'true';
                    
                    if (!orgReady || !stateReady) {
                        // Not ready yet, try again shortly
                        setTimeout(waitForPickersAndSet, 50);
                        return;
                    }
                    
                    // Get server-provided IDs from data element
                    const dataElement = document.getElementById('org-state-data');
                    const serverOrgIds = dataElement ? JSON.parse(dataElement.dataset.orgIds || '[]') : [];
                    const serverStateIds = dataElement ? JSON.parse(dataElement.dataset.stateIds || '[]') : [];
                    
                    // MERGE preserved selections with server-provided selections
                    const orgIdsToSet = [...new Set([...preservedOrgIds, ...serverOrgIds])];
                    const stateIdsToSet = [...new Set([...preservedStateIds, ...serverStateIds])];
                    
                    console.log('Setting org IDs:', orgIdsToSet);
                    console.log('Setting state IDs:', stateIdsToSet);
                    
                    if (orgPicker && orgIdsToSet.length > 0) {
                        orgPicker.dispatchEvent(new CustomEvent('token-picker:set', { 
                            detail: orgIdsToSet,
                            bubbles: true
                        }));
                    }
                    
                    if (statePicker && stateIdsToSet.length > 0) {
                        statePicker.dispatchEvent(new CustomEvent('token-picker:set', { 
                            detail: stateIdsToSet,
                            bubbles: true
                        }));
                    }
                }
                
                // Start checking for initialized pickers
                setTimeout(waitForPickersAndSet, 50);
            }
        });
    })();

    // ============================================================================
    // Workaround for token picker dropdown not showing reliably on first focus
    // ============================================================================
    document.addEventListener('click', function(event) {
        const searchInput = event.target.closest('[data-token-search]');
        if (searchInput) {
            const picker = searchInput.closest('[data-token-picker]');
            if (picker) {
                const dropdown = picker.querySelector('[data-token-dropdown]');
                if (dropdown && dropdown.classList.contains('d-none')) {
                    // Trigger focus to ensure dropdown appears
                    searchInput.focus();
                    // Small delay then check if dropdown appeared
                    setTimeout(() => {
                        if (dropdown.classList.contains('d-none')) {
                            // Force dropdown to show if it didn't appear
                            dropdown.classList.remove('d-none');
                        }
                    }, 10);
                }
            }
        }
    }, true);

    // Time tracking mode UI management
    function updateTimeTrackingUI() {
        const mode = document.querySelector('input[name="time_tracking_mode"]:checked')?.value || 'engagement';
        const participantTimesSection = document.getElementById('participant-times-section');
        
        if (participantTimesSection) {
            if (mode === 'participant') {
                participantTimesSection.classList.remove('d-none');
            } else {
                participantTimesSection.classList.add('d-none');
            }
        }
    }

    // Listen to time tracking mode changes
    document.querySelectorAll('input[name="time_tracking_mode"]').forEach(radio => {
        radio.addEventListener('change', function () {
            updateTimeTrackingUI();
            markDirty();
        });
    });

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

    function loadParticipants() {
        const agreementIds = selectedValues('agreement_ids[]');
        const container = document.getElementById('participants-container');
        if (!container) return;

        if (agreementIds.length === 0) {
            container.innerHTML = '<small class="text-muted">Select an agreement first to load team members.</small>';
            window.activityParticipants = {};
            document.dispatchEvent(new CustomEvent('participants-updated'));
            return;
        }

        // Pass ALL agreement IDs to the HTMX participant checkboxes endpoint
        const params = new URLSearchParams();
        agreementIds.forEach(id => params.append('agreement_ids[]', id));
        
        htmx.ajax('GET', participantsUrl + '?' + params.toString(), {
            target: '#participants-container',
            swap: 'innerHTML'
        });

        // Fetch participant data for ALL selected agreements for the time tracking component
        fetchParticipantData(agreementIds);
    }

    function fetchParticipantData(agreementIds) {
        // Fetch users from all selected agreements for the time tracking component
        const params = new URLSearchParams();
        agreementIds.forEach(id => params.append('agreement_ids[]', id));
        
        fetch(`/api/agreements-users?${params.toString()}`)
            .then(response => response.json())
            .then(data => {
                window.activityParticipants = {};
                if (data.users && Array.isArray(data.users)) {
                    data.users.forEach(user => {
                        window.activityParticipants[user.id] = user.name;
                    });
                }
                document.dispatchEvent(new CustomEvent('participants-updated'));
            })
            .catch(error => {
                console.error('Error fetching participant data:', error);
                window.activityParticipants = {};
                document.dispatchEvent(new CustomEvent('participants-updated'));
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
            program_ids: selectedValues('program_ids[]'),
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
                form.querySelector('#activity-programs-picker')?.dispatchEvent(new CustomEvent('token-picker:set', { detail: template.program_ids || [] }));

                const family = document.getElementById('contact_family_id');
                const selectedType = document.getElementById('activity_type_selected');
                const internalOnly = document.getElementById('internal_only');

                if (family) family.value = template.contact_family_id || '';
                if (selectedType) selectedType.value = template.activity_type_id || '';
                if (internalOnly) internalOnly.checked = !!template.internal_only;

                if (family) {
                    htmx.trigger(family, 'change');
                }

                markDirty();
                updateActivityLoggingFields();
                loadParticipants();
            });
            container.appendChild(button);
        });
    }

    document.getElementById('activity-agreements-picker')?.addEventListener('token-picker:change', function () {
        loadParticipants();
        renderDynamicLoggingFields();
        refreshOrgStateContainer();
        markDirty();
    });

    function refreshOrgStateContainer() {
        const agreementIds = selectedValues('agreement_ids[]');
        const orgIds = selectedValues('organization_ids[]');
        const stateIds = selectedValues('state_ids[]');
        
        const params = new URLSearchParams();
        agreementIds.forEach(id => params.append('agreement_ids[]', id));
        orgIds.forEach(id => params.append('organization_ids[]', id));
        stateIds.forEach(id => params.append('state_ids[]', id));
        
        const url = '{{ route('activities.orgs-states-for-agreements') }}?' + params.toString();
        
        htmx.ajax('GET', url, {
            target: '#org-state-container',
            swap: 'innerHTML'
        });
    }

    ['activity-organizations-picker', 'activity-states-picker', 'activity-programs-picker'].forEach(function (id) {
        document.getElementById(id)?.addEventListener('token-picker:change', markDirty);
    });

    form.addEventListener('input', markDirty);
    form.addEventListener('change', function (event) {
        if (event.target && event.target.id === 'contact_family_id') {
            const selectedType = document.getElementById('activity_type_selected');
            if (selectedType) {
                selectedType.value = '';
            }
            updateActivityTypeState();
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
            type.dispatchEvent(new Event('change', { bubbles: true }));
        }
    });

    form.addEventListener('submit', function () {
        setStatus('<span class="text-secondary">Saving…</span>');
        saveRecentTemplate();
    });

    if (hasErrors) {
        setStatus('<span class="text-danger">⚠ Fix errors above to save</span>');
    } else {
        setStatus('<span class="text-muted">Ready</span>');
    }

    const family = document.getElementById('contact_family_id');
    if (family && family.value) {
        htmx.trigger(family, 'change');
    }

    updateActivityTypeState();
    loadParticipants();
    renderTemplates();
    updateTimeTrackingUI();

    document.querySelector('#activity-agreements-picker [data-token-search]')?.focus();
})();
</script>
@endsection