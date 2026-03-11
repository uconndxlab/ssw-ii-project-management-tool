@extends('layouts.app')

@section('title', $agreement->name)

@section('content')
<div class="row mb-4">
    <div class="col-12">
        <div class="d-flex justify-content-between align-items-center">
            <div>
                <h1>{{ $agreement->name }}</h1>
                <p class="text-muted mb-0">{{ $agreement->organization->name }} • {{ $agreement->state->name }}</p>
            </div>
            <div>
                <a href="{{ route('activities.create') }}?agreement_id={{ $agreement->id }}" class="btn btn-success">Log Activity</a>
                @if(auth()->user()->isAdmin())
                <a href="{{ route('agreements.edit', $agreement) }}" class="btn btn-outline-primary">Edit Agreement</a>
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
                <h5 class="mb-0">Agreement Overview</h5>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <h6 class="text-muted small mb-1">State</h6>
                        <p class="mb-0">{{ $agreement->state->name }}</p>
                    </div>
                    <div class="col-md-6 mb-3">
                        <h6 class="text-muted small mb-1">Organization</h6>
                        <p class="mb-0">
                            <a href="{{ route('organizations.show', $agreement->organization) }}">{{ $agreement->organization->name }}</a>
                        </p>
                    </div>
                </div>
                
                @if($agreement->abstract)
                <div class="row">
                    <div class="col-12 mb-3">
                        <h6 class="text-muted small mb-1">Abstract</h6>
                        <p class="mb-0">{{ $agreement->abstract }}</p>
                    </div>
                </div>
                @endif
                
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <h6 class="text-muted small mb-1">Start Date</h6>
                        <p class="mb-0">{{ $agreement->start_date?->format('M d, Y') ?? 'Not set' }}</p>
                    </div>
                    <div class="col-md-6 mb-3">
                        <h6 class="text-muted small mb-1">End Date</h6>
                        <p class="mb-0">{{ $agreement->end_date?->format('M d, Y') ?? 'Not set' }}</p>
                    </div>
                    @if($agreement->original_end_date)
                    <div class="col-md-6 mb-3">
                        <h6 class="text-muted small mb-1">Original End Date</h6>
                        <p class="mb-0">{{ $agreement->original_end_date->format('M d, Y') }}</p>
                    </div>
                    @endif
                    @if($agreement->extended_end_date)
                    <div class="col-md-6 mb-3">
                        <h6 class="text-muted small mb-1">Extended End Date</h6>
                        <p class="mb-0">{{ $agreement->extended_end_date->format('M d, Y') }}</p>
                    </div>
                    @endif
                    @if($agreement->certification_candidates)
                    <div class="col-12 mb-3">
                        <h6 class="text-muted small mb-1">Certification Candidates</h6>
                        <p class="mb-0">{{ $agreement->certification_candidates }}</p>
                    </div>
                    @endif
                </div>
                
                <div class="mt-3">
                    <h6 class="text-muted small mb-2">Assigned Team Members ({{ $agreement->users->count() }})</h6>
                    <div class="d-flex flex-wrap gap-2">
                        @foreach($agreement->users as $user)
                            <span class="badge bg-secondary">{{ $user->name }} ({{ ucfirst($user->role) }})</span>
                        @endforeach
                        @if($agreement->users->isEmpty())
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
                    <h3 class="mb-0">{{ $lifetimeTotals['activities'] }}</h3>
                    <small class="text-muted">Total Activities</small>
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
</div>

<!-- Recent Activity Section -->
<div class="row">
    <div class="col-12">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0">Recent Activity</h5>
                <a href="{{ route('activities.index') }}?agreement_id={{ $agreement->id }}" class="btn btn-sm btn-outline-secondary">
                    View All Activities
                </a>
            </div>
            <div class="card-body">
                @if($recentActivities->isNotEmpty())
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
                            @foreach($recentActivities as $activity)
                            <tr>
                                <td><a href="{{ route('activities.show', $activity) }}" class="text-decoration-none text-dark d-block">{{ $activity->engagement_date->format('M d, Y') }}</a></td>
                                <td><span class="badge bg-primary">{{ $activity->activityType->contactFamily->name }}</span></td>
                                <td>{{ $activity->activityType->name }}</td>
                                <td>{{ number_format($activity->event_hours, 2) }}</td>
                                <td>{{ number_format($activity->total_hours, 2) }}</td>
                                <td>
                                    @if($activity->participant_count)
                                        {{ $activity->participant_count }}
                                    @else
                                        <span class="text-muted">—</span>
                                    @endif
                                </td>
                                <td>{{ $activity->user->name }}</td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                @else
                <div class="text-center py-4 text-muted">
                    <p class="mb-3">No activities logged for this agreement yet.</p>
                    <a href="{{ route('activities.create') }}" class="btn btn-primary">Log First Activity</a>
                </div>
                @endif
            </div>
        </div>
    </div>
</div>

<div class="row mt-3">
    <div class="col-12">
        <a href="{{ route('agreements.index') }}" class="btn btn-outline-secondary">Back to Agreements</a>
    </div>
</div>
@endsection
