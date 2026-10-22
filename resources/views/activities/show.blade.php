@extends('layouts.app')

@section('title', 'Activity Details')

@section('content')
@php
    $canManageActivity = auth()->user()->can('update', $activity);
    $activityDuration = \App\Support\ActivityTypeDuration::fromActivity($activity);
    $completionCount = (int) ($activity->completion_count ?? 1);
    $canViewActionLog = auth()->user()->can('viewActionLog', $activity);
@endphp
<x-page-header
    context="show"
    :title="$activity->activityType->name"
    entity-type="Activity"
    :description="$activity->identityLabel(includeType: false)"
    :action-url="$canManageActivity ? route('activities.edit', $activity) : null"
>
    <x-slot:facts>
        <div><dt>Date</dt><dd>{{ $activity->engagement_date->format('F d, Y') }}</dd></div>
        <div><dt>Logged by</dt><dd class="app-fact-links"><x-user-link :user="$activity->user" /></dd></div>
        <div><dt>Activity family</dt><dd>{{ $activity->activityType->contactFamily->name }}</dd></div>
    </x-slot:facts>
    <x-slot:badges>
        @if($activity->internal_only)
            <span class="app-status app-status--warning">Internal only</span>
        @endif
        @if($activity->cancelled)
            <x-status-badge :active="false" inactive-label="Cancelled" />
        @elseif($activity->not_yet_complete)
            <span class="badge app-tag">Not yet complete</span>
        @endif
    </x-slot:badges>
    <x-slot:more>
        @if($canViewActionLog)
            <li>
                <button type="button"
                        class="dropdown-item"
                        hx-get="{{ route('activities.action-logs', ['activity' => $activity, 'current' => 1]) }}"
                        hx-target="#activity-action-log-modal-body"
                        hx-swap="innerHTML"
                        onclick="window.showActivityActionLogModal()">
                    <i class="bi bi-journal-text me-2" aria-hidden="true"></i>View log
                </button>
            </li>
        @endif
        @can('duplicate', $activity)
            <li>
                <button type="submit"
                        form="activity-duplicate-form"
                        class="dropdown-item"
                        onclick="return confirm('Duplicate this activity? A new activity will be created with the same details.')">
                    <i class="bi bi-files me-2" aria-hidden="true"></i>Duplicate
                </button>
            </li>
        @endcan
    </x-slot:more>
</x-page-header>
@can('duplicate', $activity)
    <form id="activity-duplicate-form" method="POST" action="{{ route('activities.duplicate', $activity) }}" class="d-none">
        @csrf
    </form>
@endcan

<div class="row g-4">
    <div class="col-lg-4">
        <div class="card">
            <div class="card-header">
                <h2 class="h5 mb-0">Summary</h2>
            </div>
            <div class="card-body" data-record-summary>
                @if($activity->cancelled)
                    <div class="alert alert-warning py-2">
                        This activity is marked cancelled. It remains visible in history and reports, but it does not count toward deliverable progress.
                    </div>
                @elseif($activity->not_yet_complete)
                    <div class="alert alert-info py-2">
                        This activity is marked not yet complete. Time still counts toward deliverable progress; completions do not.
                    </div>
                @endif

                <dl class="app-record-metrics">
                    <div><dt>Completions</dt><dd>{{ $completionCount }}</dd></div>
                    @if($activityDuration->hasDuration())
                        <div><dt>Total duration</dt><dd>{{ $activityDuration->formatTotalLabel($completionCount) }}</dd></div>
                    @endif
                </dl>
                <x-relationship-scroll-panel title="Agreements" kind="agreement" :count="$activity->agreements->count()" collapsible collapsed class="mt-3">
                    @foreach($activity->agreements as $record)
                        <x-relationship-ledger-row :title="$record->name" :href="$record->isLinkable() ? route('agreements.show', $record) : null" />
                    @endforeach
                </x-relationship-scroll-panel>
                <x-relationship-scroll-panel title="Organizations" kind="organization" :count="$activity->organizations->count()" collapsible collapsed class="mt-3">
                    @foreach($activity->organizations as $record)
                        <x-relationship-ledger-row :title="$record->name" />
                    @endforeach
                </x-relationship-scroll-panel>
                <x-relationship-scroll-panel title="States" kind="state" :count="$activity->states->count()" collapsible collapsed class="mt-3">
                    @foreach($activity->states as $record)
                        <x-relationship-ledger-row :title="$record->name" />
                    @endforeach
                </x-relationship-scroll-panel>
                <x-relationship-scroll-panel title="Projects" kind="project" :count="$activity->projects->count()" collapsible collapsed class="mt-3">
                    @foreach($activity->projects as $record)
                        <x-relationship-ledger-row :title="$record->name" />
                    @endforeach
                </x-relationship-scroll-panel>
                <x-relationship-scroll-panel title="Programs" kind="program" :count="$activity->programs->count()" collapsible collapsed class="mt-3">
                    @foreach($activity->programs as $record)
                        <x-relationship-ledger-row :title="$record->name" />
                    @endforeach
                </x-relationship-scroll-panel>
            </div>
        </div>
    </div>
    <div class="col-lg-8">
        <div class="card">
            <div class="card-header">
                <h2 class="h5 mb-0">Delivery Team</h2>
            </div>
            <div class="card-body">
                @if($activity->participants->count() > 0)
                <div class="row">
                    <div class="col-md-4">
                        <strong>Delivered By:</strong>
                    </div>
                    <div class="col-md-8">
                        @foreach($activity->participants as $participant)
                            <x-user-link :user="$participant" class="badge app-chip me-1" />
                        @endforeach
                    </div>
                </div>
                @else
                    <p class="text-muted small mb-0">No delivery team members recorded.</p>
                @endif
            </div>
        </div>

        @if($activity->contactTime || $activity->participantTimes->isNotEmpty())
        <div class="card mt-3">
            <div class="card-header">
                <h2 class="h5 mb-0">Time Tracking</h2>
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
                <h2 class="h5 mb-0">Reporting & Visibility</h2>
            </div>
            <div class="card-body">
                <div class="row mb-2">
                    <div class="col-md-4">
                        <strong>Internal Only:</strong>
                    </div>
                    <div class="col-md-8">
                        @if($activity->internal_only)
                            <span class="app-status app-status--warning">Yes - Excluded from external reports</span>
                        @else
                            <span class="app-status app-status--success">No - Available for external reports</span>
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

                <div class="row mb-2">
                    <div class="col-md-4">
                        <strong>Not yet complete:</strong>
                    </div>
                    <div class="col-md-8">
                        @if($activity->not_yet_complete)
                            <span class="badge app-tag">Yes</span>
                            <span class="text-muted small ms-2">Time counts; completions are excluded from deliverable progress.</span>
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
                <h2 class="h5 mb-0">Dynamic Logging Fields</h2>
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
                                                                    <i class="bi bi-file-earmark-arrow-down me-1"></i>{{ $activity->loggingFieldFileName('agreement', (int) $field->id, (int) $agreement->id) ?: basename($value) }}
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
                                                    <i class="bi bi-file-earmark-arrow-down me-1"></i>{{ $activity->loggingFieldFileName('contact_family', (int) $field->id) ?: basename($value) }}
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
                                                    <i class="bi bi-file-earmark-arrow-down me-1"></i>{{ $activity->loggingFieldFileName('activity_type', (int) $field->id) ?: basename($value) }}
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
