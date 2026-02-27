@extends('layouts.app')

@section('title', 'Engagement Details')

@section('content')
<div class="row mb-4">
    <div class="col-12">
        <div class="d-flex justify-content-between align-items-center">
            <h1>Engagement Details</h1>
            <div>
                @if(auth()->user()->isAdmin() || $engagement->user_id === auth()->id())
                <a href="{{ route('engagements.edit', $engagement) }}" class="btn btn-primary">Edit</a>
                @endif
                <a href="{{ route('engagements.index') }}" class="btn btn-secondary">Back to List</a>
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
                        {{ $engagement->engagement_date->format('F d, Y') }}
                    </div>
                </div>

                <div class="row mb-3">
                    <div class="col-md-4">
                        <strong>Project:</strong>
                    </div>
                    <div class="col-md-8">
                        {{ $engagement->project->name }}<br>
                        <small class="text-muted">{{ $engagement->project->organization->name }} ({{ $engagement->project->state->name }})</small>
                    </div>
                </div>

                <div class="row mb-3">
                    <div class="col-md-4">
                        <strong>Logged By:</strong>
                    </div>
                    <div class="col-md-8">
                        {{ $engagement->user->name }}
                    </div>
                </div>

                <div class="row mb-3">
                    <div class="col-md-4">
                        <strong>Activity Type:</strong>
                    </div>
                    <div class="col-md-8">
                        <span class="badge bg-info">{{ str_replace('_', ' ', ucwords($engagement->activity_type, '_')) }}</span>
                    </div>
                </div>

                <div class="row mb-3">
                    <div class="col-md-4">
                        <strong>Deliverable Bucket:</strong>
                    </div>
                    <div class="col-md-8">
                        <span class="badge bg-secondary">{{ str_replace('_', ' ', ucwords($engagement->deliverable_bucket, '_')) }}</span>
                    </div>
                </div>

                @if($engagement->programs->isNotEmpty())
                <div class="row mb-3">
                    <div class="col-md-4">
                        <strong>Programs:</strong>
                    </div>
                    <div class="col-md-8">
                        @foreach($engagement->programs as $program)
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
                        {{ $engagement->user->name }}
                    </div>
                </div>
                @if($engagement->participants->count() > 0)
                <div class="row mt-2">
                    <div class="col-md-4">
                        <strong>Delivered By:</strong>
                    </div>
                    <div class="col-md-8">
                        @foreach($engagement->participants as $participant)
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
                            <td>{{ number_format($engagement->event_hours, 2) }}</td>
                            <td>{{ number_format($engagement->prep_hours ?? 0, 2) }}</td>
                            <td>{{ number_format($engagement->followup_hours ?? 0, 2) }}</td>
                            <td><strong>{{ number_format($engagement->total_hours, 2) }}</strong></td>
                        </tr>
                    </tbody>
                </table>

                @if($engagement->participant_count)
                <div class="mt-3">
                    <strong>Participants:</strong> {{ number_format($engagement->participant_count) }}
                </div>
                @endif
            </div>
        </div>

        @if($engagement->summary || $engagement->follow_up || $engagement->strengths || $engagement->recommendations)
        <div class="card mt-3">
            <div class="card-header">
                <h5 class="mb-0">Narrative Details</h5>
            </div>
            <div class="card-body">
                @if($engagement->summary)
                <div class="mb-3">
                    <h6>Summary</h6>
                    <p>{{ $engagement->summary }}</p>
                </div>
                @endif

                @if($engagement->follow_up)
                <div class="mb-3">
                    <h6>Follow-Up</h6>
                    <p>{{ $engagement->follow_up }}</p>
                </div>
                @endif

                @if($engagement->strengths)
                <div class="mb-3">
                    <h6>Strengths</h6>
                    <p>{{ $engagement->strengths }}</p>
                </div>
                @endif

                @if($engagement->recommendations)
                <div class="mb-3">
                    <h6>Recommendations</h6>
                    <p>{{ $engagement->recommendations }}</p>
                </div>
                @endif
            </div>
        </div>
        @endif
    </div>
</div>
@endsection
