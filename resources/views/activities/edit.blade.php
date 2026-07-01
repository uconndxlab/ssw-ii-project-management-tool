@extends('layouts.app')

@section('title', 'Edit Activity')

@section('content')

@php
    $selectedAgreementIds = old('agreement_ids', $activity->agreements->pluck('id')->toArray());
    $selectedOrganizationIds = old('organization_ids', $activity->organizations->pluck('id')->toArray());
    $selectedStateIds = old('state_ids', $activity->states->pluck('id')->toArray());
    $selectedActivityTypeId = old('activity_type_id', $activity->activity_type_id);
    $agreementLoggingData = old('agreement_logging_values', $activity->logging_field_data['agreements'] ?? []);
    $contactFamilyLoggingData = old('contact_family_logging_values', $activity->logging_field_data['contact_family'] ?? []);
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
    :selected-activity-type-id="$selectedActivityTypeId"
    :agreement-logging-data="$agreementLoggingData"
    :contact-family-logging-data="$contactFamilyLoggingData"
    :engagement-date-value="$engagementDateValue"
    :internal-only-checked="$internalOnlyChecked"
    :activity="$activity"
/>

@endsection
