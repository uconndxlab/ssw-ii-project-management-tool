@extends('layouts.app')

@section('title', $organization->name)

@section('content')
<div class="row mb-4">
    <div class="col-12">
        <div class="d-flex justify-content-between align-items-center">
            <div>
                <h1>{{ $organization->name }}</h1>
                <p class="text-muted mb-0">{{ $organization->state->name }}</p>
            </div>
            <div>
                <a href="{{ route('activities.create') }}" class="btn btn-success">Log Activity</a>
                @if(auth()->user()->isAdmin())
                <a href="{{ route('organizations.edit', $organization) }}" class="btn btn-outline-primary">Edit Organization</a>
                @endif
            </div>
        </div>
    </div>
</div>

<!-- Overview Section -->
<div class="row mb-4">
    <div class="col-md-4">
        <div class="card mb-3">
            <div class="card-body">
                <h6 class="text-muted small mb-3">Overview</h6>
                <div class="mb-3">
                    <h3 class="mb-0">{{ $agreements->count() }}</h3>
                    <small class="text-muted">Active Agreements</small>
                </div>
                <div>
                    <h3 class="mb-0">{{ $teamMembers->count() }}</h3>
                    <small class="text-muted">Team Members</small>
                </div>
            </div>
        </div>
        
        <!-- YTD Summary -->
        <div class="card">
            <div class="card-body">
                <h6 class="text-muted small mb-3">Year-to-Date ({{ now()->year }})</h6>
                <div class="mb-3">
                    <h3 class="mb-0">{{ $ytdTotals['activities'] }}</h3>
                    <small class="text-muted">Activities</small>
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
    
    <div class="col-md-8">
        <!-- Activity Breakdown by Contact Family -->
        <div class="card mb-3">
            <div class="card-header">
                <h5 class="mb-0">YTD Activity Breakdown</h5>
            </div>
            <div class="card-body">
                @if($contactFamilyBreakdown->isNotEmpty())
                <div class="table-responsive">
                    <table class="table table-sm">
                        <thead>
                            <tr>
                                <th>Contact Family</th>
                                <th class="text-end">Activities</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($contactFamilyBreakdown as $family => $count)
                            <tr>
                                <td><span class="badge bg-primary">{{ $family }}</span></td>
                                <td class="text-end"><strong>{{ $count }}</strong></td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                @else
                <p class="text-muted mb-0">No YTD activity recorded</p>
                @endif
            </div>
        </div>
        
        <!-- Recent Activity -->
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">Recent Activity</h5>
            </div>
            <div class="card-body">
                @if($recentActivities->isNotEmpty())
                <div class="table-responsive">
                    <table class="table table-sm table-hover">
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Agreement</th>
                                <th>Activity Type</th>
                                <th>Hours</th>
                                <th>Logged By</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($recentActivities as $activity)
                            <tr>
                                <td><a href="{{ route('activities.show', $activity) }}" class="text-decoration-none text-dark d-block">{{ $activity->engagement_date->format('M d, Y') }}</a></td>
                                <td>{{ $activity->agreement->name }}</td>
                                <td>{{ $activity->activityType->name }}</td>
                                <td>{{ number_format($activity->total_hours, 2) }}</td>
                                <td>{{ $activity->user->name }}</td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                @else
                <p class="text-muted mb-0">No activities logged yet</p>
                @endif
            </div>
        </div>
    </div>
</div>

<!-- Agreements List -->
<div class="row">
    <div class="col-12">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0">Agreements ({{ $agreements->count() }})</h5>
                @if(auth()->user()->isAdmin())
                <a href="{{ route('agreements.create') }}" class="btn btn-sm btn-primary">Create Agreement</a>
                @endif
            </div>
            <div class="card-body">
                @if($agreements->isNotEmpty())
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>Name</th>
                                <th>Team Members</th>
                                <th>Start Date</th>
                                <th>End Date</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($agreements as $agreement)
                            <tr>
                                <td><a href="{{ route('agreements.show', $agreement) }}" class="text-decoration-none text-dark d-block"><strong>{{ $agreement->name }}</strong></a></td>
                                <td>{{ $agreement->users->count() }}</td>
                                <td>{{ $agreement->start_date?->format('M d, Y') ?? '—' }}</td>
                                <td>{{ $agreement->end_date?->format('M d, Y') ?? '—' }}</td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                @else
                <div class="text-center py-4 text-muted">
                    <p class="mb-3">No agreements under this organization yet.</p>
                    @if(auth()->user()->isAdmin())
                    <a href="{{ route('agreements.create') }}" class="btn btn-primary">Create First Agreement</a>
                    @endif
                </div>
                @endif
            </div>
        </div>
    </div>
</div>

<div class="row mt-3">
    <div class="col-12">
        <a href="{{ route('organizations.index') }}" class="btn btn-outline-secondary">Back to Organizations</a>
    </div>
</div>
@endsection
