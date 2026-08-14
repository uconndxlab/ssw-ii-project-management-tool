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
    'fundingSourceData' => [],
    'engagementDateValue' => null,
    'internalOnlyChecked' => false,
    'cancelledChecked' => false,
    'completionCountValue' => 1,
    'allottedDurationHours' => null,
    'allottedDurationDays' => null,
    'activity' => null,
])

@php
    use App\Support\ActivityFundingSourceTokens;
    use App\Support\ProgramParticipantDirectory;
    $availableOrganizationIds = collect($organizations)->pluck('id')->map(fn ($id) => (string) $id)->all();

    $agreementConfigs = $agreements->mapWithKeys(function ($agreement) use ($availableOrganizationIds) {
        $deliverables = $agreement->deliverables ?? collect();
        $kfsNumbersByOrganization = $agreement->organizationKfsAccounts
            ->groupBy(fn ($account) => (string) $account->pivot->organization_id);

        return [
            $agreement->id => [
                'name' => $agreement->name,
                'organization_ids' => $agreement->organizations
                    ->pluck('id')
                    ->map(fn ($id) => (string) $id)
                    ->filter(fn ($id) => in_array($id, $availableOrganizationIds, true))
                    ->values()
                    ->all(),
                'state_ids' => $agreement->states->pluck('id')->map(fn($id) => (string) $id)->values()->all(),
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
                'require_payor' => (bool) $agreement->require_payor,
                'require_payee' => (bool) $agreement->require_payee,
                'payor_organization_ids' => $agreement->organizations
                    ->filter(fn ($organization) => (bool) ($organization->pivot->payor_source ?? false))
                    ->filter(fn ($organization) => $kfsNumbersByOrganization->has((string) $organization->id))
                    ->pluck('id')
                    ->map(fn ($id) => (string) $id)
                    ->values()
                    ->all(),
                'payee_organization_ids' => $agreement->organizations
                    ->filter(fn ($organization) => preg_match('/^[0-9]{6}$/', (string) $organization->po_number) === 1)
                    ->pluck('id')
                    ->map(fn ($id) => (string) $id)
                    ->values()
                    ->all(),
                'payee_member_user_ids' => $agreement->users
                    ->concat($agreement->teams->flatMap(fn ($team) => $team->users))
                    ->filter(fn ($user) => preg_match('/^[0-9]{6}$/', (string) $user->po_number) === 1)
                    ->pluck('id')
                    ->map(fn ($id) => (string) $id)
                    ->unique()
                    ->values()
                    ->all(),
            ]
        ];
    });

    $fundingSourceOptionsByAgreement = ActivityFundingSourceTokens::fundingSourceOptionsByAgreement($agreements);
    $visibleStateIds = $agreements
        ->flatMap(fn ($agreement) => $agreement->states->pluck('id'))
        ->map(fn ($id) => (string) $id)
        ->unique()
        ->values()
        ->all();
    $visibleOrganizationIds = collect($organizations)
        ->pluck('id')
        ->map(fn ($id) => (string) $id)
        ->unique()
        ->values()
        ->all();
    $visibleProjectIds = $agreements
        ->flatMap(fn ($agreement) => $agreement->projects->pluck('id'))
        ->map(fn ($id) => (string) $id)
        ->unique()
        ->values()
        ->all();
    $visibleProgramIds = $agreements
        ->flatMap(fn ($agreement) => $agreement->programs->pluck('id'))
        ->map(fn ($id) => (string) $id)
        ->unique()
        ->values()
        ->all();
    $programParticipantDirectory = ProgramParticipantDirectory::build(
        $agreements->flatMap(fn ($agreement) => $agreement->programs)->unique('id')->values()
    );
    $programUserIdsByProgramId = $programParticipantDirectory['user_ids_by_program_id'];
    $organizationConfigs = collect($organizations)
        ->mapWithKeys(function ($organization) use ($visibleProjectIds, $visibleProgramIds) {
            $programs = $organization->programs ?? collect();

            return [
                $organization->id => [
                    'state_ids' => $organization->states
                        ->pluck('id')
                        ->map(fn ($id) => (string) $id)
                        ->unique()
                        ->values()
                        ->all(),
                    'program_ids' => $programs
                        ->pluck('id')
                        ->map(fn ($id) => (string) $id)
                        ->filter(fn ($id) => in_array($id, $visibleProgramIds, true))
                        ->unique()
                        ->values()
                        ->all(),
                    'project_ids' => $programs
                        ->flatMap(fn ($program) => $program->projects->pluck('id'))
                        ->map(fn ($id) => (string) $id)
                        ->filter(fn ($id) => in_array($id, $visibleProjectIds, true))
                        ->unique()
                        ->values()
                        ->all(),
                ],
            ];
        })
        ->all();

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
    $formId = $isEditMode ? 'activity-edit-form' : 'activity-create-form';
    $formAction = $isEditMode ? route('activities.update', $activity) : route('activities.store');
    $saveLabel = $isEditMode ? 'Save Activity' : 'Log Activity';
    $agreementsWithPerAgreementFields = $agreements->filter(function ($agreement) {
        return $agreement->agreementLoggingFields->isNotEmpty()
            || $agreement->require_payor
            || $agreement->require_payee;
    })->values();
    $activityTypesWithLoggingFields = $contactFamilies
        ->flatMap(fn ($family) => $family->activityTypes)
        ->filter(fn ($type) => $type->activityTypeLoggingFields->isNotEmpty())
        ->values();
    $contactFamiliesWithLoggingFields = $contactFamilies
        ->filter(fn ($family) => $family->contactFamilyLoggingFields->isNotEmpty())
        ->values();
    $selectedAgreementIdSet = collect($selectedAgreementIds)->map(fn ($id) => (string) $id);
    $showLoggingFieldsSection = $contactFamiliesWithLoggingFields
            ->contains(fn ($family) => (string) $family->id === (string) $currentContactFamilyId)
        || $activityTypesWithLoggingFields
            ->contains(fn ($type) => (string) $type->id === (string) $selectedActivityTypeId)
        || $agreementsWithPerAgreementFields
            ->contains(fn ($agreement) => $selectedAgreementIdSet->contains((string) $agreement->id));
    $availableProjects = $agreements
        ->flatMap(fn ($agreement) => $agreement->projects)
        ->unique('id')
        ->sortBy('name')
        ->values();
    $savedParticipantIds = $isEditMode && $activity
        ? $activity->participants
            ->pluck('id')
            ->concat($activity->participantTimes->pluck('user_id')->filter())
            ->map(fn ($id) => (string) $id)
            ->unique()
            ->values()
        : collect();
    $participantOptions = $programParticipantDirectory['users']
        ->map(function ($user) use ($savedParticipantIds) {
            $role = !empty($user->role) ? ' (' . ucfirst($user->role) . ')' : '';

            return [
                'value' => $user->id,
                'label' => $user->name . $role,
                'search' => trim($user->name . ' ' . ($user->email ?? '') . ' ' . ($user->role ?? '')),
                'contextBadgeClass' => $savedParticipantIds->contains((string) $user->id) ? 'bg-secondary' : 'bg-primary-subtle text-primary-emphasis border',
            ];
        });

    $currentContactFamily = $contactFamilies->first(fn ($family) => (string) $family->id === (string) $currentContactFamilyId);
    $selectedContactFamilyTracksAdditionalTime = (bool) ($currentContactFamily?->track_additional_time);

    if ($isEditMode && $activity) {
        $directoryOptionIds = $participantOptions->pluck('value')->map(fn ($id) => (string) $id);
        $historicalFromTimes = $activity->relationLoaded('participantTimes')
            ? $activity->participantTimes->filter(fn ($participantTime) => $participantTime->user_id)
            : collect();
        $historicalFromParticipants = $activity->relationLoaded('participants')
            ? $activity->participants
            : collect();

        $historicalParticipantOptions = $historicalFromTimes
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
            })
            ->concat(
                $historicalFromParticipants->map(function ($user) {
                    $role = !empty($user->role) ? ' (' . ucfirst($user->role) . ')' : '';

                    return [
                        'value' => $user->id,
                        'label' => $user->name . $role,
                        'search' => trim($user->name . ' ' . ($user->email ?? '') . ' ' . ($user->role ?? '')),
                        'context' => 'Historical',
                        'contextBadgeClass' => 'bg-secondary',
                    ];
                })
            )
            ->filter(fn ($option) => !$directoryOptionIds->contains((string) $option['value']))
            ->unique('value')
            ->values();

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

    $historicalParticipantIds = $savedParticipantIds->all();

    $selectedActivityTypeLabel = $contactFamilies
        ->flatMap(fn ($family) => $family->activityTypes)
        ->first(fn ($type) => (string) $type->id === (string) $selectedActivityTypeId)?->name
        ?? $activity?->activityType?->name
        ?? 'Activity';
    $recordDateLabel = 'No date';

    if (filled($engagementDateValue)) {
        try {
            $recordDateLabel = \Illuminate\Support\Carbon::parse($engagementDateValue)->format('M j, Y');
        } catch (\Throwable $exception) {
            $recordDateLabel = (string) $engagementDateValue;
        }
    }

    $recordName = $isEditMode ? $selectedActivityTypeLabel . ' · ' . $recordDateLabel : null;
@endphp

<x-form-shell>
        <x-form-errors />

        <form method="POST" action="{{ $formAction }}" id="{{ $formId }}" enctype="multipart/form-data">
            @csrf
            @if ($isEditMode)
                @method('PUT')
            @endif

            <x-page-header
                context="form"
                :title="$isEditMode ? $recordName : null"
                entity-type="Activity"
                mode="{{ $isEditMode ? 'edit' : 'create' }}"
            />

                    <x-section-card title="Coverage">
                        <x-form-field label="Agreements" name="agreement_ids" :required="true" help="Remaining coverage options are limited to these agreements.">
                            <x-token-picker
                                picker-id="activity-agreements-picker"
                                name="agreement_ids[]"
                                :items="$agreements"
                                :selected-ids="$selectedAgreementIds"
                                placeholder="Search agreements..."
                                empty-message="No agreements are available."
                                :open-on-focus="false"
                                entity="agreement"
                            />
                        </x-form-field>

                        <div class="row g-3">
                            <div class="col-md-6">
                                <x-form-field label="States" name="state_ids" :required="true" help="Limited to the selected agreements." class="mb-0">
                                    <x-token-picker
                                        picker-id="activity-states-picker"
                                        name="state_ids[]"
                                        :items="$states"
                                        :selected-ids="$selectedStateIds"
                                        placeholder="Search states..."
                                        disabled-placeholder="Select at least one agreement first..."
                                        :disabled="empty($selectedAgreementIds)"
                                        :open-on-focus="false"
                                        entity="state"
                                    />
                                </x-form-field>
                            </div>
                            <div class="col-md-6">
                                <x-form-field label="Organizations" name="organization_ids" :required="true" help="Limited to the selected agreements, and optionally by states." class="mb-0">
                                    <x-token-picker
                                        picker-id="activity-organizations-picker"
                                        name="organization_ids[]"
                                        :items="$organizations"
                                        :selected-ids="$selectedOrganizationIds"
                                        placeholder="Search organizations..."
                                        disabled-placeholder="Select at least one agreement first..."
                                        :disabled="empty($selectedAgreementIds)"
                                        :open-on-focus="false"
                                        entity="organization"
                                    />
                                </x-form-field>
                            </div>
                        </div>

                        <div class="mt-4">
                            <x-project-program-scope-picker
                                scope-id="activity-coverage-scope"
                                :projects="$availableProjects"
                                :selected-project-ids="$selectedProjectIds"
                                :selected-program-ids="$selectedProgramIds"
                                project-label="Projects *"
                                program-label="Programs *"
                                :project-disabled="empty($selectedAgreementIds)"
                                project-disabled-placeholder="Select at least one agreement first..."
                                project-help-text="Limited to the selected agreements."
                                program-help-text="Limited to the selected agreements and projects."
                                :expand-empty-programs="false"
                            />
                        </div>
                    </x-section-card>

                    <x-section-card title="Details">
                        <div class="row g-3">
                            <div class="col-md-4">
                                <x-form-field label="Date" for="engagement_date" name="engagement_date" :required="true" class="mb-0">
                                    <input type="date"
                                           class="form-control @error('engagement_date') is-invalid @enderror"
                                           id="engagement_date"
                                           name="engagement_date"
                                           value="{{ $engagementDateValue }}"
                                           required>
                                </x-form-field>
                            </div>
                            <div class="col-md-4">
                                <x-form-field label="Family" for="contact_family_id" name="contact_family_id" :required="true" class="mb-0">
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
                                        <option value="">{{ empty($selectedAgreementIds) ? 'Select at least one agreement first...' : 'Select family...' }}</option>
                                        @foreach($contactFamilies as $family)
                                            <option value="{{ $family->id }}"
                                                    data-helper-text="{{ e($family->helper_text ?? '') }}"
                                                    {{ (string) $currentContactFamilyId === (string) $family->id ? 'selected' : '' }}>
                                                {{ $family->name }}
                                            </option>
                                        @endforeach
                                    </select>
                                    <input type="hidden" id="activity_type_selected" value="{{ $selectedActivityTypeId }}">
                                    <div class="form-text d-none" id="contact_family_helper_text"></div>
                                </x-form-field>
                            </div>
                            <div class="col-md-4">
                                <x-form-field label="Type" for="activity_type_id" name="activity_type_id" :required="true" class="mb-0">
                                    <select class="form-select @error('activity_type_id') is-invalid @enderror"
                                            id="activity_type_id"
                                            name="activity_type_id"
                                            {{ $currentContactFamilyId ? '' : 'disabled' }}
                                            required>
                                        <option value="">{{ empty($selectedAgreementIds) ? 'Select at least one agreement first...' : 'Select family first...' }}</option>
                                    </select>
                                    <div class="form-text d-none" id="activity_type_helper_text"></div>
                                </x-form-field>
                            </div>
                        </div>

                        <x-form-field label="Delivered By" name="participant_user_ids" class="mt-4" help="Users on the selected programs who helped deliver this activity.">
                            <x-token-picker
                                picker-id="activity-participants-picker"
                                name="participant_user_ids[]"
                                :options="$participantOptions"
                                :selected-ids="$selectedParticipantUserIds"
                                label-key="label"
                                value-key="value"
                                search-key="search"
                                placeholder="Search covered users..."
                                disabled-placeholder="Select at least one program first..."
                                :disabled="empty($selectedProgramIds) && empty($historicalParticipantIds)"
                                :open-on-focus="false"
                                entity="user"
                            />
                        </x-form-field>

                        <x-form-options class="mt-4">
                            <x-form-switch
                                name="internal_only"
                                label="Internal only"
                                help="Exclude from external reports."
                                :checked="$internalOnlyChecked"
                            />
                            <x-form-switch
                                name="cancelled"
                                label="Cancelled"
                                help="Keep in history, but exclude from deliverable progress."
                                :checked="$cancelledChecked"
                                class="mb-0"
                            />
                        </x-form-options>
                    </x-section-card>

                    <x-section-card title="Time Tracking" id="time-tracking-section">
                        <div class="form-subsections">
                            <x-form-subsection title="Duration" meta="Completions multiply allotted totals." id="activity-duration-section">
                                <div class="row g-3">
                                    <div class="col-md-6">
                                        <label for="allotted_duration_display" class="form-label">Allotted Duration</label>
                                        <input type="text"
                                               class="form-control"
                                               id="allotted_duration_display"
                                               value=""
                                               readonly
                                               disabled
                                               placeholder="Select an activity type">
                                        <div class="form-text" id="allotted_duration_help">Set by the selected activity type.</div>
                                    </div>
                                    <div class="col-md-6">
                                        <label for="completion_count" class="form-label required-label">Completions</label>
                                        <input type="number"
                                               class="form-control @error('completion_count') is-invalid @enderror"
                                               id="completion_count"
                                               name="completion_count"
                                               value="{{ old('completion_count', $completionCountValue) }}"
                                               min="1"
                                               max="999"
                                               step="1"
                                               required>
                                        @error('completion_count')
                                            <div class="invalid-feedback">{{ $message }}</div>
                                        @enderror
                                        <div class="form-text">How many completions this log represents. Observed time is not multiplied.</div>
                                    </div>
                                </div>
                            </x-form-subsection>

                            <x-form-subsection
                                title="Time by Contact"
                                :meta="$timeTrackingContactRequiredBy"
                                class="{{ $hasTimeTracking ? '' : 'd-none' }}"
                                data-time-tracking-subsection="contact"
                            >
                                    <div class="row g-3">
                                        <div class="col-md-4 {{ $selectedContactFamilyTracksAdditionalTime ? '' : 'd-none' }}" data-contact-additional-time-field="prep_hours">
                                            <label for="contact_time_prep_hours" class="form-label">Prep Time</label>
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
                                            <label for="contact_time_activity_hours" class="form-label required-label">
                                                Activity Time
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
                                            <label for="contact_time_follow_up_hours" class="form-label">Follow Up Time</label>
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
                            </x-form-subsection>

                            <x-form-subsection
                                title="Time by User"
                                :meta="$timeTrackingUserRequiredBy"
                                class="{{ $timeTrackingUserConfigs->isNotEmpty() ? '' : 'd-none' }}"
                                data-time-tracking-subsection="user"
                            >
                                    <div class="d-flex justify-content-end mb-3">
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
                                        Select Delivered By above to track time for each user.
                                    </div>
                                    @if($participantTimeErrorMessage)
                                        <div class="text-danger small mt-2">{{ $participantTimeErrorMessage }}</div>
                                    @endif
                            </x-form-subsection>
                        </div>
                    </x-section-card>

                    <div id="logging-fields-section" class="{{ $showLoggingFieldsSection ? '' : 'd-none' }}">
                        <x-section-card title="Logging Fields">
                            <div class="form-subsections">
                            @foreach($contactFamiliesWithLoggingFields as $family)
                                <x-form-subsection
                                    title="Family"
                                    :meta="$family->name"
                                    class="d-none"
                                    data-contact-family-logging-group="{{ $family->id }}"
                                >
                                    <div class="row g-3">
                                        @foreach($family->contactFamilyLoggingFields as $field)
                                            <div class="{{ $field->is_full_width ? 'col-12' : 'col-md-6' }}"
                                                 data-logging-field-item
                                                 data-field-label="{{ e($field->name) }}"
                                                 data-scope-program-ids='@json($field->programs->pluck("id")->map(fn ($id) => (string) $id)->values())'>
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
                                    <div class="alert alert-warning small mt-3 mb-0 d-none" data-logging-scope-warning>
                                        <span data-logging-scope-warning-text></span>
                                    </div>
                                </x-form-subsection>
                            @endforeach

                            @foreach($activityTypesWithLoggingFields as $activityType)
                                <x-form-subsection
                                    title="Type"
                                    :meta="$activityType->name"
                                    class="d-none"
                                    data-activity-logging-group="{{ $activityType->id }}"
                                >
                                    <div class="row g-3">
                                        @foreach($activityType->activityTypeLoggingFields as $field)
                                            <div class="{{ $field->is_full_width ? 'col-12' : 'col-md-6' }}"
                                                 data-logging-field-item
                                                 data-field-label="{{ e($field->name) }}"
                                                 data-scope-program-ids='@json($field->programs->pluck("id")->map(fn ($id) => (string) $id)->values())'>
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
                                    <div class="alert alert-warning small mt-3 mb-0 d-none" data-logging-scope-warning>
                                        <span data-logging-scope-warning-text></span>
                                    </div>
                                </x-form-subsection>
                            @endforeach

                            @foreach($agreementsWithPerAgreementFields as $agreement)
                                @php
                                    $fundingOptions = $fundingSourceOptionsByAgreement[$agreement->id] ?? ['payor' => [], 'payee' => []];
                                    $selectedPayorTokens = old(
                                        "funding_sources.{$agreement->id}.payor",
                                        data_get($fundingSourceData, "{$agreement->id}.payor", [])
                                    );
                                    $selectedPayeeTokens = old(
                                        "funding_sources.{$agreement->id}.payee",
                                        data_get($fundingSourceData, "{$agreement->id}.payee", [])
                                    );
                                @endphp
                                <x-form-subsection
                                    title="Agreement"
                                    :meta="$agreement->name"
                                    class="d-none"
                                    data-agreement-logging-group="{{ $agreement->id }}"
                                >

                                    @if($agreement->agreementLoggingFields->isNotEmpty())
                                        <div class="row g-3 mb-3">
                                            @foreach($agreement->agreementLoggingFields as $field)
                                                <div class="{{ $field->is_full_width ? 'col-12' : 'col-md-6' }}"
                                                     data-logging-field-item
                                                     data-field-label="{{ e($field->name) }}"
                                                     data-scope-program-ids='@json($field->programs->pluck("id")->map(fn ($id) => (string) $id)->values())'>
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
                                    @endif

                                    @if($agreement->require_payor)
                                        <div class="mb-3" data-funding-source-picker data-agreement-id="{{ $agreement->id }}" data-funding-role="payor">
                                            <label class="form-label">Payor Sources</label>
                                            <p class="text-muted small mb-2">Optional. Select from agreement organizations that are marked as payor sources and linked to one or more KFS accounts on this agreement.</p>
                                            <x-token-picker
                                                picker-id="activity-funding-payor-{{ $agreement->id }}"
                                                name="funding_sources[{{ $agreement->id }}][payor][]"
                                                :options="$fundingOptions['payor']"
                                                label-key="label"
                                                value-key="value"
                                                search-key="search"
                                                :selected-ids="$selectedPayorTokens"
                                                placeholder="Search payor sources..."
                                :height="'220px'"
                                            />
                                            <div class="text-muted small mt-2 d-none" data-funding-empty-notice="{{ $agreement->id }}-payor">
                                                No payor sources available - no payor organizations on this agreement are linked to KFS accounts.
                                            </div>
                                            @error("funding_sources.{$agreement->id}.payor")
                                                <div class="text-danger small mt-1">{{ $message }}</div>
                                            @enderror
                                            @error("funding_sources.{$agreement->id}.payor.*")
                                                <div class="text-danger small mt-1">{{ $message }}</div>
                                            @enderror
                                        </div>
                                    @endif

                                    @if($agreement->require_payee)
                                        <div class="mb-0" data-funding-source-picker data-agreement-id="{{ $agreement->id }}" data-funding-role="payee">
                                            <label class="form-label">Payee Sources</label>
                                            <p class="text-muted small mb-2">Optional. Select from users and organizations with PO numbers. This payee field is separate from the agreement KFS payor setup.</p>
                                            <x-token-picker
                                                picker-id="activity-funding-payee-{{ $agreement->id }}"
                                                name="funding_sources[{{ $agreement->id }}][payee][]"
                                                :options="$fundingOptions['payee']"
                                                label-key="label"
                                                value-key="value"
                                                search-key="search"
                                                :selected-ids="$selectedPayeeTokens"
                                                placeholder="Search payee sources..."
                                :height="'220px'"
                                            />
                                            <div class="text-muted small mt-2 d-none" data-funding-empty-notice="{{ $agreement->id }}-payee">
                                                No payee sources available - no users or organizations with PO numbers are available on this agreement.
                                            </div>
                                            @error("funding_sources.{$agreement->id}.payee")
                                                <div class="text-danger small mt-1">{{ $message }}</div>
                                            @enderror
                                            @error("funding_sources.{$agreement->id}.payee.*")
                                                <div class="text-danger small mt-1">{{ $message }}</div>
                                            @enderror
                                        </div>
                                    @endif

                                    @if($agreement->agreementLoggingFields->isNotEmpty())
                                        <div class="alert alert-warning small mt-3 mb-0 d-none" data-logging-scope-warning>
                                            <span data-logging-scope-warning-text></span>
                                        </div>
                                    @endif
                                </x-form-subsection>
                            @endforeach
                            </div>
                        </x-section-card>
                    </div>
        </form>
</x-form-shell>

<x-save-bar
    :form-id="$formId"
    :save-label="$saveLabel"
    :last-saved-at="$activity?->updated_at"
/>

<script>
(function () {
    const agreementConfigs = @json($agreementConfigs);
    const organizationConfigs = @json($organizationConfigs);
    const contactFamilyAdditionalTimeMap = @json($contactFamilies->mapWithKeys(fn ($family) => [(string) $family->id => (bool) $family->track_additional_time]));
    const participantDirectory = @json($participantOptionMap);
    const historicalParticipantIds = @json($historicalParticipantIds);
    const initialStateIds = @json(array_map('strval', $selectedStateIds));
    const initialOrganizationIds = @json(array_map('strval', $selectedOrganizationIds));
    const initialProjectIds = @json(array_map('strval', $selectedProjectIds));
    const initialProgramIds = @json(array_map('strval', $selectedProgramIds));
    const initialAgreementIds = @json(array_map('strval', $selectedAgreementIds));
    const visibleStateIds = @json($visibleStateIds);
    const visibleOrganizationIds = @json($visibleOrganizationIds);
    const visibleProjectIds = @json($visibleProjectIds);
    const visibleProgramIds = @json($visibleProgramIds);
    const programUserIdsByProgramId = @json($programUserIdsByProgramId);
    const initialParticipantTimes = @json($normalizedParticipantTimeData);
    const initialCompletionCount = @json((int) old('completion_count', $completionCountValue));
    let initialAllottedDurationHours = @json($allottedDurationHours !== null ? (float) $allottedDurationHours : null);
    let initialAllottedDurationDays = @json($allottedDurationDays !== null ? (float) $allottedDurationDays : null);
    const form = document.getElementById(@json($formId));
    const isEditMode = @json($isEditMode);
    let agreementsPickerInitialized = false;
    let programsPickerInitialized = false;
    let preserveInitialCoverageSelections = isEditMode;
    let coverageInteractionDetected = false;

    if (!form) return;

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

    function uniqueMergedProgramUserIds() {
        const merged = new Set();

        resolvedSelectedProgramIds().forEach(function (programId) {
            (programUserIdsByProgramId[String(programId)] || []).forEach(function (value) {
                merged.add(String(value));
            });
        });

        return Array.from(merged);
    }

    function resolvedSelectedProgramIds() {
        const values = selectedProgramIds();

        if (values.length || programsPickerInitialized || initialProgramIds.length === 0) {
            return values;
        }

        return initialProgramIds.map(String);
    }

    function allowedProgramParticipantIds() {
        return resolvedSelectedProgramIds().length ? uniqueMergedProgramUserIds() : [];
    }

    function isHistoricalParticipant(userId) {
        const id = String(userId);

        if (!historicalParticipantIds.includes(id)) {
            return false;
        }

        return allowedProgramParticipantIds().indexOf(id) === -1;
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

    function selectedStateIds() {
        return selectedValues('state_ids[]').map(String);
    }

    function selectedOrganizationIds() {
        return selectedValues('organization_ids[]').map(String);
    }

    function selectedProjectIds() {
        return selectedValues('project_ids[]').map(String);
    }

    function selectedProgramIds() {
        return selectedValues('program_ids[]').map(String);
    }

    function intersectionValues(values, allowedValues) {
        const allowedSet = new Set((allowedValues || []).map(String));

        return Array.from(new Set((values || []).map(String))).filter(function (value) {
            return allowedSet.has(String(value));
        });
    }

    function mergePreservedCoverage(allowedValues, preservedValues) {
        const normalizedAllowed = Array.isArray(allowedValues) ? allowedValues.map(String) : [];

        if (!preserveInitialCoverageSelections) {
            return normalizedAllowed;
        }

        return Array.from(new Set(normalizedAllowed.concat((preservedValues || []).map(String))));
    }

    function parseJson(text, fallback) {
        try {
            return JSON.parse(text || '');
        } catch (error) {
            return fallback;
        }
    }

    function noteCoverageInteraction(event) {
        if (event.isTrusted) {
            coverageInteractionDetected = true;
        }
    }

    function stopPreservingInitialCoverageIfNeeded() {
        if (coverageInteractionDetected) {
            preserveInitialCoverageSelections = false;
            coverageInteractionDetected = false;
        }
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

    function escapeHtml(value) {
        return String(value)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#39;');
    }

    function joinLabels(labels) {
        const names = (labels || []).filter(Boolean);
        const formattedNames = names.map(function (name) {
            return '<strong>' + escapeHtml(name) + '</strong>';
        });

        if (names.length === 0) {
            return '';
        }

        if (names.length === 1) {
            return formattedNames[0];
        }

        if (names.length === 2) {
            return formattedNames[0] + ' and ' + formattedNames[1];
        }

        return formattedNames.slice(0, -1).join(', ') + ', and ' + formattedNames[formattedNames.length - 1];
    }

    function setFieldContainerDisabled(container, disabled) {
        container.querySelectorAll('input, textarea, select').forEach(function (field) {
            field.disabled = disabled;
        });
    }

    function loggingFieldMatchesPrograms(fieldItem, programIds) {
        const scopedProgramIds = parseJson(fieldItem.dataset.scopeProgramIds, []).map(String);

        if (scopedProgramIds.length === 0) {
            return true;
        }

        if ((programIds || []).length === 0) {
            return false;
        }

        return scopedProgramIds.some(function (programId) {
            return programIds.includes(String(programId));
        });
    }

    function updateLoggingScopeWarning(group, hiddenLabels) {
        const warning = group.querySelector('[data-logging-scope-warning]');
        const text = warning?.querySelector('[data-logging-scope-warning-text]');
        const labels = Array.from(new Set((hiddenLabels || []).filter(Boolean)));

        if (!warning || !text) {
            return;
        }

        if (labels.length === 0) {
            warning.classList.add('d-none');
            text.innerHTML = '';
            return;
        }

        text.innerHTML = joinLabels(labels) + ' ' + (labels.length === 1 ? 'is' : 'are') + ' not required under the current program selection.';
        warning.classList.remove('d-none');
    }

    function applyLoggingFieldProgramScope(group) {
        const programIds = selectedProgramIds();
        const hiddenLabels = [];

        group.querySelectorAll('[data-logging-field-item]').forEach(function (fieldItem) {
            const visible = loggingFieldMatchesPrograms(fieldItem, programIds);

            fieldItem.classList.toggle('d-none', !visible);
            setFieldContainerDisabled(fieldItem, !visible);

            if (!visible && fieldItem.dataset.fieldLabel) {
                hiddenLabels.push(fieldItem.dataset.fieldLabel);
            }
        });

        updateLoggingScopeWarning(group, hiddenLabels);
    }

    function restrictStatePicker() {
        const statePicker = document.getElementById('activity-states-picker');
        const agreements = selectedAgreementIds();
        const allowedStateIds = agreements.length ? uniqueMergedIds('state_ids') : [];

        if (!statePicker) {
            return;
        }

        statePicker.dispatchEvent(new CustomEvent('token-picker:set-disabled', {
            detail: {
                disabled: agreements.length === 0 || allowedStateIds.length === 0,
                placeholder: agreements.length === 0
                    ? 'Select at least one agreement first...'
                    : allowedStateIds.length === 0
                    ? 'No states are available from the selected agreements...'
                    : 'Search states...',
            }
        }));
        statePicker.dispatchEvent(new CustomEvent('token-picker:restrict', { detail: mergePreservedCoverage(allowedStateIds, initialStateIds) }));
    }

    function restrictOrganizationPicker() {
        const orgPicker = document.getElementById('activity-organizations-picker');
        const agreements = selectedAgreementIds();
        const states = selectedStateIds();

        if (!orgPicker) {
            return;
        }

        const agreementOrganizationIds = agreements.length ? uniqueMergedIds('organization_ids') : [];
        const allowedOrganizationIds = agreementOrganizationIds.filter(function (organizationId) {
                const config = organizationConfigs[String(organizationId)] || {};

                return states.length === 0 || intersectionValues(config.state_ids || [], states).length > 0;
            });

        orgPicker.dispatchEvent(new CustomEvent('token-picker:set-disabled', {
            detail: {
                disabled: agreements.length === 0 || allowedOrganizationIds.length === 0,
                placeholder: agreements.length === 0
                    ? 'Select at least one agreement first...'
                    : 'No organizations match the selected states...',
            }
        }));
        orgPicker.dispatchEvent(new CustomEvent('token-picker:restrict', {
            detail: mergePreservedCoverage(allowedOrganizationIds, initialOrganizationIds)
        }));
    }

    function restrictParticipantPicker() {
        const programs = resolvedSelectedProgramIds();
        const participantPicker = document.getElementById('activity-participants-picker');

        if (!participantPicker) return;

        const allowedParticipantIds = allowedProgramParticipantIds();
        const preservedHistoricalIds = historicalParticipantIds.filter(function (id) {
            return allowedParticipantIds.indexOf(String(id)) === -1;
        });
        const restrictedIds = Array.from(new Set(allowedParticipantIds.concat(preservedHistoricalIds).map(String)));
        const disabled = restrictedIds.length === 0;
        let placeholder = 'Search covered users...';

        if (programs.length === 0) {
            placeholder = preservedHistoricalIds.length === 0
                ? 'Select at least one program first...'
                : 'Search historical participants...';
        } else if (allowedParticipantIds.length === 0 && preservedHistoricalIds.length === 0) {
            placeholder = 'No covered users for selected programs...';
        }

        const historicalContexts = {};
        restrictedIds.forEach(function (userId) {
            historicalContexts[String(userId)] = isHistoricalParticipant(userId) ? 'Historical' : '';
        });

        participantPicker.dispatchEvent(new CustomEvent('token-picker:set-disabled', {
            detail: {
                disabled: disabled,
                placeholder: placeholder,
            }
        }));
        participantPicker.dispatchEvent(new CustomEvent('token-picker:update-option-contexts', {
            detail: historicalContexts,
        }));
        participantPicker.dispatchEvent(new CustomEvent('token-picker:restrict', { detail: restrictedIds }));
    }

    function eligibleFundingTokens(agreementId, role) {
        const config = agreementConfigs[String(agreementId)];

        if (!config) {
            return [];
        }

        const tokens = [];

        if (role === 'payor') {
            (config.payor_organization_ids || []).forEach(function (id) {
                tokens.push('organization:' + id);
            });
        } else {
            (config.payee_organization_ids || []).forEach(function (id) {
                tokens.push('organization:' + id);
            });

            (config.payee_member_user_ids || []).forEach(function (id) {
                tokens.push('user:' + id);
            });
        }

        return tokens;
    }

    function restrictFundingSourcePickers() {
        const selectedAgreements = new Set(selectedAgreementIds());

        document.querySelectorAll('[data-funding-source-picker]').forEach(function (wrapper) {
            const agreementId = wrapper.dataset.agreementId;
            const role = wrapper.dataset.fundingRole;
            const picker = wrapper.querySelector('[data-token-picker]');
            const emptyNotice = document.querySelector('[data-funding-empty-notice="' + agreementId + '-' + role + '"]');
            const config = agreementConfigs[String(agreementId)] || {};
            const requiresRole = role === 'payor' ? config.require_payor : config.require_payee;

            if (!picker) {
                return;
            }

            if (!requiresRole || !selectedAgreements.has(String(agreementId))) {
                picker.dispatchEvent(new CustomEvent('token-picker:set-disabled', {
                    detail: {
                        disabled: true,
                        placeholder: 'Not enabled for this agreement.',
                    },
                }));
                emptyNotice?.classList.add('d-none');

                return;
            }

            const eligible = eligibleFundingTokens(agreementId, role);
            const roleLabel = role === 'payor' ? 'payor' : 'payee';

            if (eligible.length === 0) {
                picker.dispatchEvent(new CustomEvent('token-picker:set-disabled', {
                    detail: {
                        disabled: true,
                        placeholder: role === 'payor'
                            ? 'No payor organizations linked to KFS accounts on this agreement...'
                            : 'No payee sources with PO numbers on this agreement...',
                    },
                }));
                emptyNotice?.classList.remove('d-none');
            } else {
                picker.dispatchEvent(new CustomEvent('token-picker:set-disabled', {
                    detail: {
                        disabled: false,
                        placeholder: 'Search ' + roleLabel + ' sources...',
                    },
                }));
                emptyNotice?.classList.add('d-none');
            }

            picker.dispatchEvent(new CustomEvent('token-picker:restrict', { detail: eligible }));
        });
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
            historicalBadge.classList.toggle('d-none', !isHistoricalParticipant(userId));

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
        const disableProjects = agreements.length === 0 || allowedProjectIds.length === 0;
        const projectPlaceholder = agreements.length === 0
            ? 'Select at least one agreement first...'
            : 'No projects are available from the selected agreements...';
        const forceProgramDisabled = agreements.length === 0 || allowedProgramIds.length === 0;
        const programDisabledPlaceholder = agreements.length === 0
            ? 'Select at least one agreement first...'
            : 'No programs are available from the selected agreements...';

        projectPicker.dispatchEvent(new CustomEvent('token-picker:set-disabled', {
            detail: {
                disabled: disableProjects,
                placeholder: projectPlaceholder,
            }
        }));
        projectPicker.dispatchEvent(new CustomEvent('token-picker:restrict', {
            detail: mergePreservedCoverage(allowedProjectIds, initialProjectIds)
        }));
        scopeSection.dispatchEvent(new CustomEvent('project-program-scope:restrict', {
            detail: {
                programIds: mergePreservedCoverage(allowedProgramIds, initialProgramIds),
                forceProjectDisabled: disableProjects,
                forceProgramDisabled: forceProgramDisabled,
                projectDisabledPlaceholder: projectPlaceholder,
                programDisabledPlaceholder: programDisabledPlaceholder,
            }
        }));
    }

    function restrictAgreementPicker() {
        const agreementPicker = document.getElementById('activity-agreements-picker');

        if (!agreementPicker) {
            return;
        }

        agreementPicker.dispatchEvent(new CustomEvent('token-picker:set-disabled', {
            detail: {
                disabled: false,
                placeholder: 'Search agreements...',
            }
        }));
        agreementPicker.dispatchEvent(new CustomEvent('token-picker:restrict', {
            detail: mergePreservedCoverage(Object.keys(agreementConfigs), initialAgreementIds)
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
                    ? 'No activity families available for selected agreements...'
                    : 'Select family...';
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
            type.innerHTML = '<option value="">Select family first...</option>';
            type.disabled = true;
            return;
        }

        htmx.trigger(family, 'change');
    }

    function updateLoggingFieldsSectionVisibility() {
        const section = document.getElementById('logging-fields-section');

        if (!section) {
            return;
        }

        const visible = Array.from(section.querySelectorAll(
            '[data-contact-family-logging-group], [data-activity-logging-group], [data-agreement-logging-group]'
        )).some(function (group) {
            return !group.classList.contains('d-none');
        });

        section.classList.toggle('d-none', !visible);
    }

    function updateAgreementLoggingGroups() {
        const selected = new Set(selectedAgreementIds());

        document.querySelectorAll('[data-agreement-logging-group]').forEach(function (group) {
            const visible = selected.has(group.dataset.agreementLoggingGroup);
            group.classList.toggle('d-none', !visible);

            if (!visible) {
                group.querySelectorAll('input, textarea, select').forEach(function (field) {
                    field.disabled = true;
                });
            } else {
                group.querySelectorAll('input, textarea, select').forEach(function (field) {
                    field.disabled = false;
                });
                applyLoggingFieldProgramScope(group);
            }
        });

        updateLoggingFieldsSectionVisibility();
        restrictFundingSourcePickers();
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

        if (contactGroup) {
            contactGroup.classList.toggle('d-none', !hasTimeTracking);
            const label = contactGroup.querySelector('.form-subsection-meta');
            if (label) {
                label.textContent = hasTimeTracking
                    ? 'Required by: ' + joinNames(contactRequiredBy)
                    : '';
                label.classList.toggle('d-none', !label.textContent);
            }
        }

        if (userGroup) {
            const hasUserTracking = userRequiredBy.length > 0;
            userGroup.classList.toggle('d-none', !hasUserTracking);
            const label = userGroup.querySelector('.form-subsection-meta');
            if (label) {
                label.textContent = hasUserTracking
                    ? 'Required by: ' + joinNames(userRequiredBy)
                    : '';
                label.classList.toggle('d-none', !label.textContent);
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

            if (!visible) {
                group.querySelectorAll('input, textarea, select').forEach(function (field) {
                    field.disabled = true;
                });
            } else {
                group.querySelectorAll('input, textarea, select').forEach(function (field) {
                    field.disabled = false;
                });
                applyLoggingFieldProgramScope(group);
            }
        });

        updateLoggingFieldsSectionVisibility();
    }

    function updateActivityLoggingGroups() {
        const activityTypeId = document.getElementById('activity_type_id')?.value;

        document.querySelectorAll('[data-activity-logging-group]').forEach(function (group) {
            const visible = group.dataset.activityLoggingGroup === activityTypeId;
            group.classList.toggle('d-none', !visible);

            if (!visible) {
                group.querySelectorAll('input, textarea, select').forEach(function (field) {
                    field.disabled = true;
                });
            } else {
                group.querySelectorAll('input, textarea, select').forEach(function (field) {
                    field.disabled = false;
                });
                applyLoggingFieldProgramScope(group);
            }
        });

        updateLoggingFieldsSectionVisibility();
        updateAllottedDurationDisplay();
    }

    function formatDurationValue(hours, days) {
        if (days && parseFloat(days) > 0) {
            const value = parseFloat(days);
            return value + ' ' + (value === 1 ? 'day' : 'days');
        }

        if (hours && parseFloat(hours) > 0) {
            const value = parseFloat(hours);
            return value + ' ' + (value === 1 ? 'hour' : 'hours');
        }

        return '';
    }

    function durationFromActivityTypeOption(option) {
        if (!option || !option.value) {
            return { hours: null, days: null };
        }

        const hours = parseFloat(option.dataset.durationHours || '');
        const days = parseFloat(option.dataset.durationDays || '');

        return {
            hours: Number.isFinite(hours) && hours > 0 ? hours : null,
            days: Number.isFinite(days) && days > 0 ? days : null,
        };
    }

    function updateAllottedDurationDisplay(forceSnapshot) {
        const displayInput = document.getElementById('allotted_duration_display');
        const typeSelect = document.getElementById('activity_type_id');

        if (!displayInput) {
            return;
        }

        let hours = null;
        let days = null;

        if (forceSnapshot || (isEditMode && (initialAllottedDurationHours !== null || initialAllottedDurationDays !== null))) {
            hours = initialAllottedDurationHours;
            days = initialAllottedDurationDays;
        } else if (typeSelect && typeSelect.value) {
            const duration = durationFromActivityTypeOption(typeSelect.options[typeSelect.selectedIndex]);
            hours = duration.hours;
            days = duration.days;
        }

        const formatted = formatDurationValue(hours, days);
        displayInput.value = formatted;

        const selectedOption = typeSelect && typeSelect.value ? typeSelect.options[typeSelect.selectedIndex] : null;
        const typeName = selectedOption && selectedOption.text ? selectedOption.text.trim() : '';
        const help = document.getElementById('allotted_duration_help');

        if (!typeName) {
            displayInput.placeholder = 'Select an activity type';
            if (help) {
                help.textContent = 'Set by the selected activity type.';
            }
            return;
        }

        displayInput.placeholder = formatted ? '' : 'No duration configured for this activity type';
        if (help) {
            help.textContent = formatted
                ? 'Set by ' + typeName + '.'
                : 'No duration configured for ' + typeName + '.';
        }
    }

    function updateActivityTypeState() {
        const family = document.getElementById('contact_family_id');
        const type = document.getElementById('activity_type_id');
        if (!family || !type) return;
        type.disabled = !family.value;
    }

    function updateSelectHelperText(selectId, targetId) {
        const select = document.getElementById(selectId);
        const target = document.getElementById(targetId);

        if (!select || !target) {
            return;
        }

        const selectedOption = select.options[select.selectedIndex];
        const helperText = selectedOption?.dataset?.helperText || '';

        target.textContent = helperText;
        target.classList.toggle('d-none', helperText.trim() === '');
    }

    function markDirty() {}

    const agreementsPicker = document.getElementById('activity-agreements-picker');
    if (agreementsPicker) {
        agreementsPicker.addEventListener('token-picker:change', function () {
            if (agreementsPickerInitialized) {
                preserveInitialCoverageSelections = false;
            }
            agreementsPickerInitialized = true;
            restrictStatePicker();
            restrictOrganizationPicker();
            restrictScopePickers();
            restrictParticipantPicker();
            restrictFundingSourcePickers();
            restrictClassificationOptions();
            refreshActivityTypes();
            updateAgreementLoggingGroups();
            updateTimeTrackingSection();
            markDirty();
        });
        agreementsPicker.addEventListener('token-picker:initialized', function () {
            agreementsPickerInitialized = true;
            restrictStatePicker();
            restrictOrganizationPicker();
            restrictScopePickers();
            restrictParticipantPicker();
            restrictFundingSourcePickers();
            restrictClassificationOptions();
            refreshActivityTypes();
            updateAgreementLoggingGroups();
            updateTimeTrackingSection();
        });
    }

    document.getElementById('activity-states-picker')?.addEventListener('click', noteCoverageInteraction, true);
    document.getElementById('activity-states-picker')?.addEventListener('input', noteCoverageInteraction, true);
    document.getElementById('activity-states-picker')?.addEventListener('token-picker:change', function () {
        stopPreservingInitialCoverageIfNeeded();
        restrictOrganizationPicker();
        markDirty();
    });

    document.getElementById('activity-organizations-picker')?.addEventListener('click', noteCoverageInteraction, true);
    document.getElementById('activity-organizations-picker')?.addEventListener('input', noteCoverageInteraction, true);
    document.getElementById('activity-organizations-picker')?.addEventListener('token-picker:change', function () {
        stopPreservingInitialCoverageIfNeeded();
        markDirty();
    });

    form.querySelector('[data-scope-id="activity-coverage-scope"]')?.addEventListener('click', noteCoverageInteraction, true);
    form.querySelector('[data-scope-id="activity-coverage-scope"]')?.addEventListener('input', noteCoverageInteraction, true);
    form.querySelector('[data-scope-id="activity-coverage-scope"]')?.addEventListener('project-program-scope:change', function () {
        programsPickerInitialized = true;
        stopPreservingInitialCoverageIfNeeded();
        restrictParticipantPicker();
        updateAgreementLoggingGroups();
        updateContactFamilyLoggingGroups();
        updateActivityLoggingGroups();
        markDirty();
    });

    document.getElementById('activity-participants-picker')?.addEventListener('token-picker:change', function () {
        renderParticipantTimeRows();
        markDirty();
    });

    document.getElementById('copy-contact-time-to-users')?.addEventListener('click', copyContactTimeToParticipants);

    form.addEventListener('input', markDirty);
    form.addEventListener('change', function (event) {
        if (event.target && event.target.id === 'contact_family_id') {
            const selectedType = document.getElementById('activity_type_selected');
            if (selectedType && event.isTrusted) selectedType.value = '';
            updateActivityTypeState();
            updateSelectHelperText('contact_family_id', 'contact_family_helper_text');
            updateContactFamilyLoggingGroups();
            updateActivityLoggingGroups();
            updateAdditionalContactTimeFields();
            renderParticipantTimeRows();
        }
        if (event.target && event.target.id === 'activity_type_id') {
            if (event.isTrusted) {
                initialAllottedDurationHours = null;
                initialAllottedDurationDays = null;
            }
            updateSelectHelperText('activity_type_id', 'activity_type_helper_text');
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
        updateSelectHelperText('activity_type_id', 'activity_type_helper_text');
        updateAllottedDurationDisplay(false);
    });

    const family = document.getElementById('contact_family_id');
    if (family && family.value) htmx.trigger(family, 'change');

    updateActivityTypeState();
    restrictStatePicker();
    restrictOrganizationPicker();
    restrictScopePickers();
    restrictAgreementPicker();
    restrictParticipantPicker();
    restrictFundingSourcePickers();
    restrictClassificationOptions();
    updateAgreementLoggingGroups();
    updateTimeTrackingSection();
    updateContactFamilyLoggingGroups();
    updateActivityLoggingGroups();
    updateAdditionalContactTimeFields();
    updateAllottedDurationDisplay(isEditMode);
    updateSelectHelperText('contact_family_id', 'contact_family_helper_text');
    updateSelectHelperText('activity_type_id', 'activity_type_helper_text');
    renderParticipantTimeRows();
})();
</script>
