@extends('layouts.app')

@section('title', 'Log Activity')

@section('content')

@php
    $selectedAgreementIds = old('agreement_ids', $preselectedAgreementId ? [$preselectedAgreementId] : []);
    $selectedOrganizationIds = old('organization_ids', []);
    $selectedStateIds = old('state_ids', []);
    $selectedProjectIds = old('project_ids', []);
    $selectedProgramIds = old('program_ids', []);
    $selectedParticipantUserIds = old('participant_user_ids', []);
    $selectedActivityTypeId = old('activity_type_id', null);
    $agreementLoggingData = old('agreement_logging_values', []);
    $contactFamilyLoggingData = old('contact_family_logging_values', []);
    $activityLoggingData = old('activity_logging_values', []);
    $engagementDateValue = old('engagement_date', now()->format('Y-m-d'));
    $internalOnlyChecked = (bool) old('internal_only', false);
@endphp

<x-activity-form
    form-mode="create"
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
    :engagement-date-value="$engagementDateValue"
    :internal-only-checked="$internalOnlyChecked"
/>

@endsection
