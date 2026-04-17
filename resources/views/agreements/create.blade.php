@extends('layouts.app')

@section('title', 'Create Agreement')

@section('content')
<div class="row mb-4">
    <div class="col-12">
        <h1>Create Agreement</h1>
    </div>
</div>

<div class="row">
    <div class="col-md-8">
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

                <form method="POST" action="{{ route('agreements.store') }}">
                    @csrf

                    <div class="mb-3">
                        <label for="name" class="form-label">Agreement Name</label>
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

                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="organization_id" class="form-label">Organization</label>
                                <select class="form-select @error('organization_id') is-invalid @enderror" 
                                        id="organization_id" 
                                        name="organization_id" 
                                        required>
                                    <option value="">Select organization...</option>
                                    @foreach($organizations as $organization)
                                        <option value="{{ $organization->id }}" {{ old('organization_id') == $organization->id ? 'selected' : '' }}>
                                            {{ $organization->name }}
                                        </option>
                                    @endforeach
                                </select>
                                @error('organization_id')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                        </div>

                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="state_id" class="form-label">State</label>
                                <select class="form-select @error('state_id') is-invalid @enderror" 
                                        id="state_id" 
                                        name="state_id" 
                                        required>
                                    <option value="">Select state...</option>
                                    @foreach($states as $state)
                                        <option value="{{ $state->id }}" {{ old('state_id') == $state->id ? 'selected' : '' }}>
                                            {{ $state->name }}
                                        </option>
                                    @endforeach
                                </select>
                                @error('state_id')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label for="abstract" class="form-label">Abstract</label>
                        <textarea class="form-control @error('abstract') is-invalid @enderror" 
                                  id="abstract" 
                                  name="abstract" 
                                  rows="4">{{ old('abstract') }}</textarea>
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
                                       value="{{ old('start_date') }}">
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
                                       value="{{ old('end_date') }}">
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
                                       value="{{ old('original_end_date') }}">
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
                                       value="{{ old('extended_end_date') }}">
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
                                  rows="3">{{ old('certification_candidates') }}</textarea>
                        @error('certification_candidates')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                        <small class="text-muted">List of certification candidates (placeholder)</small>
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

                    <div class="mb-3">
                        <label class="form-label">Assign Users</label>

                        <x-user-picker
                            picker-id="agreement-create-users"
                            name="user_ids[]"
                            :users="$users"
                            :selected-ids="old('user_ids', [])"
                            search-placeholder="Search to assign users..."
                            :show-role="true"
                        />
                    </div>

                    <div class="d-flex gap-2">
                        <button type="submit" class="btn btn-primary">Create Agreement</button>
                        <a href="{{ route('agreements.index') }}" class="btn btn-secondary">Cancel</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection
