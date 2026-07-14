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
    'selectedProjectIds' => [],
    'selectedProgramIds' => [],
    'selectedParticipantUserIds' => [],
    'selectedActivityTypeId' => null,
    'agreementLoggingData' => [],
    'contactFamilyLoggingData' => [],
    'activityLoggingData' => [],
    'contactTimeData' => [],
    'participantTimeData' => [],
    'engagementDateValue' => null,
    'internalOnlyChecked' => false,
    'activity' => null,
])

@php
    $agreementConfigs = $agreements->mapWithKeys(function ($agreement) {
        $deliverables = $agreement->deliverables ?? collect();

        return [
            $agreement->id => [
                'name' => $agreement->name,
                'organization_ids' => $agreement->organizations->pluck('id')->map(fn($id) => (string) $id)->values()->all(),
                'state_ids' => $agreement->states->pluck('id')->map(fn($id) => (string) $id)->values()->all(),
                'participant_user_ids' => $agreement->users
                    ->pluck('id')
                    ->concat($agreement->teams->flatMap(fn ($team) => $team->users->pluck('id')))
                    ->map(fn ($id) => (string) $id)
                    ->unique()
                    ->values()
                    ->all(),
                'project_ids' => $agreement->projects->pluck('id')->map(fn($id) => (string) $id)->values()->all(),
                'program_ids' => $agreement->programs->pluck('id')->map(fn($id) => (string) $id)->values()->all(),
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
                'time_tracking_mode' => $agreement->time_tracking_mode?->value,
            ]
        ];
    });

    $selectedAgreementTimeTrackingConfigs = collect($selectedAgreementIds)
        ->map(fn ($agreementId) => $agreementConfigs[(string) $agreementId] ?? null)
        ->filter()
        ->values();

    $timeTrackingContactConfigs = $selectedAgreementTimeTrackingConfigs
        ->filter(fn ($config) => in_array($config['time_tracking_mode'] ?? null, ['by_contact', 'by_user'], true))
        ->values();

    $timeTrackingUserConfigs = $selectedAgreementTimeTrackingConfigs
        ->filter(fn ($config) => ($config['time_tracking_mode'] ?? null) === 'by_user')
        ->values();

    $formatAgreementNames = function ($configs) {
        $names = collect($configs)->pluck('name')->filter()->values();

        if ($names->isEmpty()) {
            return '';
        }

        if ($names->count() === 1) {
            return $names->first();
        }

        if ($names->count() === 2) {
            return $names->implode(' and ');
        }

        return $names->slice(0, -1)->implode(', ') . ', and ' . $names->last();
    };

    $timeTrackingContactRequiredBy = $timeTrackingContactConfigs->isNotEmpty()
        ? 'Required by: ' . $formatAgreementNames($timeTrackingContactConfigs)
        : '';
    $timeTrackingUserRequiredBy = $timeTrackingUserConfigs->isNotEmpty()
        ? 'Required by: ' . $formatAgreementNames($timeTrackingUserConfigs)
        : '';
    $hasTimeTracking = $timeTrackingContactConfigs->isNotEmpty();

    $isEditMode = $formMode === 'edit';
    $pageTitle = $isEditMode ? 'Edit Activity' : 'Log Activity';
    $pageSubtitle = $isEditMode
        ? 'Fast update mode for existing records.'
        : 'Fast entry mode for daily operational logging.';
    $formId = $isEditMode ? 'activity-edit-form' : 'activity-create-form';
    $formAction = $isEditMode ? route('activities.update', $activity) : route('activities.store');
    $saveStatusDefault = $isEditMode ? 'Saved' : 'Ready';
    $agreementsWithLoggingFields = $agreements->filter(fn ($agreement) => $agreement->agreementLoggingFields->isNotEmpty())->values();
    $activityTypesWithLoggingFields = $contactFamilies
        ->flatMap(fn ($family) => $family->activityTypes)
        ->filter(fn ($type) => $type->activityTypeLoggingFields->isNotEmpty())
        ->values();
    $availableProjects = $agreements
        ->flatMap(fn ($agreement) => $agreement->projects)
        ->unique('id')
        ->sortBy('name')
        ->values();
    $participantOptions = $agreements
        ->flatMap(function ($agreement) {
            return $agreement->users->concat($agreement->teams->flatMap(fn ($team) => $team->users));
        })
        ->unique('id')
        ->sortBy('name')
        ->values()
        ->map(function ($user) {
            $role = !empty($user->role) ? ' (' . ucfirst($user->role) . ')' : '';

            return [
                'value' => $user->id,
                'label' => $user->name . $role,
                'search' => trim($user->name . ' ' . ($user->email ?? '') . ' ' . ($user->role ?? '')),
            ];
        });

    $currentContactFamily = $contactFamilies->first(fn ($family) => (string) $family->id === (string) $currentContactFamilyId);
    $selectedContactFamilyTracksAdditionalTime = (bool) ($currentContactFamily?->track_additional_time);

    if ($isEditMode && $activity?->relationLoaded('participantTimes')) {
        $historicalParticipantOptions = $activity->participantTimes
            ->filter(function ($participantTime) use ($participantOptions) {
                if (!$participantTime->user_id) {
                    return false;
                }

                return !$participantOptions->contains(fn ($option) => (string) $option['value'] === (string) $participantTime->user_id);
            })
            ->map(function ($participantTime) {
                $label = $participantTime->user?->name
                    ?? $participantTime->participant_name
                    ?? 'Historical Participant';

                return [
                    'value' => $participantTime->user_id,
                    'label' => $label,
                    'search' => strtolower($label),
                    'context' => 'Historical',
                    'contextBadgeClass' => 'bg-secondary',
                ];
            });

        $participantOptions = $participantOptions
            ->concat($historicalParticipantOptions)
            ->unique('value')
            ->sortBy('label')
            ->values();
    }

    $participantOptionMap = $participantOptions
        ->mapWithKeys(fn ($option) => [
            (string) $option['value'] => [
                'label' => $option['label'],
                'historical' => ($option['context'] ?? null) === 'Historical',
            ],
        ])
        ->all();

    $normalizedContactTimeData = [
        'activity_hours' => data_get($contactTimeData, 'activity_hours'),
        'prep_hours' => data_get($contactTimeData, 'prep_hours', 0),
        'follow_up_hours' => data_get($contactTimeData, 'follow_up_hours', 0),
    ];

    $normalizedParticipantTimeData = collect($participantTimeData)
        ->mapWithKeys(function ($row, $key) use ($participantOptionMap) {
            $userId = (string) data_get($row, 'user_id', $key);

            if ($userId === '') {
                return [];
            }

            return [
                $userId => [
                    'user_id' => $userId,
                    'hours' => data_get($row, 'hours'),
                    'prep_hours' => data_get($row, 'prep_hours', 0),
                    'follow_up_hours' => data_get($row, 'follow_up_hours', 0),
                    'participant_name' => data_get($row, 'participant_name', data_get($participantOptionMap, $userId . '.label')),
                ],
            ];
        })
        ->all();

    $participantTimeErrorMessage = collect($errors->messages())
        ->filter(fn ($messages, $key) => str_starts_with($key, 'participant_times.'))
        ->flatten()
        ->first();

    $historicalParticipantIds = $isEditMode && $activity
        ? $activity->participantTimes
            ->pluck('user_id')
            ->filter()
            ->map(fn ($id) => (string) $id)
            ->unique()
            ->values()
            ->all()
        : [];

    $originalAgreementIds = $isEditMode && $activity
        ? $activity->agreements
            ->pluck('id')
            ->map(fn ($id) => (string) $id)
            ->values()
            ->all()
        : [];
@endphp

<style>
    .activity-logging-subsection {
        border-left: 4px solid var(--bs-border-color);
        padding-left: 1rem;
    }

    .activity-logging-subsection + .activity-logging-subsection {
        margin-top: 1.25rem;
        padding-top: 1.25rem;
    }

    .activity-logging-subsection-title {
        font-size: 1.05rem;
        font-weight: 600;
        line-height: 1.3;
    }

    .activity-logging-subsection-meta {
        font-size: 0.875rem;
    }

    .time-tracking-subsection {
        border-left: 4px solid var(--bs-border-color);
        padding-left: 1rem;
    }

    .time-tracking-subsection + .time-tracking-subsection {
        margin-top: 1.25rem;
        padding-top: 1.25rem;
    }
</style>

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

                        <div class="mt-4">
                            <x-project-program-scope-picker
                                scope-id="activity-coverage-scope"
                                :projects="$availableProjects"
                                :selected-project-ids="$selectedProjectIds"
                                :selected-program-ids="$selectedProgramIds"
                                project-help-text="Track the project coverage for this activity. Available projects come from the selected agreements."
                                program-help-text="Track the program coverage for this activity. Available programs come from the selected agreements."
                            />
                        </div>
                    </x-section-card>

                    <x-section-card title="Participants">
                        <div class="mb-0">
                            <label class="form-label fw-semibold">Delivered By</label>
                            <x-token-picker
                                picker-id="activity-participants-picker"
                                name="participant_user_ids[]"
                                :options="$participantOptions"
                                :selected-ids="$selectedParticipantUserIds"
                                label-key="label"
                                value-key="value"
                                search-key="search"
                                placeholder="Search covered users..."
                                disabled-placeholder="Select at least one agreement first..."
                                :disabled="empty($selectedAgreementIds)"
                                :open-on-focus="false"
                            />
                            <div class="form-text">Choose the covered users who helped deliver this activity.</div>
                            @error('participant_user_ids')
                                <div class="text-danger small mt-1">{{ $message }}</div>
                            @enderror
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
                                        {{ empty($selectedAgreementIds) ? 'disabled' : '' }}
                                        required>
                                    <option value="">{{ empty($selectedAgreementIds) ? 'Select at least one agreement first...' : 'Select contact family...' }}</option>
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
                                    <option value="">{{ empty($selectedAgreementIds) ? 'Select at least one agreement first...' : 'Select contact family first...' }}</option>
                                </select>
                                @error('activity_type_id')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                        </div>

                        <div class="mt-4">
                            @foreach($contactFamilies as $family)
                                <div class="activity-logging-subsection d-none" data-contact-family-logging-group="{{ $family->id }}">
                                    <div class="d-flex flex-column flex-md-row align-items-md-baseline gap-1 gap-md-3 mb-3">
                                        <div class="activity-logging-subsection-title">Contact Family Logging Fields</div>
                                        <div class="text-muted activity-logging-subsection-meta">{{ $family->name }}</div>
                                    </div>

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

                        <div id="activity-logging-section" class="mt-4 d-none">
                            @forelse($activityTypesWithLoggingFields as $activityType)
                                <div class="activity-logging-subsection d-none" data-activity-logging-group="{{ $activityType->id }}">
                                    <div class="d-flex flex-column flex-md-row align-items-md-baseline gap-1 gap-md-3 mb-3">
                                        <div class="activity-logging-subsection-title">Activity Logging Fields</div>
                                        <div class="text-muted activity-logging-subsection-meta">{{ $activityType->name }}</div>
                                    </div>
                                    <div class="row g-3">
                                        @foreach($activityType->activityTypeLoggingFields as $field)
                                            <div class="{{ $field->is_full_width ? 'col-12' : 'col-md-6' }}">
                                                @include('activities.partials.logging-field-input', [
                                                    'field' => $field,
                                                    'inputName' => "activity_logging_values[{$field->id}]",
                                                    'oldKey' => "activity_logging_values.{$field->id}",
                                                    'value' => data_get($activityLoggingData, (string) $field->id),
                                                    'inputId' => "activity_field_{$activityType->id}_{$field->id}",
                                                    'downloadContext' => 'activity_type',
                                                    'isRequired' => (bool) $field->pivot->is_required,
                                                ])
                                            </div>
                                        @endforeach
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </x-section-card>

                    <div id="time-tracking-section" class="{{ $hasTimeTracking ? '' : 'd-none' }}">
                        <x-section-card title="Time Tracking">
                            <div class="d-grid gap-3">
                                <div class="time-tracking-subsection {{ $hasTimeTracking ? '' : 'd-none' }}" data-time-tracking-subsection="contact">
                                    <div class="d-flex flex-column flex-md-row align-items-md-baseline gap-1 gap-md-3 mb-3">
                                        <div class="activity-logging-subsection-title">Time by Contact</div>
                                        <div class="text-muted activity-logging-subsection-meta" data-time-tracking-contact-required-by>{{ $timeTrackingContactRequiredBy }}</div>
                                    </div>
                                    <div class="row g-3">
                                        <div class="col-md-4 {{ $selectedContactFamilyTracksAdditionalTime ? '' : 'd-none' }}" data-contact-additional-time-field="prep_hours">
                                            <label for="contact_time_prep_hours" class="form-label fw-semibold">Prep Time</label>
                                            <input type="number"
                                                   step="0.25"
                                                   min="0"
                                                   class="form-control @error('contact_time.prep_hours') is-invalid @enderror"
                                                   id="contact_time_prep_hours"
                                                   name="contact_time[prep_hours]"
                                                   value="{{ $normalizedContactTimeData['prep_hours'] }}"
                                                   data-contact-time-input="prep_hours">
                                            @error('contact_time.prep_hours')
                                                <div class="invalid-feedback">{{ $message }}</div>
                                            @enderror
                                        </div>
                                        <div class="col-md-4">
                                            <label for="contact_time_activity_hours" class="form-label fw-semibold">
                                                Activity Time <span class="text-danger">*</span>
                                            </label>
                                            <input type="number"
                                                   step="0.25"
                                                   min="0"
                                                   class="form-control @error('contact_time.activity_hours') is-invalid @enderror"
                                                   id="contact_time_activity_hours"
                                                   name="contact_time[activity_hours]"
                                                   value="{{ $normalizedContactTimeData['activity_hours'] }}"
                                                   data-contact-time-input="activity_hours">
                                            @error('contact_time.activity_hours')
                                                <div class="invalid-feedback">{{ $message }}</div>
                                            @enderror
                                        </div>
                                        <div class="col-md-4 {{ $selectedContactFamilyTracksAdditionalTime ? '' : 'd-none' }}" data-contact-additional-time-field="follow_up_hours">
                                            <label for="contact_time_follow_up_hours" class="form-label fw-semibold">Follow Up Time</label>
                                            <input type="number"
                                                   step="0.25"
                                                   min="0"
                                                   class="form-control @error('contact_time.follow_up_hours') is-invalid @enderror"
                                                   id="contact_time_follow_up_hours"
                                                   name="contact_time[follow_up_hours]"
                                                   value="{{ $normalizedContactTimeData['follow_up_hours'] }}"
                                                   data-contact-time-input="follow_up_hours">
                                            @error('contact_time.follow_up_hours')
                                                <div class="invalid-feedback">{{ $message }}</div>
                                            @enderror
                                        </div>
                                    </div>
                                </div>

                                <div class="time-tracking-subsection {{ $timeTrackingUserConfigs->isNotEmpty() ? '' : 'd-none' }}" data-time-tracking-subsection="user">
                                    <div class="d-flex flex-column flex-xl-row justify-content-between align-items-xl-baseline gap-2 mb-3">
                                        <div class="d-flex flex-column flex-md-row align-items-md-baseline gap-1 gap-md-3">
                                            <div class="activity-logging-subsection-title">Time by User</div>
                                            <div class="text-muted activity-logging-subsection-meta" data-time-tracking-user-required-by>{{ $timeTrackingUserRequiredBy }}</div>
                                        </div>
                                        <button type="button" class="btn btn-sm btn-outline-secondary" id="copy-contact-time-to-users">
                                            Sync Contact Time to Users
                                        </button>
                                    </div>
                                    <div class="table-responsive">
                                        <table class="table table-sm align-middle mb-0">
                                            <thead>
                                                <tr>
                                                    <th scope="col">Delivered By</th>
                                                    <th scope="col" class="{{ $selectedContactFamilyTracksAdditionalTime ? '' : 'd-none' }}" data-participant-extra-header="prep">Prep Time</th>
                                                    <th scope="col" style="width: 180px;">Activity Time</th>
                                                    <th scope="col" class="{{ $selectedContactFamilyTracksAdditionalTime ? '' : 'd-none' }}" data-participant-extra-header="follow_up">Follow Up Time</th>
                                                </tr>
                                            </thead>
                                            <tbody id="participant-time-rows"></tbody>
                                        </table>
                                    </div>
                                    <div id="participant-time-empty-state" class="text-muted small mt-2">
                                        Select Participants above to track time for each user.
                                    </div>
                                    @if($participantTimeErrorMessage)
                                        <div class="text-danger small mt-2">{{ $participantTimeErrorMessage }}</div>
                                    @endif
                                </div>
                            </div>
                        </x-section-card>
                    </div>

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
                                                    'agreementId' => $agreement->id,
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
        </div>
    </div>
</div>
<div style="height: 72px;"></div>

<script>
(function () {
    const agreementConfigs = @json($agreementConfigs);
    const contactFamilyAdditionalTimeMap = @json($contactFamilies->mapWithKeys(fn ($family) => [(string) $family->id => (bool) $family->track_additional_time]));
    const participantDirectory = @json($participantOptionMap);
    const historicalParticipantIds = @json($historicalParticipantIds);
    const initialAgreementIds = @json(array_map('strval', $selectedAgreementIds));
    const originalAgreementIds = @json($originalAgreementIds);
    const initialParticipantTimes = @json($normalizedParticipantTimeData);
    const form = document.getElementById(@json($formId));
    const statusTop = document.getElementById('activity-save-status');
    const statusBar = document.getElementById('activity-save-bar-status');
    const hasErrors = @json($errors->any());
    const isEditMode = @json($isEditMode);
    let agreementsPickerInitialized = false;

    if (!form) return;

    function setStatus(html) {
        statusTop.innerHTML = html;
        statusBar.innerHTML = html;
    }

    function selectedValues(name) {
        return Array.from(form.querySelectorAll('input[type="hidden"][name="' + name + '"]')).map(i => i.value);
    }

    function selectedAgreementIds() {
        const values = selectedValues('agreement_ids[]').map(String);

        if (values.length || agreementsPickerInitialized || initialAgreementIds.length === 0) {
            return values;
        }

        return initialAgreementIds;
    }

    function firstSelectedAgreementId() {
        return selectedAgreementIds()[0] || null;
    }

    function selectedAgreementConfigs() {
        return selectedAgreementIds().map(function (agreementId) {
            return agreementConfigs[agreementId] || null;
        }).filter(Boolean);
    }

    function shouldPreserveHistoricalParticipants() {
        if (!isEditMode || originalAgreementIds.length === 0) {
            return false;
        }

        const selected = new Set(selectedAgreementIds());

        return originalAgreementIds.every(function (agreementId) {
            return selected.has(String(agreementId));
        });
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

    function joinNames(items) {
        const names = items.map(function (item) {
            return item.name;
        }).filter(Boolean);

        if (names.length === 0) {
            return '';
        }

        if (names.length === 1) {
            return names[0];
        }

        if (names.length === 2) {
            return names[0] + ' and ' + names[1];
        }

        return names.slice(0, -1).join(', ') + ', and ' + names[names.length - 1];
    }

    function restrictCoveragePickers() {
        const agreements = selectedAgreementIds();
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

    function restrictParticipantPicker() {
        const agreements = selectedAgreementIds();
        const participantPicker = document.getElementById('activity-participants-picker');

        if (!participantPicker) return;

        const allowedParticipantIds = agreements.length ? uniqueMergedIds('participant_user_ids') : [];
        const preservedHistoricalIds = shouldPreserveHistoricalParticipants() ? historicalParticipantIds : [];
        const restrictedIds = Array.from(new Set(allowedParticipantIds.concat(preservedHistoricalIds).map(String)));
        const disabled = !agreements.length || allowedParticipantIds.length === 0;
        const placeholder = !agreements.length
            ? 'Select at least one agreement first...'
            : 'No covered users for selected agreements...';

        participantPicker.dispatchEvent(new CustomEvent('token-picker:set-disabled', {
            detail: {
                disabled: disabled,
                placeholder: placeholder,
            }
        }));
        participantPicker.dispatchEvent(new CustomEvent('token-picker:restrict', { detail: restrictedIds }));
    }

    function selectedParticipantIds() {
        return selectedValues('participant_user_ids[]').map(String);
    }

    function participantTimeRowsContainer() {
        return document.getElementById('participant-time-rows');
    }

    function participantLabel(userId) {
        return participantDirectory[String(userId)]?.label || ('User #' + userId);
    }

    function buildParticipantTimeInput(name, value, hidden, dataAttribute) {
        const input = document.createElement('input');
        input.type = 'number';
        input.step = '0.25';
        input.min = '0';
        input.className = 'form-control form-control-sm';
        input.name = name;
        input.value = value ?? '';

        if (dataAttribute) {
            input.setAttribute(dataAttribute, '');
        }

        if (hidden) {
            input.classList.add('d-none');
        }

        return input;
    }

    function collectParticipantTimesFromDom() {
        const rows = participantTimeRowsContainer();

        if (!rows) {
            return {};
        }

        const values = {};

        rows.querySelectorAll('[data-participant-time-row]').forEach(function (row) {
            const userId = String(row.dataset.userId || '');

            if (!userId) {
                return;
            }

            values[userId] = {
                user_id: userId,
                hours: row.querySelector('[data-participant-time-hours]')?.value || '',
                prep_hours: row.querySelector('[data-participant-time-prep-hours]')?.value || '0',
                follow_up_hours: row.querySelector('[data-participant-time-follow-up-hours]')?.value || '0',
                participant_name: row.dataset.participantName || participantLabel(userId),
            };
        });

        return values;
    }

    function renderParticipantTimeRows() {
        const rows = participantTimeRowsContainer();
        const emptyState = document.getElementById('participant-time-empty-state');
        const copyButton = document.getElementById('copy-contact-time-to-users');

        if (!rows) {
            return;
        }

        const currentValues = Object.assign({}, initialParticipantTimes, collectParticipantTimesFromDom());
        const selectedIds = selectedParticipantIds();
        const tracksAdditionalTime = !!contactFamilyAdditionalTimeMap[String(document.getElementById('contact_family_id')?.value || '')];

        rows.innerHTML = '';

        selectedIds.forEach(function (userId) {
            const rowData = currentValues[String(userId)] || {};
            const row = document.createElement('tr');
            const nameCell = document.createElement('td');
            const prepCell = document.createElement('td');
            const hoursCell = document.createElement('td');
            const followUpCell = document.createElement('td');
            const labelWrap = document.createElement('div');
            const label = document.createElement('span');
            const historicalBadge = document.createElement('span');
            const userInput = document.createElement('input');
            const prepHoursInput = buildParticipantTimeInput('participant_times[' + userId + '][prep_hours]', rowData.prep_hours ?? 0, !tracksAdditionalTime, 'data-participant-time-prep-hours');
            const hoursInput = buildParticipantTimeInput('participant_times[' + userId + '][hours]', rowData.hours ?? '', false, 'data-participant-time-hours');
            const followUpHoursInput = buildParticipantTimeInput('participant_times[' + userId + '][follow_up_hours]', rowData.follow_up_hours ?? 0, !tracksAdditionalTime, 'data-participant-time-follow-up-hours');

            row.dataset.userId = String(userId);
            row.dataset.participantName = rowData.participant_name || participantLabel(userId);
            row.setAttribute('data-participant-time-row', '');

            labelWrap.className = 'd-flex flex-wrap align-items-center gap-2';
            label.textContent = row.dataset.participantName;
            historicalBadge.className = 'badge bg-secondary';
            historicalBadge.textContent = 'Historical';
            historicalBadge.classList.toggle('d-none', !participantDirectory[String(userId)]?.historical);

            userInput.type = 'hidden';
            userInput.name = 'participant_times[' + userId + '][user_id]';
            userInput.value = userId;

            labelWrap.appendChild(label);
            labelWrap.appendChild(historicalBadge);
            nameCell.appendChild(labelWrap);
            nameCell.appendChild(userInput);

            prepCell.classList.toggle('d-none', !tracksAdditionalTime);
            followUpCell.classList.toggle('d-none', !tracksAdditionalTime);
            prepHoursInput.disabled = !tracksAdditionalTime;
            followUpHoursInput.disabled = !tracksAdditionalTime;
            prepCell.appendChild(prepHoursInput);
            hoursCell.appendChild(hoursInput);
            followUpCell.appendChild(followUpHoursInput);

            row.appendChild(nameCell);
            row.appendChild(prepCell);
            row.appendChild(hoursCell);
            row.appendChild(followUpCell);
            rows.appendChild(row);
        });

        if (emptyState) {
            emptyState.classList.toggle('d-none', selectedIds.length > 0);
        }

        if (copyButton) {
            copyButton.disabled = selectedIds.length === 0;
        }
    }

    function copyContactTimeToParticipants() {
        const contactActivityTime = document.getElementById('contact_time_activity_hours')?.value;
        const contactPrepTime = document.getElementById('contact_time_prep_hours')?.value || '0';
        const contactFollowUpTime = document.getElementById('contact_time_follow_up_hours')?.value || '0';
        const tracksAdditionalTime = !!contactFamilyAdditionalTimeMap[String(document.getElementById('contact_family_id')?.value || '')];

        if (!contactActivityTime) {
            return;
        }

        participantTimeRowsContainer()?.querySelectorAll('[data-participant-time-row]').forEach(function (row) {
            const hoursInput = row.querySelector('[data-participant-time-hours]');
            const prepInput = row.querySelector('[data-participant-time-prep-hours]');
            const followUpInput = row.querySelector('[data-participant-time-follow-up-hours]');

            if (hoursInput) {
                hoursInput.value = contactActivityTime;
            }

            if (tracksAdditionalTime && prepInput) {
                prepInput.value = contactPrepTime;
            }

            if (tracksAdditionalTime && followUpInput) {
                followUpInput.value = contactFollowUpTime;
            }
        });

        markDirty();
    }

    function updateAdditionalContactTimeFields() {
        const familyId = String(document.getElementById('contact_family_id')?.value || '');
        const tracksAdditionalTime = !!contactFamilyAdditionalTimeMap[familyId];

        document.querySelectorAll('[data-contact-additional-time-field]').forEach(function (fieldWrap) {
            const input = fieldWrap.querySelector('input');
            fieldWrap.classList.toggle('d-none', !tracksAdditionalTime);

            if (input) {
                input.disabled = !tracksAdditionalTime;
            }

            if (input && tracksAdditionalTime && input.value === '') {
                input.value = '0';
            }
        });

        document.querySelectorAll('[data-participant-extra-header]').forEach(function (header) {
            header.classList.toggle('d-none', !tracksAdditionalTime);
        });
    }

    function restrictScopePickers() {
        const agreements = selectedAgreementIds();
        const scopeSection = form.querySelector('[data-scope-id="activity-coverage-scope"]');
        const projectPicker = document.getElementById('activity-coverage-scope-projects');

        if (!scopeSection || !projectPicker) return;

        const allowedProjectIds = agreements.length ? uniqueMergedIds('project_ids') : [];
        const allowedProgramIds = agreements.length ? uniqueMergedIds('program_ids') : [];
        const disableProjects = !agreements.length || allowedProjectIds.length === 0;
        const projectPlaceholder = !agreements.length
            ? 'Select at least one agreement first...'
            : 'No covered projects for selected agreements...';
        const forceProgramDisabled = !agreements.length || allowedProgramIds.length === 0;
        const programDisabledPlaceholder = !agreements.length
            ? 'Select at least one agreement first...'
            : 'No covered programs for selected agreements...';

        projectPicker.dispatchEvent(new CustomEvent('token-picker:set-disabled', {
            detail: {
                disabled: disableProjects,
                placeholder: projectPlaceholder,
            }
        }));
        projectPicker.dispatchEvent(new CustomEvent('token-picker:restrict', { detail: allowedProjectIds }));
        scopeSection.dispatchEvent(new CustomEvent('project-program-scope:restrict', {
            detail: {
                programIds: allowedProgramIds,
                forceProgramDisabled: forceProgramDisabled,
                programDisabledPlaceholder: programDisabledPlaceholder,
            }
        }));
    }

    function restrictClassificationOptions() {
        const family = document.getElementById('contact_family_id');
        const type = document.getElementById('activity_type_id');
        const selectedType = document.getElementById('activity_type_selected');
        const agreements = selectedAgreementIds();

        if (!family || !type) return;

        const hasAgreementSelection = agreements.length > 0;
        const allowedFamilyIds = new Set(uniqueMergedIds('contact_family_ids'));
        const hasRestriction = hasAgreementSelection && allowedFamilyIds.size > 0;

        if (!hasAgreementSelection) {
            family.value = '';
            family.disabled = true;
            if (selectedType) {
                selectedType.value = '';
            }
            if (family.options.length > 0) {
                family.options[0].textContent = 'Select at least one agreement first...';
            }
            type.innerHTML = '<option value="">Select agreement first...</option>';
            type.disabled = true;
            updateContactFamilyLoggingGroups();
            updateActivityLoggingGroups();
            updateAdditionalContactTimeFields();
            return;
        }

        family.disabled = allowedFamilyIds.size === 0;

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
            updateContactFamilyLoggingGroups();
            updateActivityLoggingGroups();
        }

        if (hasAgreementSelection && allowedFamilyIds.size === 0) {
            family.value = '';
            if (selectedType) {
                selectedType.value = '';
            }
            type.innerHTML = '<option value="">No activity types available for selected agreements...</option>';
            updateContactFamilyLoggingGroups();
            updateActivityLoggingGroups();
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
        const selected = new Set(selectedAgreementIds());
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

    function updateTimeTrackingSection() {
        const selected = selectedAgreementConfigs();
        const section = document.getElementById('time-tracking-section');
        const contactGroup = section?.querySelector('[data-time-tracking-subsection="contact"]');
        const userGroup = section?.querySelector('[data-time-tracking-subsection="user"]');
        const contactRequiredBy = selected.filter(function (config) {
            return config.time_tracking_mode === 'by_contact' || config.time_tracking_mode === 'by_user';
        });
        const userRequiredBy = selected.filter(function (config) {
            return config.time_tracking_mode === 'by_user';
        });
        const hasTimeTracking = contactRequiredBy.length > 0;

        section?.classList.toggle('d-none', !hasTimeTracking);

        if (contactGroup) {
            contactGroup.classList.toggle('d-none', !hasTimeTracking);
            const label = contactGroup.querySelector('[data-time-tracking-contact-required-by]');
            if (label) {
                label.textContent = hasTimeTracking
                    ? 'Required by: ' + joinNames(contactRequiredBy)
                    : '';
            }
        }

        if (userGroup) {
            const hasUserTracking = userRequiredBy.length > 0;
            userGroup.classList.toggle('d-none', !hasUserTracking);
            const label = userGroup.querySelector('[data-time-tracking-user-required-by]');
            if (label) {
                label.textContent = hasUserTracking
                    ? 'Required by: ' + joinNames(userRequiredBy)
                    : '';
            }
        }

        updateAdditionalContactTimeFields();
        renderParticipantTimeRows();
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

    function updateActivityLoggingGroups() {
        const activityTypeId = document.getElementById('activity_type_id')?.value;
        const section = document.getElementById('activity-logging-section');
        let visibleGroups = 0;

        document.querySelectorAll('[data-activity-logging-group]').forEach(function (group) {
            const visible = group.dataset.activityLoggingGroup === activityTypeId;
            group.classList.toggle('d-none', !visible);
            group.querySelectorAll('input, textarea, select').forEach(function (field) {
                field.disabled = !visible;
            });
            visibleGroups += visible ? 1 : 0;
        });

        section?.classList.toggle('d-none', !activityTypeId || visibleGroups === 0);
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
        const firstAgreementValue = selectedAgreementIds()[0] || 'Template';
        const firstAgreementLabel = document.querySelector('#activity-agreements-picker [data-token-selected] span')?.textContent || firstAgreementValue;

        const payload = {
            name: firstAgreementLabel + ' · ' + new Date().toLocaleDateString(),
            agreement_ids: selectedAgreementIds(),
            organization_ids: selectedValues('organization_ids[]'),
            state_ids: selectedValues('state_ids[]'),
            project_ids: selectedValues('project_ids[]'),
            program_ids: selectedValues('program_ids[]'),
            participant_user_ids: selectedValues('participant_user_ids[]'),
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
                form.querySelector('#activity-coverage-scope-projects')?.dispatchEvent(new CustomEvent('token-picker:set', { detail: template.project_ids || [] }));
                form.querySelector('#activity-coverage-scope-programs')?.dispatchEvent(new CustomEvent('token-picker:set', { detail: template.program_ids || [] }));
                form.querySelector('#activity-participants-picker')?.dispatchEvent(new CustomEvent('token-picker:set', { detail: template.participant_user_ids || [] }));

                const family = document.getElementById('contact_family_id');
                const selectedType = document.getElementById('activity_type_selected');
                const internalOnly = document.getElementById('internal_only');

                if (family) family.value = template.contact_family_id || '';
                if (selectedType) selectedType.value = template.activity_type_id || '';
                if (internalOnly) internalOnly.checked = !!template.internal_only;

                if (family) htmx.trigger(family, 'change');

                markDirty();
                updateAgreementLoggingGroups();
                updateActivityLoggingGroups();
            });
            container.appendChild(button);
        });
    }

    const agreementsPicker = document.getElementById('activity-agreements-picker');
    if (agreementsPicker) {
        agreementsPicker.addEventListener('token-picker:change', function () {
            agreementsPickerInitialized = true;
            restrictCoveragePickers();
            restrictParticipantPicker();
            restrictScopePickers();
            restrictClassificationOptions();
            refreshActivityTypes();
            updateAgreementAutoFill();
            updateAgreementLoggingGroups();
            updateTimeTrackingSection();
            markDirty();
        });
        agreementsPicker.addEventListener('token-picker:initialized', function () {
            agreementsPickerInitialized = true;
            restrictCoveragePickers();
            restrictParticipantPicker();
            restrictScopePickers();
            restrictClassificationOptions();
            refreshActivityTypes();
            if (!isEditMode) {
                updateAgreementAutoFill();
            }
            updateAgreementLoggingGroups();
            updateTimeTrackingSection();
        });
    }

    ['activity-organizations-picker', 'activity-states-picker', 'activity-coverage-scope-projects', 'activity-coverage-scope-programs', 'activity-participants-picker'].forEach(function (id) {
        document.getElementById(id)?.addEventListener('token-picker:change', function () {
            if (id === 'activity-participants-picker') {
                renderParticipantTimeRows();
            }

            markDirty();
        });
    });

    document.getElementById('copy-contact-time-to-users')?.addEventListener('click', copyContactTimeToParticipants);

    form.addEventListener('input', markDirty);
    form.addEventListener('change', function (event) {
        if (event.target && event.target.id === 'contact_family_id') {
            const selectedType = document.getElementById('activity_type_selected');
            if (selectedType && event.isTrusted) selectedType.value = '';
            updateActivityTypeState();
            updateContactFamilyLoggingGroups();
            updateActivityLoggingGroups();
            updateAdditionalContactTimeFields();
            renderParticipantTimeRows();
        }
        if (event.target && event.target.id === 'activity_type_id') {
            updateActivityLoggingGroups();
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
    restrictParticipantPicker();
    restrictScopePickers();
    restrictClassificationOptions();
    updateAgreementLoggingGroups();
    updateTimeTrackingSection();
    updateContactFamilyLoggingGroups();
    updateActivityLoggingGroups();
    updateAdditionalContactTimeFields();
    renderParticipantTimeRows();

    if (!isEditMode) {
        renderTemplates();
    }
})();
</script>
