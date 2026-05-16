@extends('layouts.app')

@section('title', 'Edit Agreement')

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

    $activityLoggingConfig = old(
        'activity_logging_config',
        $agreement->activity_logging_config ?? $defaultActivityLoggingConfig
    );
    $selectedAgreementLoggingFieldIds = old('agreement_logging_field_ids', $agreement->agreementLoggingFields->pluck('id')->toArray());
    $requiredAgreementLoggingFieldIds = old(
        'required_agreement_logging_field_ids',
        $agreement->agreementLoggingFields->filter(fn ($field) => $field->pivot->is_required)->pluck('id')->toArray()
    );
@endphp

<div class="row mb-4">
    <div class="col-12">
        <h1>Edit Agreement</h1>
    </div>
</div>

<div class="row">
    <div class="col-md-8">
        <div class="card mb-4">
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

                <form method="POST" action="{{ route('agreements.update', $agreement) }}" id="agreements-edit-form">
                    @csrf
                    @method('PUT')

                    <div class="mb-3">
                        <label for="name" class="form-label">Agreement Name</label>
                        <input type="text"
                               class="form-control @error('name') is-invalid @enderror"
                               id="name"
                               name="name"
                               value="{{ old('name', $agreement->name) }}"
                               required>
                        @error('name')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Organizations</label>
                                <x-organization-picker
                                    picker-id="agreement-organizations"
                                    name="organization_ids[]"
                                    :organizations="$organizations"
                                    :selected-ids="old('organization_ids', $agreement->organizations->pluck('id')->toArray())"
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
                                    picker-id="agreement-states"
                                    name="state_ids[]"
                                    :states="$states"
                                    :selected-ids="old('state_ids', $agreement->states->pluck('id')->toArray())"
                                />
                                @error('state_ids')
                                    <div class="text-danger small mt-1">{{ $message }}</div>
                                @enderror
                            </div>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label for="abstract" class="form-label">Abstract</label>
                        <textarea class="form-control @error('abstract') is-invalid @enderror"
                                  id="abstract"
                                  name="abstract"
                                  rows="4">{{ old('abstract', $agreement->abstract) }}</textarea>
                        @error('abstract')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="start_date" class="form-label">Start Date</label>
                                <input type="date"
                                       class="form-control @error('start_date') is-invalid @enderror"
                                       id="start_date"
                                       name="start_date"
                                       value="{{ old('start_date', $agreement->start_date?->format('Y-m-d')) }}">
                                @error('start_date')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                        </div>

                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="end_date" class="form-label">End Date</label>
                                <input type="date"
                                       class="form-control @error('end_date') is-invalid @enderror"
                                       id="end_date"
                                       name="end_date"
                                       value="{{ old('end_date', $agreement->end_date?->format('Y-m-d')) }}">
                                @error('end_date')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="original_end_date" class="form-label">Original End Date</label>
                                <input type="date"
                                       class="form-control @error('original_end_date') is-invalid @enderror"
                                       id="original_end_date"
                                       name="original_end_date"
                                       value="{{ old('original_end_date', $agreement->original_end_date?->format('Y-m-d')) }}">
                                @error('original_end_date')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                                <small class="text-muted">For tracking agreement extensions</small>
                            </div>
                        </div>

                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="extended_end_date" class="form-label">Extended End Date</label>
                                <input type="date"
                                       class="form-control @error('extended_end_date') is-invalid @enderror"
                                       id="extended_end_date"
                                       name="extended_end_date"
                                       value="{{ old('extended_end_date', $agreement->extended_end_date?->format('Y-m-d')) }}">
                                @error('extended_end_date')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label for="certification_candidates" class="form-label">Certification Candidates</label>
                        <textarea class="form-control @error('certification_candidates') is-invalid @enderror"
                                  id="certification_candidates"
                                  name="certification_candidates"
                                  rows="3">{{ old('certification_candidates', $agreement->certification_candidates) }}</textarea>
                        @error('certification_candidates')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                        <small class="text-muted">List of certification candidates (placeholder)</small>
                    </div>

                    <hr class="my-4">

                    <div class="mb-4">
                        <h5 class="mb-1">Time Tracking Method</h5>
                        <p class="text-muted small mb-2">How time is recorded for all activities under this agreement.</p>
                        <div class="form-check mb-2">
                            <input class="form-check-input" type="radio" id="time_tracking_engagement"
                                   name="time_tracking_mode" value="engagement"
                                   {{ old('time_tracking_mode', $agreement->time_tracking_mode ?? 'engagement') === 'engagement' ? 'checked' : '' }}>
                            <label class="form-check-label" for="time_tracking_engagement">
                                <strong>Time by Engagement</strong>
                                <small class="text-muted d-block">One total time value for the entire activity.</small>
                            </label>
                        </div>
                        <div class="form-check mb-2">
                            <input class="form-check-input" type="radio" id="time_tracking_participant"
                                   name="time_tracking_mode" value="participant"
                                   {{ old('time_tracking_mode', $agreement->time_tracking_mode ?? 'engagement') === 'participant' ? 'checked' : '' }}>
                            <label class="form-check-label" for="time_tracking_participant">
                                <strong>Time by Participant</strong>
                                <small class="text-muted d-block">Track individual time per team member.</small>
                            </label>
                        </div>
                    </div>

                    <div class="mb-4">
                        <h5 class="mb-3">Activity Logging Fields</h5>

                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-check mb-2">
                                    <input class="form-check-input"
                                           type="checkbox"
                                           name="activity_logging_config[event_hours]"
                                           id="activity_logging_config_event_hours"
                                           value="1"
                                           {{ !empty($activityLoggingConfig['event_hours']) ? 'checked' : '' }}>
                                    <label class="form-check-label" for="activity_logging_config_event_hours">
                                        Event Hours
                                    </label>
                                </div>

                                <div class="form-check mb-2">
                                    <input class="form-check-input"
                                           type="checkbox"
                                           name="activity_logging_config[prep_hours]"
                                           id="activity_logging_config_prep_hours"
                                           value="1"
                                           {{ !empty($activityLoggingConfig['prep_hours']) ? 'checked' : '' }}>
                                    <label class="form-check-label" for="activity_logging_config_prep_hours">
                                        Prep Hours
                                    </label>
                                </div>

                                <div class="form-check mb-2">
                                    <input class="form-check-input"
                                           type="checkbox"
                                           name="activity_logging_config[followup_hours]"
                                           id="activity_logging_config_followup_hours"
                                           value="1"
                                           {{ !empty($activityLoggingConfig['followup_hours']) ? 'checked' : '' }}>
                                    <label class="form-check-label" for="activity_logging_config_followup_hours">
                                        Follow-up Hours
                                    </label>
                                </div>

                                <div class="form-check mb-2">
                                    <input class="form-check-input"
                                           type="checkbox"
                                           name="activity_logging_config[participant_count]"
                                           id="activity_logging_config_participant_count"
                                           value="1"
                                           {{ !empty($activityLoggingConfig['participant_count']) ? 'checked' : '' }}>
                                    <label class="form-check-label" for="activity_logging_config_participant_count">
                                        Participants
                                    </label>
                                </div>

                                <div class="form-check mb-2">
                                    <input class="form-check-input"
                                           type="checkbox"
                                           name="activity_logging_config[external_attendees]"
                                           id="activity_logging_config_external_attendees"
                                           value="1"
                                           {{ !empty($activityLoggingConfig['external_attendees']) ? 'checked' : '' }}>
                                    <label class="form-check-label" for="activity_logging_config_external_attendees">
                                        External Attendees
                                    </label>
                                </div>
                            </div>

                            <div class="col-md-6">
                                <div class="form-check mb-2">
                                    <input class="form-check-input"
                                           type="checkbox"
                                           name="activity_logging_config[summary]"
                                           id="activity_logging_config_summary"
                                           value="1"
                                           {{ !empty($activityLoggingConfig['summary']) ? 'checked' : '' }}>
                                    <label class="form-check-label" for="activity_logging_config_summary">
                                        Summary
                                    </label>
                                </div>

                                <div class="form-check mb-2">
                                    <input class="form-check-input"
                                           type="checkbox"
                                           name="activity_logging_config[follow_up]"
                                           id="activity_logging_config_follow_up"
                                           value="1"
                                           {{ !empty($activityLoggingConfig['follow_up']) ? 'checked' : '' }}>
                                    <label class="form-check-label" for="activity_logging_config_follow_up">
                                        Follow-Up
                                    </label>
                                </div>

                                <div class="form-check mb-2">
                                    <input class="form-check-input"
                                           type="checkbox"
                                           name="activity_logging_config[strengths]"
                                           id="activity_logging_config_strengths"
                                           value="1"
                                           {{ !empty($activityLoggingConfig['strengths']) ? 'checked' : '' }}>
                                    <label class="form-check-label" for="activity_logging_config_strengths">
                                        Strengths
                                    </label>
                                </div>

                                <div class="form-check mb-2">
                                    <input class="form-check-input"
                                           type="checkbox"
                                           name="activity_logging_config[recommendations]"
                                           id="activity_logging_config_recommendations"
                                           value="1"
                                           {{ !empty($activityLoggingConfig['recommendations']) ? 'checked' : '' }}>
                                    <label class="form-check-label" for="activity_logging_config_recommendations">
                                        Recommendations
                                    </label>
                                </div>
                            </div>
                        </div>

                        <small class="text-muted">
                            Select which fields should appear when logging activities for this agreement.
                        </small>
                    </div>

                    <div class="mb-4">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <div>
                                <h5 class="mb-1">Agreement-Specific Logging Fields</h5>
                                <p class="text-muted small mb-0">These fields appear in a dedicated agreement section while logging activity.</p>
                            </div>
                            <a href="{{ route('agreement-logging-fields.index') }}" class="btn btn-sm btn-outline-secondary">Manage Agreement Fields</a>
                        </div>

                        @if($agreementLoggingFields->isEmpty())
                            <div class="alert alert-light border mb-0">No agreement logging fields have been defined yet.</div>
                        @else
                            <div class="border rounded">
                                @foreach($agreementLoggingFields as $field)
                                    <label class="d-flex align-items-start gap-3 px-3 py-2 border-bottom {{ $loop->last ? 'border-bottom-0' : '' }}">
                                        <input class="form-check-input mt-1"
                                               type="checkbox"
                                               name="agreement_logging_field_ids[]"
                                               value="{{ $field->id }}"
                                               {{ in_array($field->id, $selectedAgreementLoggingFieldIds) ? 'checked' : '' }}>
                                        <div class="flex-grow-1">
                                            <div class="fw-semibold">{{ $field->name }}</div>
                                            <div class="small text-muted">{{ ucfirst($field->field_type) }}{{ $field->help_text ? ' · ' . $field->help_text : '' }}</div>
                                        </div>
                                        <div class="form-check m-0">
                                            <input class="form-check-input"
                                                   type="checkbox"
                                                   name="required_agreement_logging_field_ids[]"
                                                   value="{{ $field->id }}"
                                                   {{ in_array($field->id, $requiredAgreementLoggingFieldIds) ? 'checked' : '' }}>
                                            <label class="form-check-label small">Required</label>
                                        </div>
                                    </label>
                                @endforeach
                            </div>
                        @endif
                    </div>

                    <hr class="my-4">

                    <div class="mb-3">
                        <label class="form-label">Assign Users</label>

                        <x-user-picker
                            picker-id="agreement-edit-users"
                            name="user_ids[]"
                            :users="$users"
                            :selected-ids="old('user_ids', $agreement->users->pluck('id')->toArray())"
                            search-placeholder="Search to assign users..."
                            :show-role="true"
                        />

                        <small class="text-muted">Use the checkboxes above and click Update Agreement to save assigned users.</small>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Assign Teams</label>

                        <x-team-picker
                            picker-id="agreement-edit-teams"
                            name="team_ids[]"
                            :teams="$teams"
                            :selected-ids="old('team_ids', $agreement->teams->pluck('id')->toArray())"
                            search-placeholder="Search to assign teams..."
                        />

                        <small class="text-muted">
                            All users in assigned teams will have access to this agreement.
                        </small>
                    </div>

                </form>
            </div>
        </div>

        <div class="card mb-4">
            <div class="card-body">
                <label class="form-label">Quick Add User</label>
                <form hx-post="{{ route('agreements.assign-user', $agreement) }}"
                      hx-target="#user-list"
                      hx-swap="innerHTML"
                      class="mb-3">
                    @csrf
                    <div class="input-group">
                        <select class="form-select" name="user_id" required>
                            <option value="">Select a user to add...</option>
                            @foreach($users->whereNotIn('id', $agreement->users->pluck('id')) as $user)
                                <option value="{{ $user->id }}">
                                    {{ $user->name }} ({{ ucfirst($user->role) }})
                                </option>
                            @endforeach
                        </select>
                        <button type="submit" class="btn btn-outline-primary">
                            Add User
                        </button>
                    </div>
                </form>

                <div id="user-list" class="list-group">
                    @include('agreements.partials.user-list', ['agreement' => $agreement])
                </div>
            </div>
        </div>

        <div class="card">
            <div class="card-body">
                <h5 class="mb-3">Deliverables</h5>

                <div class="card mb-3">
                    <div class="card-body">
                        <h6 class="card-title">Add Deliverable</h6>

                        <form hx-post="{{ route('agreements.add-deliverable', $agreement) }}"
                              hx-target="#deliverable-list"
                              hx-swap="innerHTML">
                            @csrf

                            <div class="row">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="deliverable_contact_family_id" class="form-label">Contact Family</label>
                                        <select class="form-select"
                                                id="deliverable_contact_family_id"
                                                name="contact_family_id"
                                                hx-get="{{ route('activity-types.by-family') }}"
                                                hx-target="#deliverable_activity_type_id"
                                                hx-swap="innerHTML"
                                                hx-include="this">
                                            <option value="">Select contact family...</option>
                                            @foreach($contactFamilies as $family)
                                                <option value="{{ $family->id }}">{{ $family->name }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                </div>

                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="deliverable_activity_type_id" class="form-label">Activity Type</label>
                                        <select class="form-select"
                                                id="deliverable_activity_type_id"
                                                name="activity_type_id">
                                            <option value="">Select contact family first...</option>
                                        </select>
                                    </div>
                                </div>
                            </div>

                            <div class="row">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="deliverable_required_hours" class="form-label">Required Hours</label>
                                        <input type="number"
                                               class="form-control"
                                               id="deliverable_required_hours"
                                               name="required_hours"
                                               min="0"
                                               step="0.1">
                                    </div>
                                </div>

                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="deliverable_required_activities" class="form-label">Required Activities</label>
                                        <input type="number"
                                               class="form-control"
                                               id="deliverable_required_activities"
                                               name="required_activities"
                                               min="0"
                                               step="1">
                                    </div>
                                </div>
                            </div>

                            <div class="mb-3">
                                <label for="deliverable_notes" class="form-label">Notes</label>
                                <textarea class="form-control"
                                          id="deliverable_notes"
                                          name="notes"
                                          rows="2"></textarea>
                            </div>

                            <button type="submit" class="btn btn-sm btn-outline-primary">
                                Add Deliverable
                            </button>
                        </form>
                    </div>
                </div>

                <div class="table-responsive">
                    <table class="table table-sm">
                        <thead>
                            <tr>
                                <th>Activity Type</th>
                                <th>Contact Family</th>
                                <th class="text-center">Hours</th>
                                <th class="text-center">Activities</th>
                                <th>Notes</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody id="deliverable-list">
                            @include('agreements.partials.deliverable-list', ['agreement' => $agreement])
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
<x-save-bar form-id="agreements-edit-form" cancel-url="{{ route('agreements.index') }}" save-label="Save Agreement" :last-saved-at="$agreement->updated_at" />
@endsection