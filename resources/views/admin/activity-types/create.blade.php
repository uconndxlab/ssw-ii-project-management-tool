@extends('layouts.app')

@section('title', 'Create Activity Type')

@section('content')
@php
    $selectedActivityTypeLoggingFieldIds = old('activity_type_logging_field_ids', []);
    $requiredActivityTypeLoggingFieldIds = old('required_activity_type_logging_field_ids', []);
@endphp
<div class="row justify-content-center mb-4">
    <div class="col-lg-8">
        <h1>Create Activity Type</h1>
    </div>
</div>

<div class="row justify-content-center">
    <div class="col-lg-8">
        <div class="card">
            <div class="card-body">
                @if ($errors->any())
                    <div class="alert alert-danger">
                        <ul class="mb-0">
                            @foreach ($errors->all() as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                <form method="POST" action="{{ route('activity-types.store') }}" id="activity-types-create-form">
                    @csrf

                    <div class="mb-3">
                        <label for="contact_family_id" class="form-label">Contact Family <span class="text-danger">*</span></label>
                        <select class="form-select @error('contact_family_id') is-invalid @enderror"
                                id="contact_family_id"
                                name="contact_family_id"
                                required>
                            <option value="">Select contact family...</option>
                            @foreach($contactFamilies as $family)
                                <option value="{{ $family->id }}" {{ old('contact_family_id') == $family->id ? 'selected' : '' }}>
                                    {{ $family->name }}
                                </option>
                            @endforeach
                        </select>
                        @error('contact_family_id')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="mb-3">
                        <label for="name" class="form-label">Name <span class="text-danger">*</span></label>
                        <input type="text"
                               class="form-control @error('name') is-invalid @enderror"
                               id="name"
                               name="name"
                               value="{{ old('name') }}"
                               required>
                        @error('name')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Duration</label>
                        <div class="row g-2">
                            <div class="col-md-6">
                                <div class="input-group">
                                    <input type="number"
                                           class="form-control @error('duration_days') is-invalid @enderror"
                                           id="duration_days"
                                           name="duration_days"
                                           value="{{ old('duration_days', 0) }}"
                                           min="0"
                                           placeholder="0">
                                    <span class="input-group-text">days</span>
                                    @error('duration_days')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="input-group">
                                    <input type="number"
                                           class="form-control @error('duration_hours') is-invalid @enderror"
                                           id="duration_hours"
                                           name="duration_hours"
                                           value="{{ old('duration_hours', 0) }}"
                                           min="0"
                                           placeholder="0">
                                    <span class="input-group-text">hours</span>
                                    @error('duration_hours')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>
                        </div>
                        <div class="form-text">Duration associated with this activity type for reporting purposes. Both fields are optional and independent.</div>
                    </div>

                    <div class="mb-3">
                        <div class="form-check">
                            <input type="checkbox"
                                   class="form-check-input"
                                   id="active"
                                   name="active"
                                   value="1"
                                   {{ old('active', true) ? 'checked' : '' }}>
                            <label class="form-check-label" for="active">
                                Active
                            </label>
                        </div>
                        <div class="form-text">Only active activity types appear in activity forms.</div>
                    </div>

                    <div class="mb-4">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <div>
                                <h5 class="mb-1">Activity Logging Fields</h5>
                                <p class="text-muted small mb-0">These fields appear in the activity log when this activity type is selected.</p>
                            </div>
                            <a href="{{ route('logging-fields.index') }}" class="btn btn-sm btn-outline-secondary">Manage Logging Fields</a>
                        </div>

                        @if($activityTypeLoggingFields->isEmpty())
                            <div class="alert alert-light border mb-0">No activity logging fields have been defined yet.</div>
                        @else
                            <div class="border rounded">
                                @foreach($activityTypeLoggingFields as $field)
                                    <label class="d-flex align-items-start gap-3 px-3 py-2 border-bottom {{ $loop->last ? 'border-bottom-0' : '' }}">
                                        <input class="form-check-input mt-1"
                                               type="checkbox"
                                               name="activity_type_logging_field_ids[]"
                                               value="{{ $field->id }}"
                                               {{ in_array($field->id, $selectedActivityTypeLoggingFieldIds) ? 'checked' : '' }}>
                                        <div class="flex-grow-1">
                                            <div class="fw-semibold">{{ $field->name }}</div>
                                            <div class="small text-muted">{{ ucfirst($field->field_type) }}{{ $field->help_text ? ' · ' . $field->help_text : '' }}</div>
                                        </div>
                                        <div class="form-check m-0">
                                            <input class="form-check-input"
                                                   type="checkbox"
                                                   name="required_activity_type_logging_field_ids[]"
                                                   value="{{ $field->id }}"
                                                   {{ in_array($field->id, $requiredActivityTypeLoggingFieldIds) ? 'checked' : '' }}>
                                            <label class="form-check-label small">Required</label>
                                        </div>
                                    </label>
                                @endforeach
                            </div>
                        @endif
                    </div>

                </form>
            </div>
        </div>
    </div>
</div>
<x-save-bar form-id="activity-types-create-form" cancel-url="{{ route('activity-types.index') }}" save-label="Create Activity Type" />
@endsection
