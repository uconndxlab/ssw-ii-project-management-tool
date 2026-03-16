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

<!-- Agreement Info & Stats Row -->
<div class="row mb-4">
    <!-- Agreement Details -->
    <div class="col-md-8">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">Agreement Details</h5>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <h6 class="text-muted small mb-1">Organization</h6>
                        <p class="mb-0">
                            <a href="{{ route('organizations.show', $agreement->organization) }}">{{ $agreement->organization->name }}</a>
                        </p>
                    </div>
                    <div class="col-md-6 mb-3">
                        <h6 class="text-muted small mb-1">State</h6>
                        <p class="mb-0">{{ $agreement->state->name }}</p>
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
                    <div class="col-md-6 mb-2">
                        <h6 class="text-muted small mb-1">Start Date</h6>
                        <p class="mb-0">{{ $agreement->start_date?->format('M d, Y') ?? 'Not set' }}</p>
                    </div>
                    <div class="col-md-6 mb-2">
                        <h6 class="text-muted small mb-1">End Date</h6>
                        <p class="mb-0">{{ $agreement->end_date?->format('M d, Y') ?? 'Not set' }}</p>
                    </div>
                    @if($agreement->original_end_date || $agreement->extended_end_date)
                    <div class="col-md-6">
                        <h6 class="text-muted small mb-1">Original End Date</h6>
                        <p class="mb-0">{{ $agreement->original_end_date?->format('M d, Y') ?? '—' }}</p>
                    </div>
                    <div class="col-md-6">
                        <h6 class="text-muted small mb-1">Extended End Date</h6>
                        <p class="mb-0">{{ $agreement->extended_end_date?->format('M d, Y') ?? '—' }}</p>
                    </div>
                    @endif
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
    
    <!-- Stats Summary -->
    <div class="col-md-4 d-none">
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
        
        <div class="card d-none">
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

<!-- Deliverable Progress & Staff Row -->
<div class="row mb-4">
    <!-- Deliverable Progress -->
    <div class="col-md-8">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">Deliverable Progress</h5>
            </div>
            <div class="card-body">
                @if($deliverableProgress->isNotEmpty())
                    @foreach($deliverableProgress as $progress)
                    <div class="mb-4 {{ !$loop->last ? 'pb-3 border-bottom' : '' }}">
                        <div class="d-flex justify-content-between align-items-start mb-2">
                            <div>
                                <strong class="d-block">{{ $progress['deliverable']->activityType?->name ?? 'Unspecified Activity Type' }}</strong>
                                @if($progress['deliverable']->contactFamily)
                                <small class="text-muted">{{ $progress['deliverable']->contactFamily->name }}</small>
                                @endif
                            </div>
                        </div>
                        
                        @if($progress['deliverable']->required_hours)
                        <div class="mb-2">
                            <div class="d-flex justify-content-between align-items-center mb-1">
                                <span class="small text-muted">Hours Required</span>
                                <span class="small">
                                    <strong>{{ number_format($progress['completed_hours'], 1) }}</strong> / {{ number_format($progress['deliverable']->required_hours, 1) }}
                                </span>
                            </div>
                            @php
                                $hoursPercent = $progress['deliverable']->required_hours > 0 
                                    ? min(100, ($progress['completed_hours'] / $progress['deliverable']->required_hours) * 100) 
                                    : 0;
                            @endphp
                            <div class="progress" style="height: 8px;">
                                <div class="progress-bar {{ $hoursPercent >= 100 ? 'bg-success' : 'bg-primary' }}" 
                                     role="progressbar"
                                     style="width: {{ $hoursPercent }}%"
                                     aria-valuenow="{{ $hoursPercent }}"
                                     aria-valuemin="0" 
                                     aria-valuemax="100"></div>
                            </div>
                        </div>
                        @endif
                        
                        @if($progress['deliverable']->required_activities)
                        <div class="mb-2">
                            <div class="d-flex justify-content-between align-items-center mb-1">
                                <span class="small text-muted">Activities Required</span>
                                <span class="small">
                                    <strong>{{ $progress['completed_activities'] }}</strong> / {{ $progress['deliverable']->required_activities }}
                                </span>
                            </div>
                            @php
                                $activitiesPercent = $progress['deliverable']->required_activities > 0 
                                    ? min(100, ($progress['completed_activities'] / $progress['deliverable']->required_activities) * 100) 
                                    : 0;
                            @endphp
                            <div class="progress" style="height: 8px;">
                                <div class="progress-bar {{ $activitiesPercent >= 100 ? 'bg-success' : 'bg-primary' }}" 
                                     role="progressbar"
                                     style="width: {{ $activitiesPercent }}%"
                                     aria-valuenow="{{ $activitiesPercent }}"
                                     aria-valuemin="0" 
                                     aria-valuemax="100"></div>
                            </div>
                        </div>
                        @endif
                        
                        @if($progress['deliverable']->notes)
                        <div class="small text-muted mt-2">
                            <em>{{ $progress['deliverable']->notes }}</em>
                        </div>
                        @endif
                        
                        @if(!$progress['deliverable']->required_hours && !$progress['deliverable']->required_activities)
                        <div class="small text-muted">
                            <em>No specific requirements defined</em>
                        </div>
                        @endif
                    </div>
                    @endforeach
                @else
                    <p class="text-muted mb-0">No deliverables defined for this agreement.</p>
                @endif
            </div>
        </div>
    </div>
    
    <!-- Assigned Staff & Certification Candidates -->
    <div class="col-md-4">
        <!-- Assigned Staff -->
        <div class="card mb-3">
            <div class="card-header">
                <h5 class="mb-0">Assigned Staff</h5>
            </div>
            <div class="card-body">
                @if($agreement->users->isNotEmpty())
                    <div class="list-group list-group-flush">
                        @foreach($agreement->users as $user)
                        <div class="list-group-item px-0 py-2">
                            <strong class="d-block">{{ $user->name }}</strong>
                            <small class="text-muted">{{ ucfirst($user->role) }}</small>
                        </div>
                        @endforeach
                    </div>
                @else
                    <p class="text-muted mb-0">No team members assigned</p>
                @endif
            </div>
        </div>
        
        <!-- Certification Candidates -->
        @if($agreement->certification_candidates)
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">Certification Candidates</h5>
            </div>
            <div class="card-body">
                <p class="mb-0" style="white-space: pre-line;">{{ $agreement->certification_candidates }}</p>
            </div>
        </div>
        @endif
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
