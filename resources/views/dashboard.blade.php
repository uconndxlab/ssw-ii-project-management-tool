@extends('layouts.app')

@section('title', 'Dashboard')

@section('content')
<div class="row mb-4">
    <div class="col-12">
        <div class="d-flex justify-content-between align-items-center">
            <div>
                <h1>Dashboard</h1>
                <p class="text-muted mb-0">Overview of {{ now()->year }} Activity</p>
            </div>
            <a href="{{ route('activities.create') }}" class="btn btn-success">Log Activity</a>
        </div>
    </div>
</div>

<!-- YTD Summary Cards -->
<div class="row mb-4">
    <div class="col-md-4">
        <div class="card">
            <div class="card-body text-center">
                <h2 class="mb-2">{{ $ytdTotals['activities'] }}</h2>
                <p class="text-muted mb-0">Total Activities</p>
                <small class="text-muted">Year-to-Date</small>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card">
            <div class="card-body text-center">
                <h2 class="mb-2">{{ number_format($ytdTotals['hours'], 1) }}</h2>
                <p class="text-muted mb-0">Total Hours</p>
                <small class="text-muted">Year-to-Date</small>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card">
            <div class="card-body text-center">
                <h2 class="mb-2">{{ number_format($ytdTotals['participants']) }}</h2>
                <p class="text-muted mb-0">Total Participants</p>
                <small class="text-muted">Year-to-Date</small>
            </div>
        </div>
    </div>
</div>

<div class="row">
    <!-- Agreements -->
    <div class="col-md-6 mb-4">
        <div class="card h-100">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0">My Agreements</h5>
                <a href="{{ route('agreements.index') }}" class="btn btn-sm btn-outline-secondary">View All</a>
            </div>
            <div class="card-body">
                @if($agreements->isNotEmpty())
                    <div class="list-group list-group-flush">
                        @foreach($agreements as $agreement)
                            <a href="{{ route('agreements.show', $agreement) }}" class="list-group-item list-group-item-action">
                                <div class="d-flex justify-content-between align-items-start">

                                    <div>
                                        <h6 class="mb-1">{{ $agreement->name }}</h6>
                                        <p class="mb-1 small text-muted">
                                            @forelse($agreement->organizations as $org)
                                                <span class="badge bg-secondary me-1">{{ $org->name }}</span>
                                            @empty
                                                <span class="text-muted">No organizations</span>
                                            @endforelse
                                        </p>
                                        <div class="small text-muted">
                                            @forelse($agreement->states as $state)
                                                <span class="badge bg-info me-1">{{ $state->name }}</span>
                                            @empty
                                                <span class="text-muted">No states</span>
                                            @endforelse
                                        </div>
                                    </div>

                                    <div class="text-end small text-muted">
                                        <div>Activities: {{ $agreement->activities_count }}</div>
                                        <div>
                                            Last Activity:
                                            {{ $agreement->activities_max_engagement_date
                                                ? \Carbon\Carbon::parse($agreement->activities_max_engagement_date)->format('M d, Y')
                                                : '—' }}
                                        </div>
                                    </div>

                                </div>
                            </a>
                        @endforeach
                    </div>
                @else
                    <p class="text-muted mb-0">No agreements found.</p>
                @endif
            </div>
        </div>
    </div>

    <!-- Recent Activity -->
    <div class="col-md-6 mb-4">
        <div class="card h-100">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0">Recent Activity</h5>
                <a href="{{ route('activities.index') }}" class="btn btn-sm btn-outline-secondary">View All</a>
            </div>
            <div class="card-body">
                @if($recentActivities->isNotEmpty())
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead>
                                <tr>
                                    <th>Date</th>
                                    <th>Agreement</th>
                                    <th>Contact Family</th>
                                    <th>Activity Type</th>
                                    <th>Hours</th>
                                    <th>Logged By</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($recentActivities as $activity)
                                    <tr>
                                        <td>
                                            <a href="{{ route('activities.show', $activity) }}" class="text-decoration-none text-dark d-block">
                                                {{ $activity->engagement_date->format('M d, Y') }}
                                            </a>
                                        </td>
                                        <td>
                                            @forelse($activity->agreements as $agreement)
                                                <span class="badge bg-secondary me-1 mb-1">{{ $agreement->name }}</span>
                                            @empty
                                                <span class="text-muted small">None</span>
                                            @endforelse
                                        </td>
                                        <td><span class="badge bg-primary">{{ $activity->activityType->contactFamily->name }}</span></td>
                                        <td>{{ $activity->activityType->name }}</td>
                                        <td>{{ number_format($activity->total_hours, 2) }}</td>
                                        <td>{{ $activity->user->name }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @else
                    <div class="text-center py-4 text-muted">
                        <p class="mb-0">No activities logged yet.</p>
                    </div>
                @endif
            </div>
        </div>
    </div>
</div>
@endsection