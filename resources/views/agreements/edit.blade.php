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
    
    // Get selected logging field IDs and required field IDs
    $selectedLoggingFieldIds = old('logging_field_ids', $agreement->loggingFields->pluck('id')->toArray());
    $requiredLoggingFieldIds = old('required_logging_field_ids', 
        $agreement->loggingFields->filter(fn($field) => $field->pivot->is_required)->pluck('id')->toArray()
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

                    <!-- Relationships -->
                    <x-section-card title="2) Relationships" subtitle="Organizations and states covered by this agreement.">
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

                    <!-- Programs & Projects -->
                    <x-section-card title="3) Programs & Projects" subtitle="Select a project to auto-select all programs, or manually cherry-pick programs.">
                        @php
                            $selectedProgramIds = old('program_ids', $agreement->programs->pluck('id')->toArray());
                        @endphp

                        <div class="mb-3">
                            <label for="project_id" class="form-label fw-semibold">Project (optional)</label>
                            <select id="project_id" name="project_id" class="form-select @error('project_id') is-invalid @enderror">
                                <option value="">No project selected</option>
                                @foreach($projects as $project)
                                    <option value="{{ $project->id }}" {{ old('project_id', $agreement->project_id) == $project->id ? 'selected' : '' }}>{{ $project->name }}</option>
                                @endforeach
                            </select>
                            <small class="text-muted">Selecting a project will check all of its programs automatically.</small>
                            @error('project_id')
                                <div class="invalid-feedback d-block">{{ $message }}</div>
                            @enderror
                        </div>

                        <label class="form-label fw-semibold">Programs</label>
                        <div class="border rounded p-3 bg-light" id="program-checkboxes">
                            @foreach($programsByProject as $projectId => $programs)
                                @php
                                    $project = $projectId ? $projects->find($projectId) : null;
                                    $projectName = $project ? $project->name : 'Unassigned Programs';
                                @endphp
                                <div class="mb-2">
                                    <div class="small text-muted fw-semibold mb-1">{{ $projectName }}</div>
                                    @foreach($programs->sortBy('name') as $program)
                                        <div class="form-check mb-1">
                                            <input class="form-check-input agreement-program-checkbox"
                                                   type="checkbox"
                                                   name="program_ids[]"
                                                   id="program_{{ $program->id }}"
                                                   value="{{ $program->id }}"
                                                   data-project-id="{{ $program->project_id }}"
                                                   {{ in_array($program->id, $selectedProgramIds) ? 'checked' : '' }}>
                                            <label class="form-check-label" for="program_{{ $program->id }}">{{ $program->name }}</label>
                                        </div>
                                    @endforeach
                                </div>
                            @endforeach
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

                    <!-- Scope of Work -->
                    <x-section-card title="5) Scope of Work" subtitle="Detailed description and certification candidates.">
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

                    <!-- Deliverables -->
                    <x-section-card title="6) Deliverables" subtitle="Define required deliverables and track progress.">
                        <div class="mb-3">
                            <h6 class="mb-3">Add Deliverable</h6>
                            <div id="deliverable-form" class="border rounded p-3 bg-light mb-3">
                                @csrf
                                
                                <div class="row g-2">
                                    <div class="col-md-6">
                                        <label for="deliverable_contact_family_id" class="form-label small fw-semibold">Contact Family</label>
                                        <select class="form-select form-select-sm" 
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
                                    
                                    <div class="col-md-6">
                                        <label for="deliverable_activity_type_id" class="form-label small fw-semibold">Activity Type</label>
                                        <select class="form-select form-select-sm" 
                                                id="deliverable_activity_type_id" 
                                                name="activity_type_id">
                                            <option value="">Select contact family first...</option>
                                        </select>
                                    </div>
                                </div>
                                
                                <div class="row g-2 mt-1">
                                    <div class="col-md-6">
                                        <label for="deliverable_required_hours" class="form-label small fw-semibold">Required Hours</label>
                                        <input type="number" 
                                               class="form-control form-control-sm" 
                                               id="deliverable_required_hours" 
                                               name="required_hours" 
                                               min="0" 
                                               step="0.1"
                                               placeholder="Optional">
                                    </div>
                                    
                                    <div class="col-md-6">
                                        <label for="deliverable_required_activities" class="form-label small fw-semibold">Required Activities</label>
                                        <input type="number" 
                                               class="form-control form-control-sm" 
                                               id="deliverable_required_activities" 
                                               name="required_activities" 
                                               min="0" 
                                               step="1"
                                               placeholder="Optional">
                                    </div>
                                </div>
                                
                                <div class="mt-2">
                                    <label for="deliverable_notes" class="form-label small fw-semibold">Notes</label>
                                    <textarea class="form-control form-control-sm" 
                                              id="deliverable_notes" 
                                              name="notes" 
                                              rows="2"
                                              placeholder="Optional notes about this deliverable..."></textarea>
                                </div>
                                
                                <button type="button"
                                        id="add-deliverable-btn"
                                        class="btn btn-sm btn-primary mt-2">
                                    + Add Deliverable
                                </button>
                            </div>
                        </div>
                        
                        <div class="table-responsive">
                            <table class="table table-sm table-hover mb-0" id="deliverable-table">
                                <thead class="table-light" id="deliverable-thead" @if($agreement->deliverables->isEmpty()) style="display:none" @endif>
                                    <tr>
                                        <th class="small">Activity Type</th>
                                        <th class="small">Contact Family</th>
                                        <th class="small text-center">Hours</th>
                                        <th class="small text-center">Activities</th>
                                        <th class="small">Notes</th>
                                        <th class="small text-end">Action</th>
                                    </tr>
                                </thead>
                                <tbody id="deliverable-list">
                                    @if($agreement->deliverables->isNotEmpty())
                                        @include('agreements.partials.deliverable-list', ['agreement' => $agreement])
                                    @endif
                                </tbody>
                            </table>
                        </div>
                        <div id="deliverable-empty" class="alert alert-info small mb-0 mt-2" @if($agreement->deliverables->isNotEmpty()) style="display:none" @endif>
                            No deliverables defined yet. Add one using the form above.
                        </div>
                    </x-section-card>
                </div>
            </div>

            <div class="col-lg-4">
                <div class="d-grid gap-4">
                    <!-- Dates -->
                    <x-section-card title="7) Dates" subtitle="Contract timeline.">
                        <div class="mb-3">
                            <label for="start_date" class="form-label fw-semibold">Start Date</label>
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

                        <div class="mb-3">
                            <label for="end_date" class="form-label fw-semibold">End Date</label>
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

                        <div class="mb-3">
                            <label for="extension_start_date" class="form-label">Extension Start</label>
                            <input type="date"
                                   class="form-control @error('extension_start_date') is-invalid @enderror"
                                   id="extension_start_date"
                                   name="extension_start_date"
                                   value="{{ old('extension_start_date', $agreement->extension_start_date?->format('Y-m-d') ?? '') }}">
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
                                   value="{{ old('extension_end_date', $agreement->extension_end_date?->format('Y-m-d') ?? '') }}">
                            <small class="text-muted">Optional</small>
                            @error('extension_end_date')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                    </x-section-card>

                    <!-- Reporting / Logging Fields -->
                    <x-section-card title="8) Reporting / Logging" subtitle="Activity logging configuration.">
                        <p class="text-muted small mb-3">✅ Select fields to enable for activity logging:</p>
                        
                        @foreach($loggingFields as $field)
                            <div class="border-bottom pb-2 mb-2">
                                <div class="form-check">
                                    <input class="form-check-input logging-field-checkbox"
                                           type="checkbox"
                                           name="logging_field_ids[]"
                                           id="logging_field_{{ $field->id }}"
                                           value="{{ $field->id }}"
                                           {{ in_array($field->id, $selectedLoggingFieldIds) ? 'checked' : '' }}
                                           onchange="toggleRequiredCheckbox({{ $field->id }})">
                                    <label class="form-check-label fw-semibold" for="logging_field_{{ $field->id }}">
                                        {{ $field->name }}
                                        <span class="badge bg-secondary text-uppercase small">{{ $field->field_type }}</span>
                                    </label>
                                </div>
                                @if($field->help_text)
                                    <small class="text-muted d-block ms-4">{{ $field->help_text }}</small>
                                @endif
                                <div class="form-check ms-4 mt-1" id="required_{{ $field->id }}" style="display: {{ in_array($field->id, $selectedLoggingFieldIds) ? 'block' : 'none' }};">
                                    <input class="form-check-input"
                                           type="checkbox"
                                           name="required_logging_field_ids[]"
                                           id="required_logging_field_{{ $field->id }}"
                                           value="{{ $field->id }}"
                                           {{ in_array($field->id, $requiredLoggingFieldIds) ? 'checked' : '' }}>
                                    <label class="form-check-label small text-muted" for="required_logging_field_{{ $field->id }}">
                                        Make this field required
                                    </label>
                                </div>
                            </div>
                        @endforeach

                        <script>
                        function toggleRequiredCheckbox(fieldId) {
                            const checkbox = document.getElementById('logging_field_' + fieldId);
                            const requiredDiv = document.getElementById('required_' + fieldId);
                            const requiredCheckbox = document.getElementById('required_logging_field_' + fieldId);
                            
                            if (checkbox.checked) {
                                requiredDiv.style.display = 'block';
                            } else {
                                requiredDiv.style.display = 'none';
                                requiredCheckbox.checked = false;
                            }
                        }
                        </script>

                        <hr class="my-3">

                        <label class="form-label fw-semibold">Time Tracking Mode</label>
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
                        <div class="form-check mt-2">
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
                    </x-section-card>

                    <!-- Attachments -->
                    <x-section-card title="9) Attachments" subtitle="📎 Upload documents.">
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

<script>
    document.addEventListener('DOMContentLoaded', function () {
        const addDeliverableBtn = document.getElementById('add-deliverable-btn');
        if (addDeliverableBtn) {
            addDeliverableBtn.addEventListener('click', function () {
                const form = document.getElementById('deliverable-form');
                const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content;

                const body = new URLSearchParams({
                    _token: csrfToken,
                    contact_family_id: form.querySelector('[name="contact_family_id"]')?.value ?? '',
                    activity_type_id: form.querySelector('[name="activity_type_id"]')?.value ?? '',
                    required_hours: form.querySelector('[name="required_hours"]')?.value ?? '',
                    required_activities: form.querySelector('[name="required_activities"]')?.value ?? '',
                    notes: form.querySelector('[name="notes"]')?.value ?? '',
                });

                fetch('{{ route('agreements.add-deliverable', $agreement) }}', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded', 'X-CSRF-TOKEN': csrfToken },
                    body: body.toString(),
                })
                .then(res => res.text())
                .then(html => {
                    document.getElementById('deliverable-list').innerHTML = html;

                    // Show table header, hide empty message
                    const thead = document.getElementById('deliverable-thead');
                    const empty = document.getElementById('deliverable-empty');
                    if (thead) thead.style.display = '';
                    if (empty) empty.style.display = 'none';

                    // Reset fields
                    form.querySelectorAll('input[type="number"], textarea').forEach(el => el.value = '');
                    form.querySelector('[name="contact_family_id"]').value = '';
                    const activitySelect = document.getElementById('deliverable_activity_type_id');
                    if (activitySelect) {
                        activitySelect.innerHTML = '<option value="">Select contact family first...</option>';
                    }
                })
                .catch(err => console.error('Failed to add deliverable:', err));
            });
        }
        const projectSelect = document.getElementById('project_id');
        const programCheckboxes = document.querySelectorAll('.agreement-program-checkbox');

        if (projectSelect) {
            projectSelect.addEventListener('change', function () {
                const selectedProjectId = this.value;
                if (!selectedProjectId) return;

                programCheckboxes.forEach((checkbox) => {
                    if (checkbox.dataset.projectId === selectedProjectId) {
                        checkbox.checked = true;
                    }
                });
            });
        }

        // Auto-select state when an organization is checked
        document.addEventListener('change', function (e) {
            const checkbox = e.target;
            if (!checkbox.matches('input[type="checkbox"][data-state-id]')) return;

            const stateId = checkbox.dataset.stateId;
            if (!stateId) return;

            const stateCheckbox = document.querySelector(
                'input[type="checkbox"][name="state_ids[]"][value="' + stateId + '"]'
            );
            if (stateCheckbox && checkbox.checked) {
                stateCheckbox.checked = true;
                stateCheckbox.dispatchEvent(new Event('change', { bubbles: true }));
            }
        });
    });
</script>

<!-- Sticky Save Bar -->
<x-save-bar 
    form-id="agreements-edit-form" 
    cancel-url="{{ route('agreements.index') }}" 
    save-label="Save Agreement" 
    :last-saved-at="$agreement->updated_at" />

@endsection
