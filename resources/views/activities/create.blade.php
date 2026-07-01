@extends('layouts.app')

@section('title', 'Log Activity')

@section('content')

@php
    $prefill = $duplicateData ?? [];
    $selectedAgreementIds = old('agreement_ids', $prefill['agreement_ids'] ?? ($preselectedAgreementId ? [$preselectedAgreementId] : []));
    $selectedOrganizationIds = old('organization_ids', $prefill['organization_ids'] ?? []);
    $selectedStateIds = old('state_ids', $prefill['state_ids'] ?? []);
    $selectedActivityTypeId = old('activity_type_id', $prefill['activity_type_id'] ?? null);
    $agreementLoggingData = old('agreement_logging_values', $prefill['agreement_logging_values'] ?? []);
    $contactFamilyLoggingData = old('contact_family_logging_values', $prefill['contact_family_logging_values'] ?? []);
    $engagementDateValue = old('engagement_date', $prefill['engagement_date'] ?? now()->format('Y-m-d'));
    $internalOnlyChecked = (bool) old('internal_only', $prefill['internal_only'] ?? false);
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
    :selected-activity-type-id="$selectedActivityTypeId"
    :agreement-logging-data="$agreementLoggingData"
    :contact-family-logging-data="$contactFamilyLoggingData"
    :engagement-date-value="$engagementDateValue"
    :internal-only-checked="$internalOnlyChecked"
/>

@endsection
