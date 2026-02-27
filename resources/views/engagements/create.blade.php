@extends('layouts.app')

@section('title', 'Log Engagement')

@section('content')
<div class="row mb-4">
    <div class="col-12">
        <h1>Log Engagement</h1>
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

                <form method="POST" action="{{ route('engagements.store') }}">
                    @csrf

                    <div class="mb-3">
                        <label for="project_id" class="form-label">Project <span class="text-danger">*</span></label>
                        <select class="form-select @error('project_id') is-invalid @enderror" 
                                id="project_id" 
                                name="project_id" 
                                required>
                            <option value="">Select project...</option>
                            @foreach($projects as $project)
                                <option value="{{ $project->id }}" {{ (old('project_id') ?? $preselectedProjectId) == $project->id ? 'selected' : '' }}>
                                    {{ $project->name }} ({{ $project->organization->name }})
                                </option>
                            @endforeach
                        </select>
                        @error('project_id')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="engagement_date" class="form-label">Date <span class="text-danger">*</span></label>
                                <input type="date" 
                                       class="form-control @error('engagement_date') is-invalid @enderror" 
                                       id="engagement_date" 
                                       name="engagement_date" 
                                       value="{{ old('engagement_date', now()->format('Y-m-d')) }}" 
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
                                        <option value="{{ $family->id }}" {{ old('contact_family_id') == $family->id ? 'selected' : '' }}>
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
                            <option value="">Select contact family first...</option>
                        </select>
                        @error('activity_type_id')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="row">
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label for="event_hours" class="form-label">Event Hours <span class="text-danger">*</span></label>
                                <input type="number" 
                                       class="form-control @error('event_hours') is-invalid @enderror" 
                                       id="event_hours" 
                                       name="event_hours" 
                                       step="0.25"
                                       min="0"
                                       max="9999.99"
                                       value="{{ old('event_hours') }}" 
                                       required>
                                @error('event_hours')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                        </div>

                        <div class="col-md-4">
                            <div class="mb-3">
                                <label for="prep_hours" class="form-label">Prep Hours</label>
                                <input type="number" 
                                       class="form-control @error('prep_hours') is-invalid @enderror" 
                                       id="prep_hours" 
                                       name="prep_hours" 
                                       step="0.25"
                                       min="0"
                                       max="9999.99"
                                       value="{{ old('prep_hours', 0) }}">
                                @error('prep_hours')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                        </div>

                        <div class="col-md-4">
                            <div class="mb-3">
                                <label for="followup_hours" class="form-label">Follow-Up Hours</label>
                                <input type="number" 
                                       class="form-control @error('followup_hours') is-invalid @enderror" 
                                       id="followup_hours" 
                                       name="followup_hours" 
                                       step="0.25"
                                       min="0"
                                       max="9999.99"
                                       value="{{ old('followup_hours', 0) }}">
                                @error('followup_hours')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label for="participant_count" class="form-label">Participant Count</label>
                        <input type="number" 
                               class="form-control @error('participant_count') is-invalid @enderror" 
                               id="participant_count" 
                               name="participant_count" 
                               min="0"
                               value="{{ old('participant_count') }}">
                        @error('participant_count')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
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
                                    {{ in_array($program->id, old('program_ids', [])) ? 'selected' : '' }}>
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
                            <small class="text-muted">Select a project first to see team members</small>
                        </div>
                        @error('participant_user_ids')
                            <div class="invalid-feedback d-block">{{ $message }}</div>
                        @enderror
                        <small class="text-muted">Check team members who participated in delivering this engagement</small>
                    </div>

                    <div class="mb-3">
                        <label for="summary" class="form-label">Summary</label>
                        <textarea class="form-control @error('summary') is-invalid @enderror" 
                                  id="summary" 
                                  name="summary" 
                                  rows="3">{{ old('summary') }}</textarea>
                        @error('summary')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="mb-3">
                        <label for="follow_up" class="form-label">Follow-Up</label>
                        <textarea class="form-control @error('follow_up') is-invalid @enderror" 
                                  id="follow_up" 
                                  name="follow_up" 
                                  rows="3">{{ old('follow_up') }}</textarea>
                        @error('follow_up')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="mb-3">
                        <label for="strengths" class="form-label">Strengths</label>
                        <textarea class="form-control @error('strengths') is-invalid @enderror" 
                                  id="strengths" 
                                  name="strengths" 
                                  rows="3">{{ old('strengths') }}</textarea>
                        @error('strengths')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="mb-3">
                        <label for="recommendations" class="form-label">Recommendations</label>
                        <textarea class="form-control @error('recommendations') is-invalid @enderror" 
                                  id="recommendations" 
                                  name="recommendations" 
                                  rows="3">{{ old('recommendations') }}</textarea>
                        @error('recommendations')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="d-flex gap-2">
                        <button type="submit" class="btn btn-primary">Log Engagement</button>
                        <a href="{{ route('engagements.index') }}" class="btn btn-secondary">Cancel</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
// Project-to-participants mapping from server
const projectParticipants = @json($projects->mapWithKeys(fn($p) => [
    $p->id => $p->users->map(fn($u) => ['id' => $u->id, 'name' => $u->name])
]));

// Old participant selections for validation errors
const oldParticipants = @json(old('participant_user_ids', []));

// Update participants when project changes
function updateParticipants() {
    const projectId = document.getElementById('project_id').value;
    const container = document.getElementById('participants-container');
    
    if (!projectId || !projectParticipants[projectId]) {
        container.innerHTML = '<small class="text-muted">Select a project first to see team members</small>';
        return;
    }
    
    const users = projectParticipants[projectId];
    
    if (users.length === 0) {
        container.innerHTML = '<small class="text-muted">No team members assigned to this project</small>';
        return;
    }
    
    container.innerHTML = users.map(user => {
        const isChecked = oldParticipants.includes(user.id.toString()) || oldParticipants.includes(user.id);
        return `
            <div class="form-check">
                <input class="form-check-input" 
                       type="checkbox" 
                       name="participant_user_ids[]" 
                       value="${user.id}" 
                       id="participant_${user.id}"
                       ${isChecked ? 'checked' : ''}>
                <label class="form-check-label" for="participant_${user.id}">
                    ${user.name}
                </label>
            </div>
        `;
    }).join('');
}

document.getElementById('project_id').addEventListener('change', updateParticipants);

// Trigger on page load if project is pre-selected
if (document.getElementById('project_id').value) {
    updateParticipants();
}
</script>
@endsection
