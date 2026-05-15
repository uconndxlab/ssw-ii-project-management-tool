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
                        <strong>Agreements:</strong>
                    </div>
                    <div class="col-md-8">
                        @forelse($activity->agreements as $agreement)
                            <span class="badge bg-secondary me-1 mb-1">{{ $agreement->name }}</span>
                        @empty
                            <span class="text-muted small">None assigned</span>
                        @endforelse
                    </div>
                </div>

                <div class="row mb-3">
                    <div class="col-md-4">
                        <strong>Organizations:</strong>
                    </div>
                    <div class="col-md-8">
                        @forelse($activity->organizations as $organization)
                            <span class="badge bg-secondary me-1 mb-1">{{ $organization->name }}</span>
                        @empty
                            <span class="text-muted small">None assigned</span>
                        @endforelse
                    </div>
                </div>

                <div class="row mb-3">
                    <div class="col-md-4">
                        <strong>States:</strong>
                    </div>
                    <div class="col-md-8">
                        @forelse($activity->states as $state)
                            <span class="badge bg-info me-1 mb-1">{{ $state->name }}</span>
                        @empty
                            <span class="text-muted small">None assigned</span>
                        @endforelse
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
                @if($activity->time_tracking_mode === 'participant')
                    <p class="text-muted mb-3"><small>Time tracked by participant</small></p>
                    @if($activity->participantTimes->count() > 0)
                        <table class="table table-sm table-bordered">
                            <thead>
                                <tr>
                                    <th>Participant</th>
                                    <th class="text-end">Hours</th>
                                    <th>Notes</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($activity->participantTimes as $pt)
                                    <tr>
                                        <td>{{ $pt->user->name }}</td>
                                        <td class="text-end">{{ number_format($pt->hours, 2) }}</td>
                                        <td><small class="text-muted">{{ $pt->notes ?? '—' }}</small></td>
                                    </tr>
                                @endforeach
                                <tr class="table-light">
                                    <td><strong>Total</strong></td>
                                    <td class="text-end"><strong>{{ number_format($activity->participantTimes->sum('hours'), 2) }}</strong></td>
                                    <td></td>
                                </tr>
                            </tbody>
                        </table>
                    @else
                        <p class="text-muted"><small>No participant times recorded.</small></p>
                    @endif
                @else
                    <p class="text-muted mb-3"><small>Time tracked by engagement</small></p>
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
                @endif

                @if($activity->participant_count)
                <div class="mt-3">
                    <strong>Participants:</strong> {{ number_format($activity->participant_count) }}
                </div>
                @endif

                @if($activity->external_attendees)
                <div class="mt-3">
                    <strong>External Attendees:</strong><br>
                    {{ $activity->external_attendees }}
                </div>
                @endif
            </div>
        </div>

        <div class="card mt-3">
            <div class="card-header">
                <h5 class="mb-0">Reporting & Visibility</h5>
            </div>
            <div class="card-body">
                <div class="row mb-2">
                    <div class="col-md-4">
                        <strong>Internal Only:</strong>
                    </div>
                    <div class="col-md-8">
                        @if($activity->internal_only)
                            <span class="badge bg-warning">Yes - Excluded from external reports</span>
                        @else
                            <span class="badge bg-success">No - Available for external reports</span>
                        @endif
                    </div>
                </div>
                <div class="row">
                    <div class="col-md-4">
                        <strong>Time Tracking Mode:</strong>
                    </div>
                    <div class="col-md-8">
                        @if($activity->time_tracking_mode === 'participant')
                            <span class="badge bg-info">By Participant</span>
                        @else
                            <span class="badge bg-secondary">By Engagement</span>
                        @endif
                    </div>
                </div>
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

        @if(!empty($activity->logging_field_data['agreements']) || !empty($activity->logging_field_data['contact_family']))
        <div class="card mt-3">
            <div class="card-header">
                <h5 class="mb-0">Dynamic Logging Fields</h5>
            </div>
            <div class="card-body">
                @if(!empty($activity->logging_field_data['agreements']))
                    <div class="mb-4">
                        <h6 class="mb-3">Agreement Fields</h6>
                        @foreach($activity->agreements as $agreement)
                            @php
                                $agreementValues = $activity->logging_field_data['agreements'][$agreement->id] ?? [];
                            @endphp
                            @if(!empty($agreementValues))
                                <div class="border rounded p-3 mb-3">
                                    <div class="fw-semibold mb-2">{{ $agreement->name }}</div>
                                    <div class="row g-2">
                                        @foreach($agreement->agreementLoggingFields as $field)
                                            @php
                                                $value = $agreementValues[$field->id] ?? null;
                                            @endphp
                                            @if($value !== null && $value !== '')
                                                <div class="col-md-6">
                                                    <div class="small text-muted">{{ $field->name }}</div>
                                                    <div>
                                                        {{ is_bool($value) ? ($value ? 'Yes' : 'No') : $value }}
                                                    </div>
                                                </div>
                                            @endif
                                        @endforeach
                                    </div>
                                </div>
                            @endif
                        @endforeach
                    </div>
                @endif

                @if(!empty($activity->logging_field_data['contact_family']))
                    <div>
                        <h6 class="mb-3">Contact Family Fields</h6>
                        <div class="row g-2">
                            @foreach($activity->activityType->contactFamily->contactFamilyLoggingFields as $field)
                                @php
                                    $value = $activity->logging_field_data['contact_family'][$field->id] ?? null;
                                @endphp
                                @if($value !== null && $value !== '')
                                    <div class="col-md-6">
                                        <div class="small text-muted">{{ $field->name }}</div>
                                        <div>
                                            {{ is_bool($value) ? ($value ? 'Yes' : 'No') : $value }}
                                        </div>
                                    </div>
                                @endif
                            @endforeach
                        </div>
                    </div>
                @endif
            </div>
        </div>
        @endif
    </div>
</div>
@endsection
