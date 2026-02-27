@extends('layouts.app')

@section('title', 'Dashboard')

@section('content')
<div class="row mb-4">
    <div class="col-12">
        <div class="d-flex justify-content-between align-items-center">
            <div>
                <h1>My Dashboard</h1>
                <p class="text-muted mb-0">Welcome back, {{ auth()->user()->name }}</p>
            </div>
            <a href="{{ route('engagements.create') }}" class="btn btn-success">Log Engagement</a>
        </div>
    </div>
</div>

<!-- My YTD Hours -->
<div class="row mb-4">
    <div class="col-md-4">
        <div class="card">
            <div class="card-body text-center">
                <h2 class="mb-2">{{ number_format($myYtdHours, 1) }}</h2>
                <p class="text-muted mb-0">My Hours Logged</p>
                <small class="text-muted">Year-to-Date ({{ now()->year }})</small>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card">
            <div class="card-body text-center">
                <h2 class="mb-2">{{ $myProjects->count() }}</h2>
                <p class="text-muted mb-0">My Active Projects</p>
                <small class="text-muted">Assigned to me</small>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card">
            <div class="card-body text-center">
                <h2 class="mb-2">{{ $myEngagements->count() }}</h2>
                <p class="text-muted mb-0">Recent Engagements</p>
                <small class="text-muted">Last 10 entries</small>
            </div>
        </div>
    </div>
</div>

<div class="row">
    <!-- My Projects -->
    <div class="col-md-6 mb-4">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0">My Projects</h5>
                <a href="{{ route('projects.index') }}" class="btn btn-sm btn-outline-secondary">View All</a>
            </div>
            <div class="card-body">
                @if($myProjects->isNotEmpty())
                <div class="list-group list-group-flush">
                    @foreach($myProjects as $project)
                    <a href="{{ route('projects.show', $project) }}" class="list-group-item list-group-item-action">
                        <div class="d-flex w-100 justify-content-between">
                            <h6 class="mb-1">{{ $project->name }}</h6>
                        </div>
                        <p class="mb-1 small text-muted">{{ $project->organization->name }}</p>
                        <small class="text-muted">{{ $project->state->name }}</small>
                    </a>
                    @endforeach
                </div>
                @else
                <p class="text-muted mb-0">You are not assigned to any projects yet.</p>
                @endif
            </div>
        </div>
    </div>
    
    <!-- My Recent Activity -->
    <div class="col-md-6 mb-4">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0">Recent Activity</h5>
                <a href="{{ route('engagements.index') }}" class="btn btn-sm btn-outline-secondary">View All</a>
            </div>
            <div class="card-body">
                @if($myEngagements->isNotEmpty())
                <div class="table-responsive">
                    <table class="table table-sm table-hover mb-0">
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Activity</th>
                                <th>Hours</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($myEngagements as $engagement)
                            <tr style="cursor: pointer;" onclick="window.location='{{ route('engagements.show', $engagement) }}'">
                                <td>{{ $engagement->engagement_date->format('M d') }}</td>
                                <td>
                                    <div class="small"><strong>{{ $engagement->project->name }}</strong></div>
                                    <div class="small text-muted">{{ $engagement->activityType->name }}</div>
                                </td>
                                <td>{{ number_format($engagement->total_hours, 1) }}</td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                @else
                <p class="text-muted mb-0">No recent activity on your projects.</p>
                @endif
            </div>
        </div>
    </div>
</div>
@endsection
