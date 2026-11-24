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
    'notYetCompleteChecked' => false,
    'completionCountValue' => 1,
    'selectedSubActivityId' => null,
    'subActivityLoggingData' => [],
    'allottedDurationHours' => null,
    'allottedDurationDays' => null,
    'activity' => null,
    'certificationForm' => [],
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
    $subActivitiesWithLoggingFields = $contactFamilies->flatMap(fn ($family) => $family->activityTypes)->flatMap(fn ($type) => $type->subActivities)->filter(fn ($sub) => $sub->subActivityLoggingFields->isNotEmpty())->values();
    $legacySubActivityExemption = $isEditMode && $activity->canKeepNoSubActivity($activity->activityType);
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
            $role = $user->accessLabel() ? ' (' . $user->accessLabel() . ')' : '';

            return [
                'value' => $user->id,
                'label' => $user->name . $role,
                'search' => trim($user->name . ' ' . ($user->email ?? '') . ' ' . $user->accessLabel()),
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
                    $role = $user->accessLabel() ? ' (' . $user->accessLabel() . ')' : '';

                    return [
                        'value' => $user->id,
                        'label' => $user->name . $role,
                        'search' => trim($user->name . ' ' . ($user->email ?? '') . ' ' . $user->accessLabel()),
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
    $selectedSubActivityLabel = $contactFamilies->flatMap(fn ($family) => $family->activityTypes)->flatMap(fn ($type) => $type->subActivities)->firstWhere('id', $selectedSubActivityId)?->name;
    if ($selectedSubActivityLabel) {
        $selectedActivityTypeLabel .= ' › '.$selectedSubActivityLabel;
    }
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

        <form method="POST" action="{{ $formAction }}" id="{{ $formId }}" enctype="multipart/form-data" data-activity-form>
            @csrf
            @if ($isEditMode)
                @method('PUT')
            @endif

            <x-page-header :form-id="$formId" :save-label="$saveLabel" duplicate-label="Save & Duplicate" :last-saved-at="$activity?->updated_at"
                context="form"
                :title="$isEditMode ? $recordName : null"
                entity-type="Activity"
                mode="{{ $isEditMode ? 'edit' : 'create' }}"
            />

                    <x-section-card title="Coverage">
                        <x-form-field label="Agreements" name="agreement_ids" :required="true">
                            <x-token-picker
                                picker-id="activity-agreements-picker"
                                name="agreement_ids[]"
                                :items="$agreements"
                                :selected-ids="$selectedAgreementIds"
                                placeholder="Search agreements…"
                                empty-message="No agreements are available."
                                :open-on-focus="false"
                                entity="agreement"
                            />
                        </x-form-field>

                        <div class="row g-3">
                            <div class="col-md-6">
                                <x-form-field label="States" name="state_ids" :required="true" class="mb-0">
                                    <x-token-picker
                                        picker-id="activity-states-picker"
                                        name="state_ids[]"
                                        :items="$states"
                                        :selected-ids="$selectedStateIds"
                                        placeholder="Search states…"
                                        disabled-placeholder="Select at least one agreement first…"
                                        :disabled="empty($selectedAgreementIds)"
                                        :open-on-focus="false"
                                        entity="state"
                                    />
                                </x-form-field>
                            </div>
                            <div class="col-md-6">
                                <x-form-field label="Organizations" name="organization_ids" :required="true" class="mb-0">
                                    <x-token-picker
                                        picker-id="activity-organizations-picker"
                                        name="organization_ids[]"
                                        :items="$organizations"
                                        :selected-ids="$selectedOrganizationIds"
                                        placeholder="Search organizations…"
                                        disabled-placeholder="Select at least one agreement first…"
                                        :disabled="empty($selectedAgreementIds)"
                                        :open-on-focus="false"
                                        entity="organization"
                                    />
                                </x-form-field>
                            </div>
                        </div>

                        <div class="mt-2">
                            <x-project-program-scope-picker
                                scope-id="activity-coverage-scope"
                                :projects="$availableProjects"
                                :selected-project-ids="$selectedProjectIds"
                                :selected-program-ids="$selectedProgramIds"
                                project-label="Projects *"
                                program-label="Programs *"
                                :project-disabled="empty($selectedAgreementIds)"
                                project-disabled-placeholder="Select at least one agreement first…"
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
                            <div class="w-100 mt-0"></div>
                            <div class="col-md">
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
                                        <option value="">{{ empty($selectedAgreementIds) ? 'Select at least one agreement first…' : 'Select family…' }}</option>
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
                            <div class="col-md">
                                <x-form-field label="Type" for="activity_type_id" name="activity_type_id" :required="true" class="mb-0">
                                    <select class="form-select @error('activity_type_id') is-invalid @enderror"
                                            id="activity_type_id"
                                            name="activity_type_id"
                                            {{ $currentContactFamilyId ? '' : 'disabled' }}
                                            required>
                                        <option value="">{{ empty($selectedAgreementIds) ? 'Select at least one agreement first…' : 'Select family first…' }}</option>
                                    </select>
                                    <div class="form-text d-none" id="activity_type_helper_text"></div>
                                </x-form-field>
                            </div>
                            <div class="col-md d-none" id="sub_activity_field">
                                <x-form-field label="Sub-activity" for="sub_activity_id" name="sub_activity_id" :required="true" class="mb-0">
                                    <select class="form-select @error('sub_activity_id') is-invalid @enderror" id="sub_activity_id" name="sub_activity_id"
                                            hx-get="{{ route('sub-activities.by-type') }}" hx-vals='js:{sub_activity_id: document.getElementById("sub_activity_selected").value}' hx-trigger="refresh-sub-activities" hx-target="this" hx-swap="innerHTML" hx-include="#{{ $formId }}">
                                        <option value="">Select sub-activity…</option>
                                    </select>
                                    <input type="hidden" id="sub_activity_selected" value="{{ $selectedSubActivityId }}">
                                    <div class="form-text d-none" id="sub_activity_helper_text"></div>
                                </x-form-field>
                            </div>
                            @if($isEditMode)
                                <div class="col-12 d-none" id="sub_activity_duration_warning_row">
                                    <div class="alert alert-warning py-2 mb-0" id="sub_activity_duration_warning" role="status"></div>
                                </div>
                            @endif
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
                                placeholder="Search covered users…"
                                disabled-placeholder="Select at least one program first…"
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
                            />
                            <x-form-switch
                                name="not_yet_complete"
                                label="Not yet complete"
                                help="Keep in history, count time, log activity, mark as moving forward; but exclude from deliverable progress."
                                :checked="$notYetCompleteChecked"
                                class="mb-0"
                            />
                        </x-form-options>
                    </x-section-card>

                    @include('activities.partials.certification-fields')

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
                                        <div class="form-text" id="allotted_duration_help"></div>
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
                                        <button type="button" class="btn btn-sm btn-outline-primary" id="copy-contact-time-to-users">
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

                            @foreach($subActivitiesWithLoggingFields as $subActivity)
                                <x-form-subsection
                                    title="Sub-activity"
                                    :meta="$subActivity->name"
                                    class="d-none"
                                    data-sub-activity-logging-group="{{ $subActivity->id }}"
                                >
                                    <div class="row g-3">
                                        @foreach($subActivity->subActivityLoggingFields as $field)
                                            <div class="{{ $field->is_full_width ? 'col-12' : 'col-md-6' }}"
                                                 data-logging-field-item
                                                 data-field-label="{{ e($field->name) }}"
                                                 data-scope-program-ids='@json($field->programs->pluck("id")->map(fn ($id) => (string) $id)->values())'>
                                                @include('activities.partials.logging-field-input', [
                                                    'field' => $field,
                                                    'inputName' => "sub_activity_logging_values[{$field->id}]",
                                                    'oldKey' => "sub_activity_logging_values.{$field->id}",
                                                    'value' => data_get($subActivityLoggingData, (string) $field->id),
                                                    'inputId' => "sub_activity_field_{$subActivity->id}_{$field->id}",
                                                    'downloadContext' => 'sub_activity',
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
                                                placeholder="Search payor sources…"
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
                                                placeholder="Search payee sources…"
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

            @php
                $activityFormConfig = [
                    'agreementConfigs' => $agreementConfigs,
                    'organizationConfigs' => $organizationConfigs,
                    'contactFamilyAdditionalTimeMap' => $contactFamilies->mapWithKeys(fn ($family) => [(string) $family->id => (bool) $family->track_additional_time]),
                    'participantDirectory' => $participantOptionMap,
                    'historicalParticipantIds' => $historicalParticipantIds,
                    'initialStateIds' => array_map('strval', $selectedStateIds),
                    'initialOrganizationIds' => array_map('strval', $selectedOrganizationIds),
                    'initialProjectIds' => array_map('strval', $selectedProjectIds),
                    'initialProgramIds' => array_map('strval', $selectedProgramIds),
                    'initialAgreementIds' => array_map('strval', $selectedAgreementIds),
                    'visibleStateIds' => $visibleStateIds,
                    'visibleOrganizationIds' => $visibleOrganizationIds,
                    'visibleProjectIds' => $visibleProjectIds,
                    'visibleProgramIds' => $visibleProgramIds,
                    'programUserIdsByProgramId' => $programUserIdsByProgramId,
                    'initialParticipantTimes' => $normalizedParticipantTimeData,
                    'initialCompletionCount' => (int) old('completion_count', $completionCountValue),
                    'initialAllottedDurationHours' => $allottedDurationHours !== null ? (float) $allottedDurationHours : null,
                    'initialAllottedDurationDays' => $allottedDurationDays !== null ? (float) $allottedDurationDays : null,
                    'isEditMode' => $isEditMode,
                    'savedActivityTypeId' => (string) ($activity?->activity_type_id ?? ''),
                    'savedSubActivityId' => (string) ($activity?->sub_activity_id ?? ''),
                    'savedAllottedDurationHours' => $activity?->allotted_duration_hours,
                    'savedAllottedDurationDays' => $activity?->allotted_duration_days,
                    'selectedActivityTypeId' => (string) $selectedActivityTypeId,
                    'legacySubActivityExemption' => $legacySubActivityExemption,
                ];
            @endphp
            <script type="application/json" data-activity-form-config>@json($activityFormConfig)</script>
        </form>
</x-form-shell>

@once
    @push('scripts')
        @vite('resources/js/activity-form.js')
    @endpush
@endonce
