@extends('layouts.app')

@section('title', 'Log Engagement')

@section('content')
<div class="row mb-4">
    <div class="col-12">
        <h1>Log Engagement</h1>
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

                <form method="POST" action="{{ route('engagements.store') }}">
                    @csrf

                    <div class="mb-3">
                        <label for="project_id" class="form-label">Project</label>
                        <select class="form-select @error('project_id') is-invalid @enderror" 
                                id="project_id" 
                                name="project_id" 
                                required>
                            <option value="">Select project...</option>
                            @foreach($projects as $project)
                                <option value="{{ $project->id }}" {{ old('project_id') == $project->id ? 'selected' : '' }}>
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
                                <label for="engagement_date" class="form-label">Date</label>
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
                                <label for="engagement_type" class="form-label">Engagement Type</label>
                                <select class="form-select @error('engagement_type') is-invalid @enderror" 
                                        id="engagement_type" 
                                        name="engagement_type" 
                                        required>
                                    <option value="">Select type...</option>
                                    <option value="technical_assistance" {{ old('engagement_type') === 'technical_assistance' ? 'selected' : '' }}>
                                        Technical Assistance
                                    </option>
                                    <option value="coaching" {{ old('engagement_type') === 'coaching' ? 'selected' : '' }}>
                                        Coaching
                                    </option>
                                    <option value="training" {{ old('engagement_type') === 'training' ? 'selected' : '' }}>
                                        Training
                                    </option>
                                </select>
                                @error('engagement_type')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label for="hours" class="form-label">Hours</label>
                        <input type="number" 
                               class="form-control @error('hours') is-invalid @enderror" 
                               id="hours" 
                               name="hours" 
                               step="0.25"
                               min="0.25"
                               max="999.99"
                               value="{{ old('hours') }}" 
                               required>
                        @error('hours')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                        <small class="text-muted">Use 0.25 increments for quarter hours</small>
                    </div>

                    <div class="mb-3">
                        <label for="notes" class="form-label">Notes (Optional)</label>
                        <textarea class="form-control @error('notes') is-invalid @enderror" 
                                  id="notes" 
                                  name="notes" 
                                  rows="4">{{ old('notes') }}</textarea>
                        @error('notes')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="mb-3">
                        <label for="program_ids" class="form-label">Programs (Optional)</label>
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

                    <div class="d-flex gap-2">
                        <button type="submit" class="btn btn-primary">Log Engagement</button>
                        <a href="{{ route('engagements.index') }}" class="btn btn-secondary">Cancel</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection
