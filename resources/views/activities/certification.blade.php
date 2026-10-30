@extends('layouts.app')

@section('title', 'Review Certification')

@section('content')
@php
    $formId = 'activity-certification-form';
    $recordName = $activity->activityType->name.' · '.$activity->engagement_date->format('M j, Y');
@endphp

<x-form-shell>
    <x-form-errors />

    <form method="POST" action="{{ route('activities.certification.update', $activity) }}" id="{{ $formId }}">
        @csrf
        @method('PUT')

        <x-page-header :form-id="$formId" save-label="Save review" :last-saved-at="$activity->updated_at"
            context="form" :title="'Review certification · '.$recordName" entity-type="Activity" mode="edit" />

        {{-- The certification editor reads the activity's scope from these fields, which this page keeps fixed. --}}
        @foreach($activity->agreements as $agreement)<input type="hidden" name="agreement_ids[]" value="{{ $agreement->id }}">@endforeach
        @foreach($activity->programs as $program)<input type="hidden" name="program_ids[]" value="{{ $program->id }}">@endforeach
        <input type="hidden" id="activity_type_id" value="{{ $activity->activity_type_id }}">
        <input type="hidden" id="contact_family_id" value="{{ $activity->activityType->contact_family_id }}">
        <input type="checkbox" name="cancelled" value="1" @checked($activity->cancelled) hidden disabled>
        <input type="checkbox" name="not_yet_complete" value="1" @checked($activity->not_yet_complete) hidden disabled>

        @include('activities.partials.certification-fields')
    </form>
</x-form-shell>
@endsection
