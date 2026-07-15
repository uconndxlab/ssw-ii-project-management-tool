@extends('layouts.app')

@section('title', $agreement->name)

@section('content')



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

            @if($agreement->extension_start_date || $agreement->extension_end_date)
            <dt class="col-5 text-muted fw-normal small">Extension Start</dt>
            <dd class="col-7 mb-2 small">{{ $agreement->extension_start_date?->format('M d, Y') ?? '—' }}</dd>

            <dt class="col-5 text-muted fw-normal small">Extension End</dt>
            <dd class="col-7 mb-2 small">{{ $agreement->extension_end_date?->format('M d, Y') ?? '—' }}</dd>
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
            </dd>
        </dl>

        @if($agreement->abstract)
            <hr>
            <h6 class="text-muted fw-normal small mb-1">Abstract</h6>
            <p class="small mb-0">{{ $agreement->abstract }}</p>
        @endif



        <hr>
        <a href="{{ route('activities.create') }}?agreement_id={{ $agreement->id }}" class="btn btn-sm btn-success w-100">
            Log Activity
        </a>

        @if($agreement->attachments->isNotEmpty())
            <hr>
            <h6 class="text-muted fw-normal small mb-2">Attachments</h6>
            @foreach($agreement->attachments as $attachment)
                <div class="d-flex align-items-center justify-content-between mb-2">
                    <div class="small text-truncate me-2" title="{{ $attachment->filename }}">
                        <i class="bi bi-file-earmark me-1"></i>{{ $attachment->filename }}
                    </div>
                    <a href="{{ $attachment->download_url }}"
                       class="btn btn-sm btn-outline-primary text-nowrap"
                       target="_blank">
                        View / Download
                    </a>
                </div>
            @endforeach
        @endif
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
                    @if($directUsers->isNotEmpty())
                        <div class="py-1 bg-light px-2 rounded mb-1">
                            <small class="fw-semibold text-primary">Additional users</small>
                        </div>
                    @endif
                    @foreach($directUsers as $user)
                    <div class="py-2 border-bottom">
                        <a href="{{ route('users.show', $user) }}" class="fw-semibold text-decoration-none d-block">
                            {{ $user->name }}
                        </a>
                        <small class="text-muted">{{ ucfirst($user->role) }}</small>
                        @if(!empty($user->is_principal_investigator))
                            <span class="badge bg-warning-subtle text-dark ms-1">PI</span>
                        @endif
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
                            @if(!empty($user->is_principal_investigator))
                                <span class="badge bg-warning-subtle text-dark ms-1">PI</span>
                            @endif
                        </div>
                        @endforeach
                    @endforeach
                @else
                    <p class="text-muted small mb-0">No staff assigned.</p>
                @endif

                @if($agreement->certificationCandidates->isNotEmpty())
                    <hr>
                    <h6 class="fw-semibold mb-2">Certification Candidates</h6>
                    <div class="small mb-0 d-grid gap-1">
                        @foreach($agreement->certificationCandidates as $candidate)
                            <div>{{ $candidate->name }}</div>
                        @endforeach
                    </div>
                @endif
            </div>

            {{-- Deliverable Progress --}}
            <div class="col-md-7">
                <h6 class="fw-semibold mb-3">Deliverable Progress</h6>
                @if($deliverableProgress->isNotEmpty())
                    @foreach($deliverableProgress as $progress)
                    <div class="mb-4 {{ !$loop->last ? 'pb-3 border-bottom' : '' }}">
                        <strong class="d-block">{{ $progress['deliverable']->contactFamily?->name ?? 'Unspecified Contact Family' }}</strong>
                        <small class="text-muted d-block">{{ $progress['deliverable']->activityType?->name ?? 'Any activity type' }}</small>
                        @if($progress['deliverable']->program)
                            <small class="text-muted d-block">Program: {{ $progress['deliverable']->program->name }}</small>
                        @endif
                        @if($progress['deliverable']->suggested_due_date)
                            <small class="text-muted">{{ $progress['deliverable']->suggested_due_date ? 'Suggested Due: ' . $progress['deliverable']->suggested_due_date->format('M d, Y') : '' }}</small>
                        @endif
                        @php
                            $deliverable = $progress['deliverable'];
                            $target = (float) ($deliverable->target_quantity ?? 0);
                            $completedValue = (float) $progress['completed_value'];
                            $percent = $target > 0 ? min(100, ($completedValue / $target) * 100) : 0;
                            $unitLabel = $deliverable->metric_type === 'time' ? 'Hours' : 'Completions';
                        @endphp
                        <div class="mt-3 border rounded p-3">
                            <div class="d-flex justify-content-between align-items-start gap-3 mb-1">
                                <div>
                                    <div class="fw-semibold">{{ ucfirst($deliverable->metric_type ?? 'deliverable') }}</div>
                                    <div class="text-muted small">
                                        {{ ucfirst($deliverable->contribution_basis ?? 'unspecified') }}
                                        @if($deliverable->contribution_basis === 'user' && $deliverable->user_grouping_mode)
                                            | {{ ucfirst($deliverable->user_grouping_mode) }}
                                        @endif
                                        @if($deliverable->include_additional_time)
                                            | Includes Prep/Follow Up
                                        @endif
                                    </div>
                                </div>
                                <div class="text-end small">
                                    <strong>{{ number_format($completedValue, 1) }}</strong>
                                    @if($target > 0)
                                        / {{ number_format($target, 1) }}
                                    @endif
                                </div>
                            </div>

                            <div class="progress mb-2" style="height:6px;">
                                <div class="progress-bar {{ $percent >= 100 ? 'bg-success' : 'bg-primary' }}" style="width:{{ $percent }}%"></div>
                            </div>

                            <div class="small text-muted">{{ $unitLabel }}</div>

                            @if($progress['assigned_teams']->isNotEmpty())
                                <div class="mt-2">
                                    <small class="text-muted">Teams: </small>
                                    @foreach($progress['assigned_teams'] as $team)
                                        <span class="badge bg-secondary me-1">{{ $team->name }}</span>
                                    @endforeach
                                </div>
                            @endif

                            @if($deliverable->contribution_basis === 'user' && $deliverable->user_grouping_mode === 'joint' && $progress['assigned_users']->isNotEmpty())
                                <div class="mt-2">
                                    <small class="text-muted">Assigned Users: </small>
                                    @foreach($progress['assigned_users'] as $user)
                                        <span class="badge bg-light text-dark border me-1">{{ $user->name }}</span>
                                    @endforeach
                                </div>
                            @endif

                            @if($progress['individual_progress']->isNotEmpty())
                                <div class="mt-2 small">
                                    @foreach($progress['individual_progress'] as $individual)
                                        <div class="d-flex justify-content-between">
                                            <span>{{ $individual['user']->name }}</span>
                                            <span>{{ number_format($individual['completed_value'], 1) }}@if($target > 0) / {{ number_format($target, 1) }}@endif</span>
                                        </div>
                                    @endforeach
                                </div>
                            @endif
                        </div>

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
