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
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">
                        Engagement Summary: {{ \Carbon\Carbon::parse($startDate)->format('M d, Y') }} - {{ \Carbon\Carbon::parse($endDate)->format('M d, Y') }}
                    </h5>
                </div>
                <div class="card-body">
                    @if(count($projectData) > 0)
                    <div class="table-responsive">
                        <table class="table table-bordered table-hover">
                            <thead class="table-light">
                                <tr>
                                    <th>Project</th>
                                    <th>Organization</th>
                                    <th class="text-end">Event Hours</th>
                                    <th class="text-end">Prep Hours</th>
                                    <th class="text-end">Follow-Up Hours</th>
                                    <th class="text-end"><strong>Total Hours</strong></th>
                                    <th class="text-end">Participants</th>
                                    <th class="text-center">Engagements</th>
                                </tr>
                            </thead>
                            <tbody>
                                @php
                                    $totalEvent = 0;
                                    $totalPrep = 0;
                                    $totalFollowup = 0;
                                    $grandTotal = 0;
                                    $totalParticipants = 0;
                                    $totalEngagements = 0;
                                @endphp

                                @foreach($projectData as $data)
                                @php
                                    $totalEvent += $data['event_hours'];
                                    $totalPrep += $data['prep_hours'];
                                    $totalFollowup += $data['followup_hours'];
                                    $grandTotal += $data['total_hours'];
                                    $totalParticipants += $data['participant_count'];
                                    $totalEngagements += $data['engagement_count'];
                                @endphp
                                <tr>
                                    <td>{{ $data['project']->name }}</td>
                                    <td>{{ $data['project']->organization->name }}</td>
                                    <td class="text-end">{{ number_format($data['event_hours'], 2) }}</td>
                                    <td class="text-end">{{ number_format($data['prep_hours'], 2) }}</td>
                                    <td class="text-end">{{ number_format($data['followup_hours'], 2) }}</td>
                                    <td class="text-end"><strong>{{ number_format($data['total_hours'], 2) }}</strong></td>
                                    <td class="text-end">{{ number_format($data['participant_count']) }}</td>
                                    <td class="text-center">{{ $data['engagement_count'] }}</td>
                                </tr>
                                @endforeach

                                <tr class="table-secondary">
                                    <td colspan="2"><strong>TOTAL</strong></td>
                                    <td class="text-end"><strong>{{ number_format($totalEvent, 2) }}</strong></td>
                                    <td class="text-end"><strong>{{ number_format($totalPrep, 2) }}</strong></td>
                                    <td class="text-end"><strong>{{ number_format($totalFollowup, 2) }}</strong></td>
                                    <td class="text-end"><strong>{{ number_format($grandTotal, 2) }}</strong></td>
                                    <td class="text-end"><strong>{{ number_format($totalParticipants) }}</strong></td>
                                    <td class="text-center"><strong>{{ $totalEngagements }}</strong></td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <div class="mt-3">
                        <p class="text-muted mb-0">
                            <small>
                                @if(!auth()->user()->isAdmin())
                                    Report shows only projects you are assigned to.
                                @else
                                    Report shows all projects.
                                @endif
                            </small>
                        </p>
                    </div>
                    @else
                    <p class="text-muted text-center py-4 mb-0">
                        No engagements found for the selected date range
                        @if($projectId)
                            and project
                        @endif
                    </p>
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
