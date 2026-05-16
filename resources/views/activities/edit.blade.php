@extends('layouts.app')

@section('title', 'Edit Activity')

@section('content')

@php
    $defaultActivityLoggingConfig = [
        'event_hours' => true,
        'prep_hours' => true,
        'followup_hours' => false,
        'participant_count' => true,
        'external_attendees' => true,
        'summary' => true,
        'follow_up' => true,
        'strengths' => false,
        'recommendations' => false,
    ];

    $agreementActivityConfigs = $agreements->mapWithKeys(function ($agreement) use ($defaultActivityLoggingConfig) {
        return [
            $agreement->id => array_merge(
                $defaultActivityLoggingConfig,
                $agreement->activity_logging_config ?? [],
                ['time_tracking_mode' => $agreement->time_tracking_mode ?? 'engagement']
            )
        ];
    });

    $selectedAgreementIds = old('agreement_ids', $activity->agreements->pluck('id')->toArray());
    $selectedOrganizationIds = old('organization_ids', $activity->organizations->pluck('id')->toArray());
    $selectedStateIds = old('state_ids', $activity->states->pluck('id')->toArray());
    $selectedProgramIds = old('program_ids', $activity->programs->pluck('id')->toArray());
    $selectedParticipantIds = old('participant_user_ids', $activity->participants->pluck('id')->toArray());
    $selectedActivityTypeId = old('activity_type_id', $activity->activity_type_id);
    $agreementLoggingData = old('agreement_logging_values', $activity->logging_field_data['agreements'] ?? []);
    $contactFamilyLoggingData = old('contact_family_logging_values', $activity->logging_field_data['contact_family'] ?? []);
@endphp

<div class="container-fluid py-4">
    <div class="row g-4 mb-2">
        <div class="col-12">
            <h1 class="h3 mb-1">Edit Activity</h1>
            <p class="text-muted mb-0">Fast update mode for existing records.</p>
        </div>
    </div>

    @if ($errors->any())
        <div class="alert alert-danger py-2">
            <strong>Please fix the highlighted fields.</strong>
        </div>
    @endif

    <form method="POST" action="{{ route('activities.update', $activity) }}" id="activity-edit-form">
        @csrf
        @method('PUT')

        <div class="row g-4">
            <div class="col-lg-8">
                <div class="d-grid gap-4">
                    <x-section-card title="1) Agreements & Coverage" subtitle="Update agreement context first.">
                        <div class="mb-3">
                            <label class="form-label fw-semibold">Agreements</label>
                            <x-token-picker
                                picker-id="activity-agreements-picker"
                                name="agreement_ids[]"
                                :items="$agreements"
                                :selected-ids="$selectedAgreementIds"
                                placeholder="Search agreements..."
                                empty-message="No agreements match your search."
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
                                />
                                @error('state_ids')
                                    <div class="text-danger small mt-1">{{ $message }}</div>
                                @enderror
                            </div>
                        </div>
                    </x-section-card>

                    <x-section-card title="1b) Agreement Logging Fields" subtitle="Agreement-specific questions are grouped below each selected agreement.">
                        <div id="agreement-logging-groups" class="d-grid gap-3">
                            @foreach($agreements as $agreement)
                                <div class="border rounded p-3 d-none" data-agreement-logging-group="{{ $agreement->id }}">
                                    <div class="d-flex justify-content-between align-items-center mb-3">
                                        <div>
                                            <h5 class="mb-1">{{ $agreement->name }}</h5>
                                            <p class="small text-muted mb-0">Agreement-level logging fields</p>
                                        </div>
                                    </div>

                                    @if($agreement->agreementLoggingFields->isEmpty())
                                        <div class="text-muted small">No agreement-specific logging fields are assigned to this agreement.</div>
                                    @else
                                        <div class="row g-3">
                                            @foreach($agreement->agreementLoggingFields as $field)
                                                <div class="col-md-6">
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
                                    @endif
                                </div>
                            @endforeach

                            <div id="agreement-logging-empty" class="text-muted small border rounded p-3">
                                Select one or more agreements to see their grouped logging fields.
                            </div>
                        </div>
                    </x-section-card>

                    <x-section-card title="2) Activity Classification" subtitle="Contact family controls available activity types.">
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

                        <div class="mt-4">
                            @foreach($contactFamilies as $family)
                                <div class="border rounded p-3 d-none" data-contact-family-logging-group="{{ $family->id }}">
                                    <div class="fw-semibold mb-2">{{ $family->name }} Logging Fields</div>

                                    @if($family->contactFamilyLoggingFields->isEmpty())
                                        <div class="text-muted small">No classification logging fields are assigned to this contact family.</div>
                                    @else
                                        <div class="row g-3">
                                            @foreach($family->contactFamilyLoggingFields as $field)
                                                <div class="col-md-6">
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

                    <x-section-card title="3) Notes & Participants" subtitle="Only fill what is needed.">
                        <div class="mb-3" data-config-key="external_attendees">
                            <details>
                                <summary class="small text-muted mb-2">External Attendees (optional)</summary>
                                <textarea class="form-control @error('external_attendees') is-invalid @enderror"
                                          id="external_attendees"
                                          name="external_attendees"
                                          rows="2"
                                          placeholder="Comma-separated names/organizations">{{ old('external_attendees', $activity->external_attendees) }}</textarea>
                                @error('external_attendees')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </details>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Internal Participants</label>
                            <div id="participants-container" class="border rounded p-2">
                                <small class="text-muted">Select an agreement first to load team members.</small>
                            </div>
                            @error('participant_user_ids')
                                <div class="text-danger small mt-1">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="mb-3" data-config-key="summary">
                            <label for="summary" class="form-label">Summary</label>
                            <textarea class="form-control @error('summary') is-invalid @enderror"
                                      id="summary"
                                      name="summary"
                                      rows="2">{{ old('summary', $activity->summary) }}</textarea>
                            @error('summary')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="mb-3" data-config-key="follow_up">
                            <label for="follow_up" class="form-label">Follow-Up</label>
                            <textarea class="form-control @error('follow_up') is-invalid @enderror"
                                      id="follow_up"
                                      name="follow_up"
                                      rows="2">{{ old('follow_up', $activity->follow_up) }}</textarea>
                            @error('follow_up')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                    </x-section-card>
                </div>
            </div>

            <div class="col-lg-4">
                <div class="d-grid gap-4">
                    <x-section-card title="4) Quick Details" subtitle="Date, time, count, programs.">
                        <div class="mb-3">
                            <label for="engagement_date" class="form-label fw-semibold">Date <span class="text-danger">*</span></label>
                            <input type="date"
                                   class="form-control @error('engagement_date') is-invalid @enderror"
                                   id="engagement_date"
                                   name="engagement_date"
                                   value="{{ old('engagement_date', $activity->engagement_date?->format('Y-m-d')) }}"
                                   required>
                            @error('engagement_date')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="mt-3" data-config-key="participant_count">
                            <label for="participant_count" class="form-label">Participant Count</label>
                            <input type="number"
                                   class="form-control @error('participant_count') is-invalid @enderror"
                                   id="participant_count"
                                   name="participant_count"
                                   min="0"
                                   value="{{ old('participant_count', $activity->participant_count) }}">
                            @error('participant_count')
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
                                   {{ old('internal_only', $activity->internal_only) ? 'checked' : '' }}>
                            <label class="form-check-label" for="internal_only">
                                Internal only
                                <small class="text-muted d-block mt-1">Exclude this activity from external reports.</small>
                            </label>
                        </div>
                    </x-section-card>

                    <x-section-card title="5) Time Tracking" subtitle="Determined by the selected agreement.">
                        <input type="hidden" name="time_tracking_mode" id="time_tracking_mode_input" value="{{ old('time_tracking_mode', $activity->time_tracking_mode ?? 'engagement') }}">
                        <div id="time-tracking-mode-display" class="mb-3">
                            <span class="badge bg-secondary">Time by Engagement</span>
                            <small class="text-muted ms-2">One total time value for the entire activity.</small>
                        </div>

                        <!-- Engagement time entry (shown when mode = engagement) -->
                        <div id="engagement-time-section">
                            <div class="row g-2">
                                <div class="col-12" data-config-key="event_hours">
                                    <label for="event_hours" class="form-label fw-semibold">Event Hours <span class="text-danger">*</span></label>
                                    <input type="number"
                                           class="form-control @error('event_hours') is-invalid @enderror"
                                           id="event_hours"
                                           name="event_hours"
                                           step="0.25"
                                           min="0"
                                           max="9999.99"
                                           data-required-when-enabled="true"
                                           value="{{ old('event_hours', $activity->event_hours) }}">
                                    <x-numeric-quick-chips for="event_hours" />
                                    @error('event_hours')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>

                                <div class="col-6" data-config-key="prep_hours">
                                    <label for="prep_hours" class="form-label">Prep Hours</label>
                                    <input type="number"
                                           class="form-control @error('prep_hours') is-invalid @enderror"
                                           id="prep_hours"
                                           name="prep_hours"
                                           step="0.25"
                                           min="0"
                                           max="9999.99"
                                           value="{{ old('prep_hours', $activity->prep_hours ?? 0) }}">
                                    <x-numeric-quick-chips for="prep_hours" />
                                    @error('prep_hours')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>

                                <div class="col-6" data-config-key="followup_hours">
                                    <label for="followup_hours" class="form-label">Follow-Up Hours</label>
                                    <input type="number"
                                           class="form-control @error('followup_hours') is-invalid @enderror"
                                           id="followup_hours"
                                           name="followup_hours"
                                           step="0.25"
                                           min="0"
                                           max="9999.99"
                                           value="{{ old('followup_hours', $activity->followup_hours ?? 0) }}">
                                    <x-numeric-quick-chips for="followup_hours" />
                                    @error('followup_hours')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>
                        </div>

                        <!-- Participant time entry (shown when mode = participant) -->
                        <div id="participant-times-section" class="d-none">
                            <label class="form-label fw-semibold mb-3">Participant Time Tracking</label>

                            <x-participant-time-rows />

                            <div class="alert alert-info alert-sm mt-3" role="alert">
                                <small>Add each team member and their hours. Remove rows you don't need.</small>
                            </div>
                        </div>
                    </x-section-card>

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
            <button type="submit" class="btn btn-sm btn-primary" form="activity-edit-form" name="save_mode" value="save">Save Activity</button>
            <button type="submit" class="btn btn-sm btn-outline-primary" form="activity-edit-form" name="save_mode" value="save_new">Save + New</button>
            <button type="submit" class="btn btn-sm btn-outline-primary" form="activity-edit-form" name="save_mode" value="save_duplicate">Save + Duplicate</button>
        </div>
    </div>
</div>
<div style="height: 72px;"></div>

<script>
(function () {
    const defaultActivityLoggingConfig = @json($defaultActivityLoggingConfig);
    const agreementActivityConfigs = @json($agreementActivityConfigs);
    const participantsUrl = @json(route('activities.participants-for-agreement'));
    const selectedParticipants = @json($selectedParticipantIds);
    // Saved participant times keyed by user_id — pre-fills rows on edit load
    window.existingParticipantTimes = @json(
        ($activity->participantTimes ?? collect())
            ->keyBy('user_id')
            ->map(fn ($t) => ['hours' => $t->hours, 'notes' => $t->notes])
    );
    const form = document.getElementById('activity-edit-form');
    const statusTop = document.getElementById('activity-save-status');
    const statusBar = document.getElementById('activity-save-bar-status');
    const hasErrors = @json($errors->any());

    if (!form) return;

    // Time tracking mode UI management
    function updateTimeTrackingUI(mode) {
        if (!mode) {
            mode = document.getElementById('time_tracking_mode_input')?.value || 'engagement';
        }
        const isParticipant = mode === 'participant';

        document.getElementById('participant-times-section')?.classList.toggle('d-none', !isParticipant);
        document.getElementById('engagement-time-section')?.classList.toggle('d-none', isParticipant);

        const display = document.getElementById('time-tracking-mode-display');
        if (display) {
            if (isParticipant) {
                display.innerHTML = '<span class="badge bg-info text-dark">Time by Participant</span><small class="text-muted ms-2">Individual hours tracked per team member.</small>';
            } else {
                display.innerHTML = '<span class="badge bg-secondary">Time by Engagement</span><small class="text-muted ms-2">One total time value for the entire activity.</small>';
            }
        }
    }

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

    function updateActivityLoggingFields() {
        const agreementId = firstSelectedAgreementId();
        const config = agreementActivityConfigs[agreementId] || defaultActivityLoggingConfig;

        // Sync time tracking mode from agreement
        const mode = config.time_tracking_mode || 'engagement';
        const modeInput = document.getElementById('time_tracking_mode_input');
        if (modeInput) modeInput.value = mode;
        updateTimeTrackingUI(mode);

        document.querySelectorAll('[data-config-key]').forEach(function (wrapper) {
            const key = wrapper.dataset.configKey;
            const enabled = !!config[key];
            wrapper.classList.toggle('d-none', !enabled);

            wrapper.querySelectorAll('input, textarea, select, details').forEach(function (field) {
                if (field.tagName !== 'DETAILS') {
                    field.disabled = !enabled;
                }

                if (field.dataset.requiredWhenEnabled === 'true') {
                    field.required = enabled;
                }
            });
        });
    }

    function updateAgreementLoggingGroups() {
        const selected = new Set(selectedValues('agreement_ids[]'));
        let visibleGroups = 0;

        document.querySelectorAll('[data-agreement-logging-group]').forEach(function (group) {
            const visible = selected.has(group.dataset.agreementLoggingGroup);
            group.classList.toggle('d-none', !visible);
            group.querySelectorAll('input, textarea, select').forEach(function (field) {
                field.disabled = !visible;
            });
            visibleGroups += visible ? 1 : 0;
        });

        document.getElementById('agreement-logging-empty')?.classList.toggle('d-none', visibleGroups > 0);
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

    function buildParticipantMap() {
        var container = document.getElementById('participants-container');
        if (!container) return;
        var map = {};
        container.querySelectorAll('[data-user-picker-item]').forEach(function (item) {
            var cb = item.querySelector('input[type="checkbox"]');
            if (cb && item.dataset.userLabel) map[cb.value] = item.dataset.userLabel;
        });
        window.activityParticipants = map;
        document.dispatchEvent(new CustomEvent('participants-updated'));
    }

    function loadParticipants() {
        const agreementId = firstSelectedAgreementId();
        const container = document.getElementById('participants-container');
        if (!container) return;

        if (!agreementId) {
            container.innerHTML = '<small class="text-muted">Select an agreement first to load team members.</small>';
            return;
        }

        const params = new URLSearchParams();
        params.set('agreement_id', agreementId);
        selectedParticipants.forEach(function (id) {
            params.append('participant_user_ids[]', id);
        });

        htmx.ajax('GET', participantsUrl + '?' + params.toString(), {
            target: '#participants-container',
            swap: 'innerHTML'
        }).then(function () {
            buildParticipantMap();
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

    const agreementsPicker = document.getElementById('activity-agreements-picker');
    if (agreementsPicker) {
        agreementsPicker.addEventListener('token-picker:change', function () {
            updateActivityLoggingFields();
            updateAgreementLoggingGroups();
            loadParticipants();
            markDirty();
        });
        // Re-run on initialization so pre-selected agreements (e.g. ?agreement_id=2)
        // trigger the same updates as a manual selection would.
        agreementsPicker.addEventListener('token-picker:initialized', function () {
            updateActivityLoggingFields();
            updateAgreementLoggingGroups();
            loadParticipants();
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
            type.dispatchEvent(new Event('change', { bubbles: true }));
        }
    });

    form.addEventListener('submit', function () {
        setStatus('<span class="text-secondary">Saving…</span>');
    });

    if (hasErrors) {
        setStatus('<span class="text-danger">⚠ Fix errors above to save</span>');
    } else {
        setStatus('<span class="text-muted">Saved</span>');
    }

    const family = document.getElementById('contact_family_id');
    if (family && family.value) {
        htmx.trigger(family, 'change');
    }

    updateActivityTypeState();
    updateActivityLoggingFields();
    updateAgreementLoggingGroups();
    updateContactFamilyLoggingGroups();
    // loadParticipants() is called by token-picker:initialized when agreement is pre-selected,
    // or by token-picker:change when user picks one. No need to call it here before DOM is ready.
    updateTimeTrackingUI();

    document.querySelector('#activity-agreements-picker [data-token-search]')?.focus();
})();
</script>
@endsection