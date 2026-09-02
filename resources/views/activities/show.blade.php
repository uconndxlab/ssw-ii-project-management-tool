@extends('layouts.app')

@section('title', 'Activity Details')

@section('content')
@php
    $canManageActivity = auth()->user()->can('update', $activity);
    $canViewActionLog = auth()->user()->can('viewActionLog', $activity);
@endphp
<div class="row mb-4">
    <div class="col-12">
        <x-page-header
            context="show"
            :title="$activity->activityType->name"
            entity-type="Activity"
            :description="$activity->identityLabel(includeType: false)"
        >
            <x-slot:badges>
                @if($activity->internal_only)
                    <span class="badge bg-warning text-dark">Internal only</span>
                @endif
                @if($activity->cancelled)
                    <x-status-badge :active="false" inactive-label="Cancelled" />
                @endif
            </x-slot:badges>
            @if($canViewActionLog || $canManageActivity)
                <x-slot:controls>
                    <div class="d-flex gap-2">
                        <x-activity-action-log-button :activity="$activity" :labeled="true" :link-activity="false" />
                        @if($canManageActivity)
                            <a href="{{ route('activities.edit', $activity) }}" class="btn btn-outline-primary">
                                <i class="bi bi-pencil-square me-1"></i>
                                Edit
                            </a>
                        @endif
                    </div>
                </x-slot:controls>
            @endif
        </x-page-header>
    </div>
</div>

<div class="row">
    <div class="col-lg-8">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">Basic Information</h5>
            </div>
            <div class="card-body">
                @if($activity->cancelled)
                    <div class="alert alert-warning py-2">
                        This activity is marked cancelled. It remains visible in history and reports, but it does not count toward deliverable progress.
                    </div>
                @endif

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
                            @if($agreement->isLinkable())
                                <a href="{{ route('agreements.show', $agreement) }}" class="badge bg-secondary text-decoration-none me-1 mb-1">{{ $agreement->name }}</a>
                            @else
                                <span class="badge bg-secondary me-1 mb-1">{{ $agreement->name }}</span>
                            @endif
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
                        <x-user-link :user="$activity->user" />
                    </div>
                </div>

                <div class="row mb-3">
                    <div class="col-md-4">
                        <strong>Family:</strong>
                    </div>
                    <div class="col-md-8">
                        <span class="badge bg-primary">{{ $activity->activityType->contactFamily->name }}</span>
                    </div>
                </div>

                <div class="row mb-3">
                    <div class="col-md-4">
                        <strong>Type:</strong>
                    </div>
                    <div class="col-md-8">
                        <span class="badge bg-info">{{ $activity->activityType->name }}</span>
                    </div>
                </div>

                @php
                    $activityDuration = \App\Support\ActivityTypeDuration::fromActivity($activity);
                    $completionCount = (int) ($activity->completion_count ?? 1);
                @endphp
                <div class="row mb-3">
                    <div class="col-md-4">
                        <strong>Completions:</strong>
                    </div>
                    <div class="col-md-8">
                        {{ $completionCount }}
                    </div>
                </div>
                @if($activityDuration->hasDuration())
                <div class="row mb-3">
                    <div class="col-md-4">
                        <strong>Allotted Duration (per completion):</strong>
                    </div>
                    <div class="col-md-8">
                        {{ $activityDuration->formatLabel() }}
                    </div>
                </div>
                <div class="row mb-3">
                    <div class="col-md-4">
                        <strong>Total Allotted:</strong>
                    </div>
                    <div class="col-md-8">
                        {{ $activityDuration->formatTotalLabel($completionCount) }}
                    </div>
                </div>
                @endif

                @if($activity->projects->isNotEmpty())
                <div class="row mb-3">
                    <div class="col-md-4">
                        <strong>Projects:</strong>
                    </div>
                    <div class="col-md-8">
                        @foreach($activity->projects as $project)
                            <span class="badge bg-dark me-1">{{ $project->name }}</span>
                        @endforeach
                    </div>
                </div>
                @endif

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
                        <x-user-link :user="$activity->user" />
                    </div>
                </div>
                @if($activity->participants->count() > 0)
                <div class="row mt-2">
                    <div class="col-md-4">
                        <strong>Delivered By:</strong>
                    </div>
                    <div class="col-md-8">
                        @foreach($activity->participants as $participant)
                            <x-user-link :user="$participant" class="badge bg-primary me-1" />
                        @endforeach
                    </div>
                </div>
                @endif
            </div>
        </div>

        @if($activity->contactTime || $activity->participantTimes->isNotEmpty())
        <div class="card mt-3">
            <div class="card-header">
                <h5 class="mb-0">Time Tracking</h5>
            </div>
            <div class="card-body">
                @if($activity->contactTime)
                <div class="mb-4">
                    <h6 class="mb-3">Time by Contact</h6>
                    <div class="row g-3">
                        <div class="col-md-4">
                            <div class="small text-muted">Activity Time</div>
                            <div>{{ number_format((float) $activity->contactTime->activity_hours, 2) }}</div>
                        </div>
                        <div class="col-md-4">
                            <div class="small text-muted">Prep Time</div>
                            <div>{{ number_format((float) $activity->contactTime->prep_hours, 2) }}</div>
                        </div>
                        <div class="col-md-4">
                            <div class="small text-muted">Follow Up Time</div>
                            <div>{{ number_format((float) $activity->contactTime->follow_up_hours, 2) }}</div>
                        </div>
                    </div>
                </div>
                @endif

                @if($activity->participantTimes->isNotEmpty())
                <div>
                    <h6 class="mb-3">Time by User</h6>
                    <div class="table-responsive">
                        <table class="table table-sm align-middle mb-0">
                            <thead>
                                <tr>
                                    <th scope="col">Delivered By</th>
                                    <th scope="col">Prep Time</th>
                                    <th scope="col">Activity Time</th>
                                    <th scope="col">Follow Up Time</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($activity->participantTimes as $participantTime)
                                    <tr>
                                        <td>
                                            @if($participantTime->user)
                                                <x-user-link :user="$participantTime->user" :label="$participantTime->participant_name" />
                                            @elseif($participantTime->user_id)
                                                {{ $participantTime->participant_name ?? 'Historical User' }}
                                            @else
                                                DELETED USER
                                            @endif
                                        </td>
                                        <td>{{ number_format((float) $participantTime->prep_hours, 2) }}</td>
                                        <td>{{ number_format((float) $participantTime->hours, 2) }}</td>
                                        <td>{{ number_format((float) $participantTime->follow_up_hours, 2) }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
                @endif
            </div>
        </div>
        @endif



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

                <div class="row mb-2">
                    <div class="col-md-4">
                        <strong>Cancelled:</strong>
                    </div>
                    <div class="col-md-8">
                        @if($activity->cancelled)
                            <x-status-badge :active="false" inactive-label="Cancelled" />
                            <span class="text-muted small ms-2">Visible historically, excluded from deliverable progress.</span>
                        @else
                            <span class="text-muted">No</span>
                        @endif
                    </div>
                </div>

            </div>
        </div>



        @php
            $agreementLoggingValues = $activity->agreement_logging_values;
            $contactFamilyLoggingValues = $activity->contact_family_logging_values;
            $activityTypeLoggingValues = $activity->activity_type_logging_values;
            $fundingSourcesByAgreement = $activity->agreementFundingSources->groupBy('agreement_id');
            $agreementScopedFields = $scopedLoggingFields['agreements'] ?? collect();
            $contactFamilyScopedFields = $scopedLoggingFields['contact_family'] ?? collect();
            $activityTypeScopedFields = $scopedLoggingFields['activity_type'] ?? collect();
            $hasRenderableValue = function ($value) {
                if (is_array($value)) {
                    return !empty($value);
                }

                return $value !== null && $value !== '';
            };
            $hasAgreementFieldContent = $activity->agreements->contains(function ($agreement) use ($agreementScopedFields, $fundingSourcesByAgreement) {
                return ($agreementScopedFields[(string) $agreement->id] ?? collect())->isNotEmpty()
                    || $fundingSourcesByAgreement->get($agreement->id, collect())->isNotEmpty();
            });
        @endphp
        @if($hasAgreementFieldContent || $contactFamilyScopedFields->isNotEmpty() || $activityTypeScopedFields->isNotEmpty())
        <div class="card mt-3">
            <div class="card-header">
                <h5 class="mb-0">Dynamic Logging Fields</h5>
            </div>
            <div class="card-body">
                @if($hasAgreementFieldContent)
                    <div class="mb-4">
                        <h6 class="mb-3">Agreement Fields</h6>
                        @foreach($activity->agreements as $agreement)
                            @php
                                $visibleFields = $agreementScopedFields[(string) $agreement->id] ?? collect();
                                $agreementValues = $agreementLoggingValues[$agreement->id] ?? [];
                                $agreementFunding = $fundingSourcesByAgreement->get($agreement->id, collect());
                                $payorSources = $agreementFunding->where('role', 'payor');
                                $payeeSources = $agreementFunding->where('role', 'payee');
                                $hasLoggingValues = $visibleFields->isNotEmpty();
                                $hasFundingSources = $payorSources->isNotEmpty() || $payeeSources->isNotEmpty();
                            @endphp
                            @if($hasLoggingValues || $hasFundingSources)
                                <div class="border rounded p-3 mb-3">
                                    <div class="fw-semibold mb-2">{{ $agreement->name }}</div>
                                    @if($hasLoggingValues)
                                        <div class="row g-2">
                                            @foreach($visibleFields as $field)
                                                @php
                                                    $value = $agreementValues[$field->id] ?? null;
                                                    $optionLabelMap = $field->optionLabelMap();
                                                @endphp
                                                <div class="col-md-6">
                                                    <div class="small text-muted">{{ $field->name }}</div>
                                                    <div>
                                                        @if($field->field_type === 'document' && $hasRenderableValue($value))
                                                                <a href="{{ route('activities.logging-field-document.download', ['activity' => $activity, 'context' => 'agreement', 'fieldId' => $field->id, 'agreementId' => $agreement->id]) }}" class="text-decoration-none" target="_blank">
                                                                    <i class="bi bi-file-earmark-arrow-down me-1"></i>{{ basename($value) }}
                                                                </a>
                                                            @elseif($field->isMultiselect() && is_array($value) && !empty($value))
                                                                <div class="d-flex flex-wrap gap-1">
                                                                    @foreach($value as $selectedId)
                                                                        <span class="badge text-bg-light border">{{ $optionLabelMap[(string) $selectedId] ?? $selectedId }}</span>
                                                                    @endforeach
                                                                </div>
                                                            @elseif(is_bool($value))
                                                                {{ $value ? 'Yes' : 'No' }}
                                                            @elseif($hasRenderableValue($value))
                                                                {{ $value }}
                                                            @else
                                                                <span class="text-muted">-</span>
                                                            @endif
                                                    </div>
                                                </div>
                                            @endforeach
                                        </div>
                                    @endif
                                    @include('activities.partials.agreement-funding-sources', [
                                        'payorSources' => $payorSources,
                                        'payeeSources' => $payeeSources,
                                        'hasLoggingValuesAbove' => $hasLoggingValues,
                                    ])
                                </div>
                            @endif
                        @endforeach
                    </div>
                @endif

                @if($contactFamilyScopedFields->isNotEmpty())
                    <div>
                        <h6 class="mb-3">Family Fields</h6>
                        <div class="row g-2">
                            @foreach($contactFamilyScopedFields as $field)
                                @php
                                    $value = $contactFamilyLoggingValues[$field->id] ?? null;
                                    $optionLabelMap = $field->optionLabelMap();
                                @endphp
                                <div class="col-md-6">
                                    <div class="small text-muted">{{ $field->name }}</div>
                                    <div>
                                        @if($field->field_type === 'document' && $hasRenderableValue($value))
                                                <a href="{{ route('activities.logging-field-document.download', ['activity' => $activity, 'context' => 'contact_family', 'fieldId' => $field->id]) }}" class="text-decoration-none" target="_blank">
                                                    <i class="bi bi-file-earmark-arrow-down me-1"></i>{{ basename($value) }}
                                                </a>
                                            @elseif($field->isMultiselect() && is_array($value) && !empty($value))
                                                <div class="d-flex flex-wrap gap-1">
                                                    @foreach($value as $selectedId)
                                                        <span class="badge text-bg-light border">{{ $optionLabelMap[(string) $selectedId] ?? $selectedId }}</span>
                                                    @endforeach
                                                </div>
                                            @elseif(is_bool($value))
                                                {{ $value ? 'Yes' : 'No' }}
                                            @elseif($hasRenderableValue($value))
                                                {{ $value }}
                                            @else
                                                <span class="text-muted">-</span>
                                            @endif
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endif

                @if($activityTypeScopedFields->isNotEmpty())
                    <div class="mt-4">
                        <h6 class="mb-3">Activity Fields</h6>
                        <div class="row g-2">
                            @foreach($activityTypeScopedFields as $field)
                                @php
                                    $value = $activityTypeLoggingValues[$field->id] ?? null;
                                    $optionLabelMap = $field->optionLabelMap();
                                @endphp
                                @if($field)
                                    <div class="col-md-6">
                                        <div class="small text-muted">{{ $field->name }}</div>
                                        <div>
                                            @if($field->field_type === 'document' && $hasRenderableValue($value))
                                                <a href="{{ route('activities.logging-field-document.download', ['activity' => $activity, 'context' => 'activity_type', 'fieldId' => $field->id]) }}" class="text-decoration-none" target="_blank">
                                                    <i class="bi bi-file-earmark-arrow-down me-1"></i>{{ basename($value) }}
                                                </a>
                                            @elseif($field->isMultiselect() && is_array($value) && !empty($value))
                                                <div class="d-flex flex-wrap gap-1">
                                                    @foreach($value as $selectedId)
                                                        <span class="badge text-bg-light border">{{ $optionLabelMap[(string) $selectedId] ?? $selectedId }}</span>
                                                    @endforeach
                                                </div>
                                            @elseif(is_bool($value))
                                                {{ $value ? 'Yes' : 'No' }}
                                            @elseif($hasRenderableValue($value))
                                                {{ $value }}
                                            @else
                                                <span class="text-muted">-</span>
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

<x-activity-action-log-modal />
@endsection
