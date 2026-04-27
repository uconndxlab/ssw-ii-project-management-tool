@extends('layouts.app')

@section('title', 'Create Agreement')

@section('content')
<div class="container-fluid py-4">
    <div class="row g-4 mb-2">
        <div class="col-12">
            <h1 class="h3 mb-1">Create Agreement</h1>
            <p class="text-muted mb-0">Configure contract metadata, staffing, reporting needs, and files.</p>
        </div>
    </div>

    @if ($errors->any())
        <div class="alert alert-danger py-2">
            <strong>Please fix the highlighted fields.</strong>
        </div>
    @endif

    <form method="POST" action="{{ route('agreements.store') }}" id="agreements-create-form" enctype="multipart/form-data">
        @csrf

        <div class="row g-4">
            <div class="col-lg-8">
                <div class="d-grid gap-4">
                    <!-- Basic Information -->
                    <x-section-card title="1) Basic Information" subtitle="Start with the agreement name.">
                        <div class="mb-3">
                            <label for="name" class="form-label fw-semibold">Agreement Name <span class="text-danger">*</span></label>
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
                    </x-section-card>

                    <!-- Relationships -->
                    <x-section-card title="2) Relationships" subtitle="Link organizations and states to this agreement.">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label fw-semibold">Organizations</label>
                        <x-organization-picker
                            picker-id="agreement-organizations"
                            name="organization_ids[]"
                            :organizations="$organizations"
                            :selected-ids="old('organization_ids', [])"
                        />
                                <small class="text-muted">Select one or more organizations</small>
                                @error('organization_ids')
                                    <div class="text-danger small mt-1">{{ $message }}</div>
                                @enderror
                            </div>

                            <div class="col-md-6 mb-3">
                                <label class="form-label fw-semibold">States</label>
                        <x-state-picker
                            picker-id="agreement-states"
                            name="state_ids[]"
                            :states="$states"
                            :selected-ids="old('state_ids', [])"
                        />
                                <small class="text-muted">Select one or more states</small>
                                @error('state_ids')
                                    <div class="text-danger small mt-1">{{ $message }}</div>
                                @enderror
                            </div>
                        </div>
                    </x-section-card>

                    <!-- Staffing -->
                    <x-section-card title="3) Staffing" subtitle="Assign individual users and teams who can access this agreement.">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label fw-semibold">Assign Users</label>
                        <x-user-picker
                            picker-id="agreement-create-users"
                            name="user_ids[]"
                            :users="$users"
                            :selected-ids="old('user_ids', [])"
                            search-placeholder="Search to assign users..."
                            :show-role="true"
                        />
                                <small class="text-muted">Individual users assigned to this agreement</small>
                            </div>

                            <div class="col-md-6 mb-3">
                                <label class="form-label fw-semibold">Assign Teams</label>
                        <x-team-picker
                            picker-id="agreement-create-teams"
                            name="team_ids[]"
                            :teams="$teams"
                            :selected-ids="old('team_ids', [])"
                            search-placeholder="Search to assign teams..."
                        />
                                <small class="text-muted">All users in assigned teams will have access</small>
                            </div>
                        </div>
                    </x-section-card>

                    <!-- Scope of Work -->
                    <x-section-card title="4) Scope of Work" subtitle="Describe the agreement's objectives, deliverables, and details.">
                        <div class="mb-3">
                            <label for="abstract" class="form-label fw-semibold">Description</label>
                            <textarea class="form-control @error('abstract') is-invalid @enderror" 
                                      id="abstract" 
                                      name="abstract" 
                                      rows="6"
                                      placeholder="Describe the scope of work, deliverables, or other relevant details...">{{ old('abstract') }}</textarea>
                            <small class="text-muted">Provide details about the agreement's scope, objectives, and deliverables.</small>
                            @error('abstract')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="mb-3">
                            <label for="certification_candidates" class="form-label fw-semibold">Certification Candidates</label>
                            <textarea class="form-control @error('certification_candidates') is-invalid @enderror" 
                                      id="certification_candidates" 
                                      name="certification_candidates" 
                                      rows="3"
                                      placeholder="List certification candidates if applicable...">{{ old('certification_candidates') }}</textarea>
                            <small class="text-muted">Optional: List any certification candidates associated with this agreement.</small>
                            @error('certification_candidates')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                    </x-section-card>
                </div>
            </div>

            <div class="col-lg-4">
                <div class="d-grid gap-4">
                    <!-- Dates -->
                    <x-section-card title="5) Dates" subtitle="Contract timeline.">
                        <div class="mb-3">
                            <label for="start_date" class="form-label fw-semibold">Start Date</label>
                            <input type="date" 
                                   class="form-control @error('start_date') is-invalid @enderror" 
                                   id="start_date" 
                                   name="start_date" 
                                   value="{{ old('start_date') }}">
                            @error('start_date')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="mb-3">
                            <label for="end_date" class="form-label fw-semibold">End Date</label>
                            <input type="date" 
                                   class="form-control @error('end_date') is-invalid @enderror" 
                                   id="end_date" 
                                   name="end_date" 
                                   value="{{ old('end_date') }}">
                            @error('end_date')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="mb-3">
                            <label for="extension_start_date" class="form-label">Extension Start</label>
                            <input type="date" 
                                   class="form-control @error('extension_start_date') is-invalid @enderror" 
                                   id="extension_start_date" 
                                   name="extension_start_date" 
                                   value="{{ old('extension_start_date') }}">
                            <small class="text-muted">Optional</small>
                            @error('extension_start_date')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="mb-3">
                            <label for="extension_end_date" class="form-label">Extension End</label>
                            <input type="date" 
                                   class="form-control @error('extension_end_date') is-invalid @enderror" 
                                   id="extension_end_date" 
                                   name="extension_end_date" 
                                   value="{{ old('extension_end_date') }}">
                            <small class="text-muted">Optional</small>
                            @error('extension_end_date')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                    </x-section-card>

                    <!-- Reporting / Logging Fields -->
                    <x-section-card title="6) Reporting / Logging" subtitle="Activity logging configuration.">
                        <p class="text-muted small mb-3">✅ Check fields to enable:</p>
                        <div class="form-check mb-2">
                            <input class="form-check-input"
                                   type="checkbox"
                                   name="activity_logging_config[event_hours]"
                                   id="activity_logging_config_event_hours"
                                   value="1"
                                   {{ old('activity_logging_config.event_hours') ? 'checked' : '' }}>
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
                                   {{ old('activity_logging_config.prep_hours') ? 'checked' : '' }}>
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
                                   {{ old('activity_logging_config.followup_hours') ? 'checked' : '' }}>
                            <label class="form-check-label" for="activity_logging_config_followup_hours">
                                Follow-Up Hours
                            </label>
                        </div>

                        <div class="form-check mb-2">
                            <input class="form-check-input"
                                   type="checkbox"
                                   name="activity_logging_config[participant_count]"
                                   id="activity_logging_config_participant_count"
                                   value="1"
                                   {{ old('activity_logging_config.participant_count') ? 'checked' : '' }}>
                            <label class="form-check-label" for="activity_logging_config_participant_count">
                                Participant Count
                            </label>
                        </div>

                        <div class="form-check mb-2">
                            <input class="form-check-input"
                                   type="checkbox"
                                   name="activity_logging_config[external_attendees]"
                                   id="activity_logging_config_external_attendees"
                                   value="1"
                                   {{ old('activity_logging_config.external_attendees') ? 'checked' : '' }}>
                            <label class="form-check-label" for="activity_logging_config_external_attendees">
                                External Attendees
                            </label>
                        </div>

                        <div class="form-check mb-2">
                            <input class="form-check-input"
                                   type="checkbox"
                                   name="activity_logging_config[summary]"
                                   id="activity_logging_config_summary"
                                   value="1"
                                   {{ old('activity_logging_config.summary') ? 'checked' : '' }}>
                            <label class="form-check-label" for="activity_logging_config_summary">
                                Summary / Notes
                            </label>
                        </div>

                        <div class="form-check mb-2">
                            <input class="form-check-input"
                                   type="checkbox"
                                   name="activity_logging_config[follow_up]"
                                   id="activity_logging_config_follow_up"
                                   value="1"
                                   {{ old('activity_logging_config.follow_up') ? 'checked' : '' }}>
                            <label class="form-check-label" for="activity_logging_config_follow_up">
                                Follow-Up Notes
                            </label>
                        </div>

                        <div class="form-check mb-2">
                            <input class="form-check-input"
                                   type="checkbox"
                                   name="activity_logging_config[strengths]"
                                   id="activity_logging_config_strengths"
                                   value="1"
                                   {{ old('activity_logging_config.strengths') ? 'checked' : '' }}>
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
                                   {{ old('activity_logging_config.recommendations') ? 'checked' : '' }}>
                            <label class="form-check-label" for="activity_logging_config_recommendations">
                                Recommendations
                            </label>
                        </div>

                        <hr class="my-3">

                        <label class="form-label fw-semibold">Time Tracking Mode</label>
                        <div class="form-check">
                            <input class="form-check-input" 
                                   type="radio" 
                                   name="time_tracking_mode" 
                                   id="time_tracking_engagement" 
                                   value="engagement"
                                   {{ old('time_tracking_mode', 'engagement') === 'engagement' ? 'checked' : '' }}>
                            <label class="form-check-label" for="time_tracking_engagement">
                                Time by Engagement
                            </label>
                            <small class="d-block text-muted">Track time at the engagement/activity level</small>
                        </div>
                        <div class="form-check mt-2">
                            <input class="form-check-input" 
                                   type="radio" 
                                   name="time_tracking_mode" 
                                   id="time_tracking_participant" 
                                   value="participant"
                                   {{ old('time_tracking_mode') === 'participant' ? 'checked' : '' }}>
                            <label class="form-check-label" for="time_tracking_participant">
                                Time by Participant
                            </label>
                            <small class="d-block text-muted">Track time per participant</small>
                        </div>
                    </x-section-card>

                    <!-- Attachments -->
                    <x-section-card title="7) Attachments" subtitle="📎 Upload documents.">
                        <div class="mb-3">
                            <label for="attachments" class="form-label fw-semibold">Upload Files</label>
                            <input type="file" 
                                   class="form-control @error('attachments.*') is-invalid @enderror" 
                                   id="attachments" 
                                   name="attachments[]" 
                                   multiple
                                   accept=".pdf,.doc,.docx,.xls,.xlsx,.txt">
                            <small class="text-muted">PDFs, documents, etc.</small>
                            @error('attachments.*')
                                <div class="invalid-feedback d-block">{{ $message }}</div>
                            @enderror
                        </div>
                    </x-section-card>
                </div>
            </div>
        </div>
    </form>
</div>

<!-- Sticky Save Bar -->
<x-save-bar 
    form-id="agreements-create-form" 
    cancel-url="{{ route('agreements.index') }}" 
    save-label="Create Agreement" />

@endsection
