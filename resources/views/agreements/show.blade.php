@extends('layouts.app')

@section('title', $agreement->name)

@section('content')

@php
    $defaultActivityLoggingConfig = [
        'event_hours' => true,
        'prep_hours' => true,
        'followup_hours' => false,
        'participant_count' => true,
        'external_attendees' => true,
        'summary' => true,
        'follow_up' => true,
        'strengths' => false,
        'recommendations' => false,
    ];

    $activityLoggingConfig = $agreement->activity_logging_config ?? $defaultActivityLoggingConfig;

    $activityLoggingLabels = [
        'event_hours' => 'Event Hours',
        'prep_hours' => 'Prep Hours',
        'followup_hours' => 'Follow-up Hours',
        'participant_count' => 'Participants',
        'external_attendees' => 'External Attendees',
        'summary' => 'Summary',
        'follow_up' => 'Follow-Up',
        'strengths' => 'Strengths',
        'recommendations' => 'Recommendations',
    ];

    $enabledActivityLoggingFields = collect($activityLoggingLabels)
        ->filter(fn ($label, $key) => !empty($activityLoggingConfig[$key]))
        ->values();
@endphp

<x-entity-show
    title="{{ $agreement->name }}"
    type="Agreement"
    typeBadgeClass="bg-success"
    editRoute="{{ auth()->user()->isAdmin() ? route('agreements.edit', $agreement) : null }}"
    backRoute="{{ route('agreements.index') }}"
    backLabel="All Agreements"
>
    {{-- ── Summary ─────────────────────────────────────────────────────── --}}
    <x-slot:summary>
        <dl class="row mb-0">
            <dt class="col-5 text-muted fw-normal small">Start Date</dt>
            <dd class="col-7 mb-2">{{ $agreement->start_date?->format('M d, Y') ?? '—' }}</dd>

            <dt class="col-5 text-muted fw-normal small">End Date</dt>
            <dd class="col-7 mb-2">{{ $agreement->end_date?->format('M d, Y') ?? '—' }}</dd>

            @if($agreement->original_end_date || $agreement->extended_end_date)
            <dt class="col-5 text-muted fw-normal small">Original End</dt>
            <dd class="col-7 mb-2 small">{{ $agreement->original_end_date?->format('M d, Y') ?? '—' }}</dd>

            <dt class="col-5 text-muted fw-normal small">Extended End</dt>
            <dd class="col-7 mb-2 small">{{ $agreement->extended_end_date?->format('M d, Y') ?? '—' }}</dd>
            @endif

            <dt class="col-5 text-muted fw-normal small">Organizations</dt>
            <dd class="col-7 mb-2">
                @forelse($agreement->organizations as $org)
                    <a href="{{ route('organizations.show', $org) }}" class="badge bg-secondary text-decoration-none me-1 mb-1">
                        {{ $org->name }}
                    </a>
                @empty
                    <span class="text-muted small">None</span>
                @endforelse
            </dd>

            <dt class="col-5 text-muted fw-normal small">States</dt>
            <dd class="col-7 mb-2">
                @forelse($agreement->states as $state)
                    <a href="{{ route('states.show', $state) }}" class="badge bg-info text-dark text-decoration-none me-1 mb-1">
                        {{ $state->name }}
                    </a>
                @empty
                    <span class="text-muted small">None</span>
                @endforelse
            </dd>

            <dt class="col-5 text-muted fw-normal small">Activities</dt>
            <dd class="col-7 mb-0">
                <span class="badge bg-primary rounded-pill">{{ $lifetimeTotals['activities'] }}</span>
                <small class="text-muted d-block">{{ number_format($lifetimeTotals['hours'], 1) }} hrs lifetime</small>
            </dd>
        </dl>

        @if($agreement->abstract)
            <hr>
            <h6 class="text-muted fw-normal small mb-1">Abstract</h6>
            <p class="small mb-0">{{ $agreement->abstract }}</p>
        @endif

        @if($enabledActivityLoggingFields->isNotEmpty())
            <hr>
            <h6 class="text-muted fw-normal small mb-2">Logging Fields</h6>
            <div class="d-flex flex-wrap gap-1">
                @foreach($enabledActivityLoggingFields as $fieldLabel)
                    <span class="badge bg-light text-dark border">{{ $fieldLabel }}</span>
                @endforeach
            </div>
        @endif

        <hr>
        <a href="{{ route('activities.create') }}?agreement_id={{ $agreement->id }}" class="btn btn-sm btn-success w-100">
            Log Activity
        </a>
    </x-slot:summary>

    {{-- ── Relationships ───────────────────────────────────────────────── --}}
    <x-slot:relationships>
        <div class="row g-4">
            {{-- Assigned Staff --}}
            <div class="col-md-5">
                <h6 class="fw-semibold mb-3">Assigned Staff</h6>
                @php
                    $usersBySource = $agreement->getUsersBySource();
                    $directUsers = $usersBySource['direct'];
                    $teamGroups = $usersBySource['teams'];
                    $hasAnyUsers = $directUsers->isNotEmpty() || !empty($teamGroups);
                @endphp

                @if($hasAnyUsers)
                    @foreach($directUsers as $user)
                    <div class="py-2 border-bottom">
                        <a href="{{ route('users.show', $user) }}" class="fw-semibold text-decoration-none d-block">
                            {{ $user->name }}
                        </a>
                        <small class="text-muted">{{ ucfirst($user->role) }}</small>
                        @if(!empty($user->also_in_teams))
                            @foreach($user->also_in_teams as $teamName)
                                <span class="badge bg-info text-dark ms-1">{{ $teamName }}</span>
                            @endforeach
                        @endif
                    </div>
                    @endforeach
                    @foreach($teamGroups as $teamName => $teamUsers)
                        <div class="py-1 bg-light px-2 rounded mt-2 mb-1">
                            <small class="fw-semibold text-primary">Team: {{ $teamName }}</small>
                        </div>
                        @foreach($teamUsers as $user)
                        <div class="py-2 border-bottom ps-3">
                            <a href="{{ route('users.show', $user) }}" class="fw-semibold text-decoration-none d-block">
                                {{ $user->name }}
                            </a>
                            <small class="text-muted">{{ ucfirst($user->role) }}</small>
                        </div>
                        @endforeach
                    @endforeach
                @else
                    <p class="text-muted small mb-0">No staff assigned.</p>
                @endif

                @if($agreement->certification_candidates)
                    <hr>
                    <h6 class="fw-semibold mb-2">Certification Candidates</h6>
                    <p class="small mb-0" style="white-space: pre-line;">{{ $agreement->certification_candidates }}</p>
                @endif
            </div>

            {{-- Deliverable Progress --}}
            <div class="col-md-7">
                <h6 class="fw-semibold mb-3">Deliverable Progress</h6>
                @if($deliverableProgress->isNotEmpty())
                    @foreach($deliverableProgress as $progress)
                    <div class="mb-4 {{ !$loop->last ? 'pb-3 border-bottom' : '' }}">
                        <strong class="d-block">{{ $progress['deliverable']->activityType?->name ?? 'Unspecified Activity Type' }}</strong>
                        @if($progress['deliverable']->contactFamily)
                            <small class="text-muted">{{ $progress['deliverable']->contactFamily->name }}</small>
                        @endif

                        @if($progress['deliverable']->required_hours)
                        <div class="mt-2">
                            <div class="d-flex justify-content-between mb-1">
                                <small class="text-muted">Hours</small>
                                <small><strong>{{ number_format($progress['completed_hours'], 1) }}</strong> / {{ number_format($progress['deliverable']->required_hours, 1) }}</small>
                            </div>
                            @php $hp = $progress['deliverable']->required_hours > 0 ? min(100, ($progress['completed_hours'] / $progress['deliverable']->required_hours) * 100) : 0; @endphp
                            <div class="progress" style="height:6px;"><div class="progress-bar {{ $hp >= 100 ? 'bg-success' : 'bg-primary' }}" style="width:{{ $hp }}%"></div></div>
                        </div>
                        @endif

                        @if($progress['deliverable']->required_activities)
                        <div class="mt-2">
                            <div class="d-flex justify-content-between mb-1">
                                <small class="text-muted">Activities</small>
                                <small><strong>{{ $progress['completed_activities'] }}</strong> / {{ $progress['deliverable']->required_activities }}</small>
                            </div>
                            @php $ap = $progress['deliverable']->required_activities > 0 ? min(100, ($progress['completed_activities'] / $progress['deliverable']->required_activities) * 100) : 0; @endphp
                            <div class="progress" style="height:6px;"><div class="progress-bar {{ $ap >= 100 ? 'bg-success' : 'bg-primary' }}" style="width:{{ $ap }}%"></div></div>
                        </div>
                        @endif

                        @if($progress['deliverable']->notes)
                            <small class="text-muted fst-italic mt-1 d-block">{{ $progress['deliverable']->notes }}</small>
                        @endif
                    </div>
                    @endforeach
                @else
                    <p class="text-muted small mb-0">No deliverables defined for this agreement.</p>
                @endif

                @if($programs->isNotEmpty())
                    <hr>
                    <h6 class="fw-semibold mb-2">Programs Represented</h6>
                    <div class="d-flex flex-wrap gap-1">
                        @foreach($programs as $program)
                            <a href="{{ route('programs.show', $program) }}" class="badge bg-warning text-dark text-decoration-none">
                                {{ $program->name }}
                            </a>
                        @endforeach
                    </div>
                @endif
            </div>
        </div>
    </x-slot:relationships>

    {{-- ── Recent Activity ─────────────────────────────────────────────── --}}
    <x-slot:activity>
        @if($recentActivities->isNotEmpty())
            <div class="d-flex justify-content-end mb-2">
                <a href="{{ route('activities.index') }}?agreement_id={{ $agreement->id }}" class="btn btn-sm btn-outline-secondary">
                    View All Activities
                </a>
            </div>
            <div class="table-responsive">
                <table class="table table-hover table-sm mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Date</th>
                            <th>Contact Family</th>
                            <th>Activity Type</th>
                            <th class="text-end">Hrs</th>
                            <th>Participants</th>
                            <th>Logged By</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($recentActivities as $activity)
                        <tr>
                            <td>
                                <a href="{{ route('activities.show', $activity) }}" class="text-decoration-none text-dark">
                                    {{ $activity->engagement_date->format('M d, Y') }}
                                </a>
                            </td>
                            <td><span class="badge bg-primary">{{ $activity->activityType->contactFamily->name }}</span></td>
                            <td class="small">{{ $activity->activityType->name }}</td>
                            <td class="text-end">{{ number_format($activity->total_hours, 1) }}</td>
                            <td>{{ $activity->participant_count ?? '—' }}</td>
                            <td class="small text-muted">{{ $activity->user->name }}</td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @else
            <div class="text-center py-3">
                <p class="text-muted mb-2">No activities logged for this agreement yet.</p>
                <a href="{{ route('activities.create') }}?agreement_id={{ $agreement->id }}" class="btn btn-sm btn-success">
                    Log First Activity
                </a>
            </div>
        @endif
    </x-slot:activity>
</x-entity-show>
@endsection
