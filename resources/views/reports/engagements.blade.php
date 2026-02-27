@extends('layouts.app')

@section('title', 'Engagement Report')

@section('content')
<div class="row mb-4">
    <div class="col-12">
        <h1>Engagement Report</h1>
    </div>
</div>

<div class="row mb-4">
    <div class="col-12">
        <div class="card">
            <div class="card-body">
                <form method="GET" action="{{ route('reports.engagements') }}" 
                      hx-get="{{ route('reports.engagements') }}" 
                      hx-target="#report-results" 
                      hx-swap="innerHTML">
                    
                    <div class="row g-3 align-items-end">
                        <div class="col-md-2">
                            <label for="start_date" class="form-label">Start Date</label>
                            <input type="date" 
                                   class="form-control" 
                                   id="start_date" 
                                   name="start_date" 
                                   value="{{ $startDate }}"
                                   required>
                        </div>

                        <div class="col-md-2">
                            <label for="end_date" class="form-label">End Date</label>
                            <input type="date" 
                                   class="form-control" 
                                   id="end_date" 
                                   name="end_date" 
                                   value="{{ $endDate }}"
                                   required>
                        </div>

                        <div class="col-md-3">
                            <label for="project_id" class="form-label">Project</label>
                            <select class="form-select" id="project_id" name="project_id">
                                <option value="">All Projects</option>
                                @foreach($visibleProjects as $project)
                                    <option value="{{ $project->id }}" {{ $projectId == $project->id ? 'selected' : '' }}>
                                        {{ $project->name }} ({{ $project->organization->name }})
                                    </option>
                                @endforeach
                            </select>
                        </div>

                        <div class="col-md-3">
                            <label for="program_id" class="form-label">Program</label>
                            <select class="form-select" id="program_id" name="program_id">
                                <option value="">All Programs</option>
                                @foreach($programs as $program)
                                    <option value="{{ $program->id }}" {{ $programId == $program->id ? 'selected' : '' }}>
                                        {{ $program->name }}
                                    </option>
                                @endforeach
                            </select>
                        </div>

                        <div class="col-md-2">
                            <button type="submit" class="btn btn-primary w-100">Generate Report</button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<div id="report-results">
    @include('reports._engagements_results')
</div>
@endsection
