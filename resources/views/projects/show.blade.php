@extends('layouts.app')

@section('title', $project->name)

@section('content')
<div class="row mb-4">
    <div class="col-12">
        <div class="d-flex justify-content-between align-items-center">
            <div>
                <h1>{{ $project->name }}</h1>
                <p class="text-muted mb-0">{{ $project->organization->name }} • {{ $project->state->name }}</p>
            </div>
            <div>
                <a href="{{ route('engagements.create') }}?project_id={{ $project->id }}" class="btn btn-success">Log Engagement</a>
                @if(auth()->user()->isAdmin())
                <a href="{{ route('projects.edit', $project) }}" class="btn btn-outline-primary">Edit Project</a>
                @endif
            </div>
        </div>
    </div>
</div>

<!-- Overview Section -->
<div class="row mb-4">
    <div class="col-md-8">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">Project Overview</h5>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <h6 class="text-muted small mb-1">State</h6>
                        <p class="mb-0">{{ $project->state->name }}</p>
                    </div>
                    <div class="col-md-6 mb-3">
                        <h6 class="text-muted small mb-1">Organization</h6>
                        <p class="mb-0">
                            <a href="{{ route('organizations.show', $project->organization) }}">{{ $project->organization->name }}</a>
                        </p>
                    </div>
                    <div class="col-md-6 mb-3">
                        <h6 class="text-muted small mb-1">Start Date</h6>
                        <p class="mb-0">{{ $project->start_date?->format('M d, Y') ?? 'Not set' }}</p>
                    </div>
                    <div class="col-md-6 mb-3">
                        <h6 class="text-muted small mb-1">End Date</h6>
                        <p class="mb-0">{{ $project->end_date?->format('M d, Y') ?? 'Not set' }}</p>
                    </div>
                </div>
                
                <div class="mt-3">
                    <h6 class="text-muted small mb-2">Assigned Team Members ({{ $project->users->count() }})</h6>
                    <div class="d-flex flex-wrap gap-2">
                        @foreach($project->users as $user)
                            <span class="badge bg-secondary">{{ $user->name }} ({{ ucfirst($user->role) }})</span>
                        @endforeach
                        @if($project->users->isEmpty())
                            <span class="text-muted">No team members assigned</span>
                        @endif
                    </div>
                </div>
                
                @if($programs->isNotEmpty())
                <div class="mt-3">
                    <h6 class="text-muted small mb-2">Programs Represented</h6>
                    <div class="d-flex flex-wrap gap-2">
                        @foreach($programs as $program)
                            <span class="badge bg-info">{{ $program->name }}</span>
                        @endforeach
                    </div>
                </div>
                @endif
            </div>
        </div>
    </div>
    
    <!-- Lifetime Summary -->
    <div class="col-md-4">
        <div class="card mb-3">
            <div class="card-body">
                <h6 class="text-muted small mb-3">Lifetime Totals</h6>
                <div class="mb-3">
                    <h3 class="mb-0">{{ $lifetimeTotals['engagements'] }}</h3>
                    <small class="text-muted">Total Engagements</small>
                </div>
                <div class="mb-3">
                    <h3 class="mb-0">{{ number_format($lifetimeTotals['hours'], 1) }}</h3>
                    <small class="text-muted">Total Hours</small>
                </div>
                <div>
                    <h3 class="mb-0">{{ number_format($lifetimeTotals['participants']) }}</h3>
                    <small class="text-muted">Total Participants</small>
                </div>
            </div>
        </div>
        
        <!-- YTD Summary -->
        <div class="card">
            <div class="card-body">
                <h6 class="text-muted small mb-3">Year-to-Date ({{ now()->year }})</h6>
                <div class="mb-3">
                    <h3 class="mb-0">{{ $ytdTotals['engagements'] }}</h3>
                    <small class="text-muted">Engagements</small>
                </div>
                <div class="mb-3">
                    <h3 class="mb-0">{{ number_format($ytdTotals['hours'], 1) }}</h3>
                    <small class="text-muted">Hours</small>
                </div>
                <div>
                    <h3 class="mb-0">{{ number_format($ytdTotals['participants']) }}</h3>
                    <small class="text-muted">Participants</small>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Recent Activity Section -->
<div class="row">
    <div class="col-12">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0">Recent Activity</h5>
                <a href="{{ route('engagements.index') }}?project_id={{ $project->id }}" class="btn btn-sm btn-outline-secondary">
                    View All Engagements
                </a>
            </div>
            <div class="card-body">
                @if($recentEngagements->isNotEmpty())
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Contact Family</th>
                                <th>Activity Type</th>
                                <th>Event Hours</th>
                                <th>Total Hours</th>
                                <th>Participants</th>
                                <th>Logged By</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($recentEngagements as $engagement)
                            <tr style="cursor: pointer;" onclick="window.location='{{ route('engagements.show', $engagement) }}'">
                                <td>{{ $engagement->engagement_date->format('M d, Y') }}</td>
                                <td><span class="badge bg-primary">{{ $engagement->activityType->contactFamily->name }}</span></td>
                                <td>{{ $engagement->activityType->name }}</td>
                                <td>{{ number_format($engagement->event_hours, 2) }}</td>
                                <td>{{ number_format($engagement->total_hours, 2) }}</td>
                                <td>
                                    @if($engagement->participant_count)
                                        {{ $engagement->participant_count }}
                                    @else
                                        <span class="text-muted">—</span>
                                    @endif
                                </td>
                                <td>{{ $engagement->user->name }}</td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                @else
                <div class="text-center py-4 text-muted">
                    <p class="mb-3">No engagements logged for this project yet.</p>
                    <a href="{{ route('engagements.create') }}" class="btn btn-primary">Log First Engagement</a>
                </div>
                @endif
            </div>
        </div>
    </div>
</div>

<div class="row mt-3">
    <div class="col-12">
        <a href="{{ route('projects.index') }}" class="btn btn-outline-secondary">Back to Projects</a>
    </div>
</div>
@endsection
