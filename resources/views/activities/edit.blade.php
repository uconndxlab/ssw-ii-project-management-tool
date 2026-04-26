@extends('layouts.app')

@section('title', 'Edit Activity')

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
@endphp

<div class="row mb-4">
    <div class="col-12">
        <h1>Edit Activity</h1>
    </div>
</div>

<div class="row">
    <div class="col-lg-10">
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

                <form method="POST" action="{{ route('activities.update', $activity) }}">
                    @csrf
                    @method('PUT')

                    <div class="mb-3">
                        <label class="form-label">Agreements</label>
                        <x-agreement-picker
                            picker-id="activity-agreements"
                            name="agreement_ids[]"
                            :agreements="$agreements"
                            :selected-ids="old('agreement_ids', $activity->agreements->pluck('id')->toArray())"
                        />
                        @error('agreement_ids')
                            <div class="text-danger small mt-1">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Organizations</label>
                                <x-organization-picker
                                    picker-id="activity-organizations"
                                    name="organization_ids[]"
                                    :organizations="$organizations"
                                    :selected-ids="old('organization_ids', $activity->organizations->pluck('id')->toArray())"
                                />
                                @error('organization_ids')
                                    <div class="text-danger small mt-1">{{ $message }}</div>
                                @enderror
                            </div>
                        </div>

                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">States</label>
                                <x-state-picker
                                    picker-id="activity-states"
                                    name="state_ids[]"
                                    :states="$states"
                                    :selected-ids="old('state_ids', $activity->states->pluck('id')->toArray())"
                                />
                                @error('state_ids')
                                    <div class="text-danger small mt-1">{{ $message }}</div>
                                @enderror
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="engagement_date" class="form-label">Date <span class="text-danger">*</span></label>
                                <input type="date"
                                       class="form-control @error('engagement_date') is-invalid @enderror"
                                       id="engagement_date"
                                       name="engagement_date"
                                       value="{{ old('engagement_date', $activity->engagement_date->format('Y-m-d')) }}"
                                       required>
                                @error('engagement_date')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                        </div>

                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="contact_family_id" class="form-label">Contact Family <span class="text-danger">*</span></label>
                                <select class="form-select @error('contact_family_id') is-invalid @enderror"
                                        id="contact_family_id"
                                        name="contact_family_id"
                                        hx-get="{{ route('activity-types.by-family') }}"
                                        hx-target="#activity_type_id"
                                        hx-swap="innerHTML"
                                        hx-include="this"
                                        required>
                                    <option value="">Select contact family...</option>
                                    @foreach($contactFamilies as $family)
                                        <option value="{{ $family->id }}" {{ old('contact_family_id', $activity->activityType->contact_family_id ?? '') == $family->id ? 'selected' : '' }}>
                                            {{ $family->name }}
                                        </option>
                                    @endforeach
                                </select>
                                @error('contact_family_id')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label for="activity_type_id" class="form-label">Activity Type <span class="text-danger">*</span></label>
                        <select class="form-select @error('activity_type_id') is-invalid @enderror"
                                id="activity_type_id"
                                name="activity_type_id"
                                required>
                            <option value="">Select activity type...</option>
                            @foreach($activityTypes as $type)
                                <option value="{{ $type->id }}" {{ old('activity_type_id', $activity->activity_type_id) == $type->id ? 'selected' : '' }}>
                                    {{ $type->name }}
                                </option>
                            @endforeach
                        </select>
                        @error('activity_type_id')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="row">
                        <div class="col-md-4" data-config-key="event_hours">
                            <div class="mb-3">
                                <label for="event_hours" class="form-label">Event Hours <span class="text-danger">*</span></label>
                                <input type="number"
                                       class="form-control @error('event_hours') is-invalid @enderror"
                                       id="event_hours"
                                       name="event_hours"
                                       step="0.25"
                                       min="0"
                                       max="9999.99"
                                       value="{{ old('event_hours', $activity->event_hours) }}"
                                       data-required-when-enabled="true">
                                @error('event_hours')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                        </div>

                        <div class="col-md-4" data-config-key="prep_hours">
                            <div class="mb-3">
                                <label for="prep_hours" class="form-label">Prep Hours</label>
                                <input type="number"
                                       class="form-control @error('prep_hours') is-invalid @enderror"
                                       id="prep_hours"
                                       name="prep_hours"
                                       step="0.25"
                                       min="0"
                                       max="9999.99"
                                       value="{{ old('prep_hours', $activity->prep_hours ?? 0) }}">
                                @error('prep_hours')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                        </div>

                        <div class="col-md-4" data-config-key="followup_hours">
                            <div class="mb-3">
                                <label for="followup_hours" class="form-label">Follow-Up Hours</label>
                                <input type="number"
                                       class="form-control @error('followup_hours') is-invalid @enderror"
                                       id="followup_hours"
                                       name="followup_hours"
                                       step="0.25"
                                       min="0"
                                       max="9999.99"
                                       value="{{ old('followup_hours', $activity->followup_hours ?? 0) }}">
                                @error('followup_hours')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                        </div>
                    </div>

                    <div class="mb-3" data-config-key="participant_count">
                        <label for="participant_count" class="form-label">Participant Count</label>
                        <input type="number"
                               class="form-control @error('participant_count') is-invalid @enderror"
                               id="participant_count"
                               name="participant_count"
                               min="0"
                               value="{{ old('participant_count', $activity->participant_count) }}">
                        @error('participant_count')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="mb-3" data-config-key="external_attendees">
                        <label for="external_attendees" class="form-label">External Attendees</label>
                        <textarea class="form-control @error('external_attendees') is-invalid @enderror"
                                id="external_attendees"
                                name="external_attendees"
                                rows="3"
                                placeholder="Jane Smith, John Smith, Kansas Workforce Team">{{ old('external_attendees', $activity->external_attendees) }}</textarea>
                        @error('external_attendees')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                        <small class="text-muted">Enter a comma-separated list of external attendees</small>
                    </div>

                    <div class="mb-3">
                        <label for="program_ids" class="form-label">Programs</label>
                        <select class="form-select @error('program_ids') is-invalid @enderror"
                                id="program_ids"
                                name="program_ids[]"
                                multiple
                                size="5">
                            @foreach($programs as $program)
                                <option value="{{ $program->id }}"
                                    {{ in_array($program->id, old('program_ids', $activity->programs->pluck('id')->toArray())) ? 'selected' : '' }}>
                                    {{ $program->name }}
                                </option>
                            @endforeach
                        </select>
                        @error('program_ids')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                        <small class="text-muted">Hold Ctrl/Cmd to select multiple programs</small>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Internal Participants</label>
                        <div id="participants-container">
                            @include('activities.partials.participant-checkboxes', [
                                'agreement' => $activity->agreements->first()?->loadMissing('users'),
                                'selectedUserIds' => old('participant_user_ids', $activity->participants->pluck('id')->toArray()),
                                'pickerId' => 'activity-participants-edit',
                            ])
                        </div>
                        @error('participant_user_ids')
                            <div class="invalid-feedback d-block">{{ $message }}</div>
                        @enderror
                        <small class="text-muted">Check team members who participated in delivering this activity</small>
                    </div>

                    <div class="mb-3" data-config-key="summary">
                        <label for="summary" class="form-label">Summary</label>
                        <textarea class="form-control @error('summary') is-invalid @enderror"
                                  id="summary"
                                  name="summary"
                                  rows="3">{{ old('summary', $activity->summary) }}</textarea>
                        @error('summary')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="mb-3" data-config-key="follow_up">
                        <label for="follow_up" class="form-label">Follow-Up</label>
                        <textarea class="form-control @error('follow_up') is-invalid @enderror"
                                  id="follow_up"
                                  name="follow_up"
                                  rows="3">{{ old('follow_up', $activity->follow_up) }}</textarea>
                        @error('follow_up')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="mb-3" data-config-key="strengths">
                        <label for="strengths" class="form-label">Strengths</label>
                        <textarea class="form-control @error('strengths') is-invalid @enderror"
                                  id="strengths"
                                  name="strengths"
                                  rows="3">{{ old('strengths', $activity->strengths) }}</textarea>
                        @error('strengths')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="mb-3" data-config-key="recommendations">
                        <label for="recommendations" class="form-label">Recommendations</label>
                        <textarea class="form-control @error('recommendations') is-invalid @enderror"
                                  id="recommendations"
                                  name="recommendations"
                                  rows="3">{{ old('recommendations', $activity->recommendations) }}</textarea>
                        @error('recommendations')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="d-flex gap-2">
                        <button type="submit" class="btn btn-primary">Update Activity</button>
                        <a href="{{ route('activities.index') }}" class="btn btn-secondary">Cancel</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
const defaultActivityLoggingConfig = @json($defaultActivityLoggingConfig);
const agreementActivityConfigs = @json($agreementActivityConfigs);

function updateActivityLoggingFields() {
    const agreementSelect = document.getElementById('agreement_id');
    const agreementId = agreementSelect.value;

    const config = agreementActivityConfigs[agreementId] || defaultActivityLoggingConfig;

    document.querySelectorAll('[data-config-key]').forEach(function (wrapper) {
        const key = wrapper.dataset.configKey;
        const enabled = !!config[key];

        wrapper.style.display = enabled ? '' : 'none';

        wrapper.querySelectorAll('input, textarea, select').forEach(function (field) {
            field.disabled = !enabled;

            if (field.dataset.requiredWhenEnabled === 'true') {
                field.required = enabled;
            }

            if (!enabled) {
                if (field.tagName === 'TEXTAREA') {
                    field.value = '';
                } else if (field.type === 'number') {
                    field.value = '';
                } else {
                    field.value = '';
                }
            }
        });
    });
}

document.addEventListener('DOMContentLoaded', function() {
    const agreementSelect = document.getElementById('agreement_id');

    updateActivityLoggingFields();

    if (agreementSelect.value) {
        htmx.trigger(agreementSelect, 'change');
    }

    agreementSelect.addEventListener('change', function() {
        updateActivityLoggingFields();
    });
});
</script>
@endsection