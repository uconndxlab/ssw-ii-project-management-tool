@extends('layouts.app')

@section('title', 'Activity Details')

@section('content')
<div class="row mb-4">
    <div class="col-12">
        <div class="d-flex justify-content-between align-items-center">
            <h1>Activity Details</h1>
            <div>
                @if(auth()->user()->isAdmin() || $activity->user_id === auth()->id())
                <a href="{{ route('activities.edit', $activity) }}" class="btn btn-primary">Edit</a>
                @endif
                <a href="{{ route('activities.index') }}" class="btn btn-secondary">Back to Activities</a>
            </div>
        </div>
    </div>
</div>

<div class="row">
    <div class="col-lg-8">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">Basic Information</h5>
            </div>
            <div class="card-body">
                <div class="row mb-3">
                    <div class="col-md-4">
                        <strong>Date:</strong>
                    </div>
                    <div class="col-md-8">
                        {{ $activity->engagement_date->format('F d, Y') }}
                    </div>
                </div>

                <div class="row mb-3">
                    <div class="col-md-4">
                        <strong>Project:</strong>
                    </div>
                    <div class="col-md-8">
                        {{ $activity->agreement->name }}<br>
                        <small class="text-muted">{{ $activity->agreement->organization->name }} ({{ $activity->agreement->state->name }})</small>
                    </div>
                </div>

                <div class="row mb-3">
                    <div class="col-md-4">
                        <strong>Logged By:</strong>
                    </div>
                    <div class="col-md-8">
                        {{ $activity->user->name }}
                    </div>
                </div>

                <div class="row mb-3">
                    <div class="col-md-4">
                        <strong>Contact Family:</strong>
                    </div>
                    <div class="col-md-8">
                        <span class="badge bg-primary">{{ $activity->activityType->contactFamily->name }}</span>
                    </div>
                </div>

                <div class="row mb-3">
                    <div class="col-md-4">
                        <strong>Activity Type:</strong>
                    </div>
                    <div class="col-md-8">
                        <span class="badge bg-info">{{ $activity->activityType->name }}</span>
                    </div>
                </div>

                @if($activity->programs->isNotEmpty())
                <div class="row mb-3">
                    <div class="col-md-4">
                        <strong>Programs:</strong>
                    </div>
                    <div class="col-md-8">
                        @foreach($activity->programs as $program)
                            <span class="badge bg-success me-1">{{ $program->name }}</span>
                        @endforeach
                    </div>
                </div>
                @endif
            </div>
        </div>

        <div class="card mt-3">
            <div class="card-header">
                <h5 class="mb-0">Delivery Team</h5>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-4">
                        <strong>Logged By:</strong>
                    </div>
                    <div class="col-md-8">
                        {{ $activity->user->name }}
                    </div>
                </div>
                @if($activity->participants->count() > 0)
                <div class="row mt-2">
                    <div class="col-md-4">
                        <strong>Delivered By:</strong>
                    </div>
                    <div class="col-md-8">
                        @foreach($activity->participants as $participant)
                            <span class="badge bg-primary me-1">{{ $participant->name }}</span>
                        @endforeach
                    </div>
                </div>
                @endif
            </div>
        </div>

        <div class="card mt-3">
            <div class="card-header">
                <h5 class="mb-0">Hours Breakdown</h5>
            </div>
            <div class="card-body">
                <table class="table table-bordered">
                    <thead>
                        <tr>
                            <th>Event Hours</th>
                            <th>Prep Hours</th>
                            <th>Follow-Up Hours</th>
                            <th><strong>Total Hours</strong></th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td>{{ number_format($activity->event_hours, 2) }}</td>
                            <td>{{ number_format($activity->prep_hours ?? 0, 2) }}</td>
                            <td>{{ number_format($activity->followup_hours ?? 0, 2) }}</td>
                            <td><strong>{{ number_format($activity->total_hours, 2) }}</strong></td>
                        </tr>
                    </tbody>
                </table>

                @if($activity->participant_count)
                <div class="mt-3">
                    <strong>Participants:</strong> {{ number_format($activity->participant_count) }}
                </div>
                @endif
            </div>
        </div>

        @if($activity->summary || $activity->follow_up || $activity->strengths || $activity->recommendations)
        <div class="card mt-3">
            <div class="card-header">
                <h5 class="mb-0">Narrative Details</h5>
            </div>
            <div class="card-body">
                @if($activity->summary)
                <div class="mb-3">
                    <h6>Summary</h6>
                    <p>{{ $activity->summary }}</p>
                </div>
                @endif

                @if($activity->follow_up)
                <div class="mb-3">
                    <h6>Follow-Up</h6>
                    <p>{{ $activity->follow_up }}</p>
                </div>
                @endif

                @if($activity->strengths)
                <div class="mb-3">
                    <h6>Strengths</h6>
                    <p>{{ $activity->strengths }}</p>
                </div>
                @endif

                @if($activity->recommendations)
                <div class="mb-3">
                    <h6>Recommendations</h6>
                    <p>{{ $activity->recommendations }}</p>
                </div>
                @endif
            </div>
        </div>
        @endif
    </div>
</div>
@endsection
