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

            </div>
        </div>



        @php
            $agreementLoggingValues = $activity->agreement_logging_values;
            $contactFamilyLoggingValues = $activity->contact_family_logging_values;
            $activityTypeLoggingValues = $activity->activity_type_logging_values;
        @endphp
        @if(!empty($agreementLoggingValues) || !empty($contactFamilyLoggingValues) || !empty($activityTypeLoggingValues))
        <div class="card mt-3">
            <div class="card-header">
                <h5 class="mb-0">Dynamic Logging Fields</h5>
            </div>
            <div class="card-body">
                @if(!empty($agreementLoggingValues))
                    <div class="mb-4">
                        <h6 class="mb-3">Agreement Fields</h6>
                        @foreach($activity->agreements as $agreement)
                            @php
                                $agreementValues = $agreementLoggingValues[$agreement->id] ?? [];
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
                                                        @if($field->field_type === 'document')
                                                            <a href="{{ route('activities.logging-field-document.download', ['activity' => $activity, 'context' => 'agreement', 'fieldId' => $field->id, 'agreementId' => $agreement->id]) }}" class="text-decoration-none" target="_blank">
                                                                <i class="bi bi-file-earmark-arrow-down me-1"></i>{{ basename($value) }}
                                                            </a>
                                                        @elseif(is_bool($value))
                                                            {{ $value ? 'Yes' : 'No' }}
                                                        @else
                                                            {{ $value }}
                                                        @endif
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

                @if(!empty($contactFamilyLoggingValues))
                    <div>
                        <h6 class="mb-3">Contact Family Fields</h6>
                        <div class="row g-2">
                            @foreach($activity->activityType->contactFamily->contactFamilyLoggingFields as $field)
                                @php
                                    $value = $contactFamilyLoggingValues[$field->id] ?? null;
                                @endphp
                                @if($value !== null && $value !== '')
                                    <div class="col-md-6">
                                        <div class="small text-muted">{{ $field->name }}</div>
                                        <div>
                                            @if($field->field_type === 'document')
                                                <a href="{{ route('activities.logging-field-document.download', ['activity' => $activity, 'context' => 'contact_family', 'fieldId' => $field->id]) }}" class="text-decoration-none" target="_blank">
                                                    <i class="bi bi-file-earmark-arrow-down me-1"></i>{{ basename($value) }}
                                                </a>
                                            @elseif(is_bool($value))
                                                {{ $value ? 'Yes' : 'No' }}
                                            @else
                                                {{ $value }}
                                            @endif
                                        </div>
                                    </div>
                                @endif
                            @endforeach
                        </div>
                    </div>
                @endif

                @if(!empty($activityTypeLoggingValues))
                    <div class="mt-4">
                        <h6 class="mb-3">Activity Fields</h6>
                        <div class="row g-2">
                            @foreach($activity->activityType->activityTypeLoggingFields as $field)
                                @php
                                    $value = $activityTypeLoggingValues[$field->id] ?? null;
                                @endphp
                                @if($field && $value !== null && $value !== '')
                                    <div class="col-md-6">
                                        <div class="small text-muted">{{ $field->name }}</div>
                                        <div>
                                            @if($field->field_type === 'document')
                                                <a href="{{ route('activities.logging-field-document.download', ['activity' => $activity, 'context' => 'activity_type', 'fieldId' => $field->id]) }}" class="text-decoration-none" target="_blank">
                                                    <i class="bi bi-file-earmark-arrow-down me-1"></i>{{ basename($value) }}
                                                </a>
                                            @elseif(is_bool($value))
                                                {{ $value ? 'Yes' : 'No' }}
                                            @else
                                                {{ $value }}
                                            @endif
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
