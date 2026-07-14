@extends('layouts.app')

@section('title', 'Edit Activity')

@section('content')

@php
    $selectedAgreementIds = old('agreement_ids', $activity->agreements->pluck('id')->toArray());
    $selectedOrganizationIds = old('organization_ids', $activity->organizations->pluck('id')->toArray());
    $selectedStateIds = old('state_ids', $activity->states->pluck('id')->toArray());
    $selectedProjectIds = old('project_ids', $activity->projects->pluck('id')->toArray());
    $selectedProgramIds = old('program_ids', $activity->programs->pluck('id')->toArray());
    $selectedParticipantUserIds = old(
        'participant_user_ids',
        $activity->participants
            ->pluck('id')
            ->concat($activity->participantTimes->pluck('user_id')->filter())
            ->unique()
            ->values()
            ->toArray()
    );
    $selectedActivityTypeId = old('activity_type_id', $activity->activity_type_id);
    $agreementLoggingData = old('agreement_logging_values', $activity->agreement_logging_values ?? []);
    $contactFamilyLoggingData = old('contact_family_logging_values', $activity->contact_family_logging_values ?? []);
    $activityLoggingData = old('activity_logging_values', $activity->activity_type_logging_values ?? []);
    $contactTimeData = old('contact_time', [
        'activity_hours' => $activity->contactTime?->activity_hours,
        'prep_hours' => $activity->contactTime?->prep_hours ?? 0,
        'follow_up_hours' => $activity->contactTime?->follow_up_hours ?? 0,
    ]);
    $participantTimeData = old(
        'participant_times',
        $activity->participantTimes
            ->whereNotNull('user_id')
            ->mapWithKeys(fn ($participantTime) => [
                $participantTime->user_id => [
                    'user_id' => $participantTime->user_id,
                    'hours' => $participantTime->hours,
                    'prep_hours' => $participantTime->prep_hours,
                    'follow_up_hours' => $participantTime->follow_up_hours,
                    'participant_name' => $participantTime->participant_name,
                ],
            ])
            ->all()
    );
    $engagementDateValue = old('engagement_date', $activity->engagement_date?->format('Y-m-d'));
    $internalOnlyChecked = (bool) old('internal_only', $activity->internal_only);
@endphp

<x-activity-form
    form-mode="edit"
    :agreements="$agreements"
    :organizations="$organizations"
    :states="$states"
    :contact-families="$contactFamilies"
    :current-contact-family-id="$currentContactFamilyId"
    :selected-agreement-ids="$selectedAgreementIds"
    :selected-organization-ids="$selectedOrganizationIds"
    :selected-state-ids="$selectedStateIds"
    :selected-project-ids="$selectedProjectIds"
    :selected-program-ids="$selectedProgramIds"
    :selected-participant-user-ids="$selectedParticipantUserIds"
    :selected-activity-type-id="$selectedActivityTypeId"
    :agreement-logging-data="$agreementLoggingData"
    :contact-family-logging-data="$contactFamilyLoggingData"
    :activity-logging-data="$activityLoggingData"
    :contact-time-data="$contactTimeData"
    :participant-time-data="$participantTimeData"
    :engagement-date-value="$engagementDateValue"
    :internal-only-checked="$internalOnlyChecked"
    :activity="$activity"
/>

@endsection
