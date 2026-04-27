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
@endphp

<div class="container-fluid py-4">
    <div class="row g-4 mb-2">
        <div class="col-12">
            <h1 class="h3 mb-1">Edit Agreement</h1>
            <p class="text-muted mb-0">Update contract metadata, staffing, reporting needs, and files.</p>
        </div>
    </div>

    @if ($errors->any())
        <div class="alert alert-danger py-2">
            <strong>Please fix the highlighted fields.</strong>
        </div>
    @endif

    <form method="POST" action="{{ route('agreements.update', $agreement) }}" id="agreements-edit-form" enctype="multipart/form-data">
        @csrf
        @method('PUT')

        <div class="row g-4">
            <div class="col-lg-8">
                <div class="d-grid gap-4">
                    <!-- Basic Information -->
                    <x-section-card title="1) Basic Information" subtitle="Agreement name.">
                        <div class="mb-3">
                            <label for="name" class="form-label fw-semibold">Agreement Name <span class="text-danger">*</span></label>
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
                    </x-section-card>

                    <!-- Dates -->
                    <x-section-card title="2) Dates" subtitle="Contract start, end, and extension dates.">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label for="start_date" class="form-label fw-semibold">Start Date <span class="text-danger">*</span></label>
                                <input type="date"
                                       class="form-control @error('start_date') is-invalid @enderror"
                                       id="start_date"
                                       name="start_date"
                                       value="{{ old('start_date', $agreement->start_date?->format('Y-m-d')) }}"
                                       required>
                                @error('start_date')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>

                            <div class="col-md-6">
                                <label for="end_date" class="form-label fw-semibold">End Date <span class="text-danger">*</span></label>
                                <input type="date"
                                       class="form-control @error('end_date') is-invalid @enderror"
                                       id="end_date"
                                       name="end_date"
                                       value="{{ old('end_date', $agreement->end_date?->format('Y-m-d')) }}"
                                       required>
                                @error('end_date')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>

                            <div class="col-md-6">
                                <label for="extension_start_date" class="form-label fw-semibold">Extension Start Date</label>
                                <input type="date"
                                       class="form-control @error('extension_start_date') is-invalid @enderror"
                                       id="extension_start_date"
                                       name="extension_start_date"
                                       value="{{ old('extension_start_date', $agreement->extension_start_date?->format('Y-m-d') ?? '') }}">
                                <small class="text-muted">Optional: If the agreement was extended</small>
                                @error('extension_start_date')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>

                            <div class="col-md-6">
                                <label for="extension_end_date" class="form-label fw-semibold">Extension End Date</label>
                                <input type="date"
                                       class="form-control @error('extension_end_date') is-invalid @enderror"
                                       id="extension_end_date"
                                       name="extension_end_date"
                                       value="{{ old('extension_end_date', $agreement->extension_end_date?->format('Y-m-d') ?? '') }}">
                                <small class="text-muted">Optional: Final extended end date</small>
                                @error('extension_end_date')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                        </div>
                    </x-section-card>

                    <!-- Relationships -->
                    <x-section-card title="3) Relationships" subtitle="Organizations and states covered by this agreement.">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label fw-semibold">Organizations</label>
                                <x-organization-picker
                                    picker-id="agreement-organizations"
                                    name="organization_ids[]"
                                    :organizations="$organizations"
                                    :selected-ids="old('organization_ids', $agreement->organizations->pluck('id')->toArray())"
                                />
                                <small class="text-muted">Select one or more organizations</small>
                                @error('organization_ids')
                                    <div class="text-danger small mt-1">{{ $message }}</div>
                                @enderror
                            </div>

                            <div class="col-md-6">
                                <label class="form-label fw-semibold">States</label>
                                <x-state-picker
                                    picker-id="agreement-states"
                                    name="state_ids[]"
                                    :states="$states"
                                    :selected-ids="old('state_ids', $agreement->states->pluck('id')->toArray())"
                                />
                                <small class="text-muted">Select one or more states</small>
                                @error('state_ids')
                                    <div class="text-danger small mt-1">{{ $message }}</div>
                                @enderror
                            </div>
                        </div>
                    </x-section-card>

                    <!-- Staffing -->
                    <x-section-card title="4) Staffing" subtitle="Assign individual users and teams to this agreement.">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label fw-semibold">Assign Users</label>
                                <x-user-picker
                                    picker-id="agreement-edit-users"
                                    name="user_ids[]"
                                    :users="$users"
                                    :selected-ids="old('user_ids', $agreement->users->pluck('id')->toArray())"
                                    search-placeholder="Search to assign users..."
                                    :show-role="true"
                                />
                                <small class="text-muted">Individual users assigned to this agreement</small>
                            </div>

                            <div class="col-md-6">
                                <label class="form-label fw-semibold">Assign Teams</label>
                                <x-team-picker
                                    picker-id="agreement-edit-teams"
                                    name="team_ids[]"
                                    :teams="$teams"
                                    :selected-ids="old('team_ids', $agreement->teams->pluck('id')->toArray())"
                                    search-placeholder="Search to assign teams..."
                                />
                                <small class="text-muted">All users in assigned teams will have access</small>
                            </div>
                        </div>
                    </x-section-card>

                    <!-- Reporting / Logging Fields -->
                    <x-section-card title="5) Reporting / Logging Fields" subtitle="✅ Check the fields staff should log when tracking activities.">
                        <p class="text-muted small mb-3">Select which fields should appear when logging activities for this agreement.</p>
                        
                        <div class="row g-3">
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
                                        Follow-Up Hours
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
                                        Participant Count
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
                                        Summary / Notes
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
                                        Follow-Up Notes
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

                        <!-- Time Tracking Mode -->
                        <div class="mt-4">
                            <label class="form-label fw-semibold">Time Tracking Mode</label>
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <div class="form-check">
                                        <input class="form-check-input" 
                                               type="radio" 
                                               name="time_tracking_mode" 
                                               id="time_tracking_engagement" 
                                               value="engagement"
                                               {{ old('time_tracking_mode', $agreement->time_tracking_mode ?? 'engagement') === 'engagement' ? 'checked' : '' }}>
                                        <label class="form-check-label" for="time_tracking_engagement">
                                            Time by Engagement
                                        </label>
                                        <small class="d-block text-muted">Track time at the engagement/activity level</small>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-check">
                                        <input class="form-check-input" 
                                               type="radio" 
                                               name="time_tracking_mode" 
                                               id="time_tracking_participant" 
                                               value="participant"
                                               {{ old('time_tracking_mode', $agreement->time_tracking_mode ?? 'engagement') === 'participant' ? 'checked' : '' }}>
                                        <label class="form-check-label" for="time_tracking_participant">
                                            Time by Participant
                                        </label>
                                        <small class="d-block text-muted">Track time per participant</small>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </x-section-card>

                    <!-- Attachments -->
                    <x-section-card title="6) Attachments" subtitle="📎 Upload PDFs, contracts, or other relevant documents.">
                        @if($agreement->attachments->isNotEmpty())
                            <h6 class="mb-3">Current Files</h6>
                            <div class="list-group mb-3">
                                @foreach($agreement->attachments as $attachment)
                                    <div class="list-group-item d-flex justify-content-between align-items-center">
                                        <div>
                                            <i class="bi bi-file-earmark"></i>
                                            <a href="{{ $attachment->download_url }}" target="_blank">
                                                {{ $attachment->filename }}
                                            </a>
                                            <small class="text-muted ms-2">({{ $attachment->formatted_size }})</small>
                                        </div>
                                        <button type="button" 
                                                class="btn btn-sm btn-outline-danger"
                                                onclick="if(confirm('Delete this file?')) { document.getElementById('delete-attachment-{{ $attachment->id }}').submit(); }">
                                            Delete
                                        </button>
                                    </div>
                                    <form id="delete-attachment-{{ $attachment->id }}" 
                                          action="{{ route('agreements.attachments.destroy', [$agreement, $attachment]) }}" 
                                          method="POST" 
                                          class="d-none">
                                        @csrf
                                        @method('DELETE')
                                    </form>
                                @endforeach
                            </div>
                        @endif

                        <div class="mb-3">
                            <label for="attachments" class="form-label fw-semibold">Upload New Files</label>
                            <input type="file" 
                                   class="form-control @error('attachments.*') is-invalid @enderror" 
                                   id="attachments" 
                                   name="attachments[]" 
                                   multiple
                                   accept=".pdf,.doc,.docx,.xls,.xlsx,.txt">
                            <small class="text-muted">Upload PDFs, documents, or other relevant files. You can select multiple files.</small>
                            @error('attachments.*')
                                <div class="invalid-feedback d-block">{{ $message }}</div>
                            @enderror
                        </div>
                    </x-section-card>

                    <!-- Scope of Work -->
                    <x-section-card title="7) Scope of Work" subtitle="Detailed description and certification candidates.">
                        <div class="mb-3">
                            <label for="abstract" class="form-label fw-semibold">Description</label>
                            <textarea class="form-control @error('abstract') is-invalid @enderror"
                                      id="abstract"
                                      name="abstract"
                                      rows="6"
                                      placeholder="Describe the scope of work, deliverables, or other relevant details...">{{ old('abstract', $agreement->abstract) }}</textarea>
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
                                      placeholder="List certification candidates if applicable...">{{ old('certification_candidates', $agreement->certification_candidates) }}</textarea>
                            <small class="text-muted">Optional: List any certification candidates associated with this agreement.</small>
                            @error('certification_candidates')
                                <div class="invalid-feedback">{{ $message }}</div>
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
    form-id="agreements-edit-form" 
    cancel-url="{{ route('agreements.index') }}" 
    save-label="Save Agreement" 
    :last-saved-at="$agreement->updated_at" />

@endsection