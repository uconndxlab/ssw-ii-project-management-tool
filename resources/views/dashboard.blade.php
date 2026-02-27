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
            <a href="{{ route('engagements.create') }}" class="btn btn-success">Log Engagement</a>
        </div>
    </div>
</div>

<!-- YTD Summary Cards -->
<div class="row mb-4">
    <div class="col-md-4">
        <div class="card">
            <div class="card-body text-center">
                <h2 class="mb-2">{{ $ytdTotals['engagements'] }}</h2>
                <p class="text-muted mb-0">Total Engagements</p>
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

<!-- Recent Activity -->
<div class="row">
    <div class="col-12">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0">Recent Activity</h5>
                <a href="{{ route('engagements.index') }}" class="btn btn-sm btn-outline-secondary">View All</a>
            </div>
            <div class="card-body">
                @if($recentEngagements->isNotEmpty())
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Project</th>
                                <th>Contact Family</th>
                                <th>Activity Type</th>
                                <th>Hours</th>
                                <th>Logged By</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($recentEngagements as $engagement)
                            <tr style="cursor: pointer;" onclick="window.location='{{ route('engagements.show', $engagement) }}'">
                                <td>{{ $engagement->engagement_date->format('M d, Y') }}</td>
                                <td>{{ $engagement->project->name }}</td>
                                <td><span class="badge bg-primary">{{ $engagement->activityType->contactFamily->name }}</span></td>
                                <td>{{ $engagement->activityType->name }}</td>
                                <td>{{ number_format($engagement->total_hours, 2) }}</td>
                                <td>{{ $engagement->user->name }}</td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                @else
                <div class="text-center py-4 text-muted">
                    <p class="mb-0">No engagements logged yet.</p>
                </div>
                @endif
            </div>
        </div>
    </div>
</div>
@endsection
