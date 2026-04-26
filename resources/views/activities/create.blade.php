@extends('layouts.app')

@section('title', 'Log Activity')

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
                $agreement->activity_logging_config ?? []
            )
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
                    <x-section-card title="1) Agreements & Coverage" subtitle="Select agreements first, then optional org/state context.">
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

                    <x-section-card title="3) Notes & Participants" subtitle="Only fill what is needed.">
                        <div class="mb-3" data-config-key="external_attendees">
                            <details>
                                <summary class="small text-muted mb-2">External Attendees (optional)</summary>
                                <textarea class="form-control @error('external_attendees') is-invalid @enderror"
                                          id="external_attendees"
                                          name="external_attendees"
                                          rows="2"
                                          placeholder="Comma-separated names/organizations">{{ old('external_attendees', $prefill['external_attendees'] ?? '') }}</textarea>
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
                                      rows="2">{{ old('summary', $prefill['summary'] ?? '') }}</textarea>
                            @error('summary')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="mb-3" data-config-key="follow_up">
                            <label for="follow_up" class="form-label">Follow-Up</label>
                            <textarea class="form-control @error('follow_up') is-invalid @enderror"
                                      id="follow_up"
                                      name="follow_up"
                                      rows="2">{{ old('follow_up', $prefill['follow_up'] ?? '') }}</textarea>
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
                                   value="{{ old('engagement_date', $prefill['engagement_date'] ?? now()->format('Y-m-d')) }}"
                                   required>
                            @error('engagement_date')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

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
                                       value="{{ old('event_hours', $prefill['event_hours'] ?? '') }}">
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
                                       value="{{ old('prep_hours', $prefill['prep_hours'] ?? 0) }}">
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
                                       value="{{ old('followup_hours', $prefill['followup_hours'] ?? 0) }}">
                                <x-numeric-quick-chips for="followup_hours" />
                                @error('followup_hours')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                        </div>

                        <div class="mt-3" data-config-key="participant_count">
                            <label for="participant_count" class="form-label">Participant Count</label>
                            <input type="number"
                                   class="form-control @error('participant_count') is-invalid @enderror"
                                   id="participant_count"
                                   name="participant_count"
                                   min="0"
                                   value="{{ old('participant_count', $prefill['participant_count'] ?? '') }}">
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

                        <div class="form-check form-switch mt-3">
                            <input class="form-check-input"
                                   type="checkbox"
                                   id="internal_only"
                                   value="1"
                                   {{ old('internal_only', 0) ? 'checked' : '' }}>
                            <label class="form-check-label" for="internal_only">Internal only</label>
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
    const defaultActivityLoggingConfig = @json($defaultActivityLoggingConfig);
    const agreementActivityConfigs = @json($agreementActivityConfigs);
    const participantsUrl = @json(route('activities.participants-for-agreement'));
    const form = document.getElementById('activity-create-form');
    const statusTop = document.getElementById('activity-save-status');
    const statusBar = document.getElementById('activity-save-bar-status');
    const hasErrors = @json($errors->any());

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

    function updateActivityLoggingFields() {
        const agreementId = firstSelectedAgreementId();
        const config = agreementActivityConfigs[agreementId] || defaultActivityLoggingConfig;

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

    function loadParticipants() {
        const agreementId = firstSelectedAgreementId();
        const container = document.getElementById('participants-container');
        if (!container) return;

        if (!agreementId) {
            container.innerHTML = '<small class="text-muted">Select an agreement first to load team members.</small>';
            return;
        }

        htmx.ajax('GET', participantsUrl + '?agreement_id=' + encodeURIComponent(agreementId), {
            target: '#participants-container',
            swap: 'innerHTML'
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
        updateActivityLoggingFields();
        loadParticipants();
        markDirty();
    });

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
    updateActivityLoggingFields();
    loadParticipants();
    renderTemplates();

    document.querySelector('#activity-agreements-picker [data-token-search]')?.focus();
})();
</script>
@endsection