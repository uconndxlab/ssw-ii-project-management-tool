@extends('layouts.app')

@section('title', $agreement->name)

@section('content')

@php
    $usersBySource = $agreement->getUsersBySource();
    $directUsers = $usersBySource['direct'];
    $directUserIds = $directUsers->pluck('id');
    $hasAnyUsers = $directUsers->isNotEmpty() || $agreement->teams->isNotEmpty();
    $agreementProgramIds = $agreement->programs->pluck('id')->map(fn ($id) => (int) $id);
    $activityOnlyPrograms = $programs->filter(fn ($program) => !$agreementProgramIds->contains((int) $program->id));
    $badgeLinkClass = 'text-decoration-underline';
@endphp

<x-entity-show
    title="{{ $agreement->name }}"
    type="Agreement"
    typeBadgeClass="bg-success"
    editRoute="{{ auth()->user()->isAdmin() ? route('agreements.edit', $agreement) : null }}"
    backRoute="{{ route('agreements.index') }}"
    backLabel="All Agreements"
    mainCardTitle="Deliverables"
    :activityFirst="true"
>
    {{-- ── Summary ─────────────────────────────────────────────────────── --}}
    <x-slot:summary>
        <dl class="row mb-0" style="min-width: 0;">
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

            <dt class="col-5 text-muted fw-normal small">Projects</dt>
            <dd class="col-7 mb-2">
                <div class="d-flex flex-wrap gap-1">
                    @forelse($agreement->projects->sortBy('name') as $project)
                        <a href="{{ route('projects.show', $project) }}" class="badge bg-primary-subtle text-primary-emphasis border {{ $badgeLinkClass }}">{{ $project->name }}</a>
                    @empty
                        <span class="text-muted small">None</span>
                    @endforelse
                </div>
            </dd>

            <dt class="col-5 text-muted fw-normal small">Programs</dt>
            <dd class="col-7 mb-2">
                <div class="d-flex flex-wrap gap-1">
                    @forelse($agreement->programs->sortBy('name') as $program)
                        <a href="{{ route('programs.show', $program) }}" class="badge bg-warning-subtle text-warning-emphasis border {{ $badgeLinkClass }}">{{ $program->name }}</a>
                    @empty
                        <span class="text-muted small">None</span>
                    @endforelse
                </div>
            </dd>

            <dt class="col-5 text-muted fw-normal small">Organizations</dt>
            <dd class="col-7 mb-2" style="min-width: 0;">
                <div class="d-flex flex-wrap gap-1 w-100" style="min-width: 0;">
                    @forelse($agreement->organizations as $org)
                        <a href="{{ route('organizations.show', $org) }}"
                           class="badge bg-secondary text-break text-start {{ $badgeLinkClass }}"
                           style="white-space: normal; line-height: 1.35; max-width: 100%; flex: 1 1 100%;">{{ $org->name }}</a>
                    @empty
                        <span class="text-muted small">None</span>
                    @endforelse
                </div>
            </dd>

            <dt class="col-5 text-muted fw-normal small">States</dt>
            <dd class="col-7 mb-2">
                <div class="d-flex flex-wrap gap-1">
                    @forelse($agreement->states as $state)
                        <a href="{{ route('states.show', $state) }}" class="badge bg-info text-dark {{ $badgeLinkClass }}">{{ $state->name }}</a>
                    @empty
                        <span class="text-muted small">None</span>
                    @endforelse
                </div>
            </dd>

            <dt class="col-5 text-muted fw-normal small">Activities</dt>
            <dd class="col-7 mb-2">
                <span class="badge bg-primary rounded-pill">{{ $lifetimeTotals['activities'] }}</span>
                <span class="text-muted small ms-1">lifetime</span>
                <span class="badge bg-light text-dark border rounded-pill ms-2">{{ $ytdTotals['activities'] }}</span>
                <span class="text-muted small ms-1">YTD</span>
            </dd>

            @if($activityOnlyPrograms->isNotEmpty())
                <dt class="col-5 text-muted fw-normal small">Programs in Activity</dt>
                <dd class="col-7 mb-2">
                    <div class="d-flex flex-wrap gap-1">
                        @foreach($activityOnlyPrograms as $program)
                            <a href="{{ route('programs.show', $program) }}" class="badge bg-warning-subtle text-warning-emphasis border {{ $badgeLinkClass }}">{{ $program->name }}</a>
                        @endforeach
                    </div>
                </dd>
            @endif
        </dl>

        @if($agreement->abstract)
            <hr>
            <h6 class="text-muted fw-normal small mb-1">Abstract</h6>
            <p class="small mb-0">{{ $agreement->abstract }}</p>
        @endif

        <hr>
        <h6 class="text-muted fw-normal small mb-2">Assigned Staff</h6>
        @if($hasAnyUsers)
            @if($directUsers->isNotEmpty())
                <div class="small fw-semibold text-muted mb-1">Additional users</div>
                @foreach($directUsers as $user)
                    <div class="small py-1 border-bottom">
                        <a href="{{ route('users.show', $user) }}" class="fw-semibold text-decoration-underline">{{ $user->name }}</a>
                        <span class="text-muted">· {{ ucfirst($user->role) }}</span>
                        @if(!empty($user->is_principal_investigator))
                            <span class="badge bg-warning-subtle text-dark ms-1">PI</span>
                        @endif
                        @if(!empty($user->also_in_teams))
                            @foreach($agreement->teams->filter(fn ($team) => $team->users->contains('id', $user->id)) as $team)
                                <a href="{{ route('teams.show', $team) }}" class="badge bg-secondary-subtle text-secondary-emphasis border ms-1 {{ $badgeLinkClass }}">{{ $team->name }}</a>
                            @endforeach
                        @endif
                    </div>
                @endforeach
            @endif

            @foreach($agreement->teams as $team)
                @php
                    $teamOnlyUsers = $team->users->whereNotIn('id', $directUserIds);
                @endphp
                @if($teamOnlyUsers->isNotEmpty())
                    <a href="{{ route('teams.show', $team) }}" class="small fw-semibold text-decoration-underline d-block mt-2 mb-1">{{ $team->name }}</a>
                    @foreach($teamOnlyUsers as $user)
                        <div class="small py-1 border-bottom ps-2">
                            <a href="{{ route('users.show', $user) }}" class="text-decoration-underline">{{ $user->name }}</a>
                            <span class="text-muted">· {{ ucfirst($user->role) }}</span>
                            @if($agreement->principalInvestigators->contains('id', $user->id))
                                <span class="badge bg-warning-subtle text-dark ms-1">PI</span>
                            @endif
                        </div>
                    @endforeach
                @endif
            @endforeach
        @else
            <p class="text-muted small mb-0">No staff assigned.</p>
        @endif

        @if($agreement->certificationCandidates->isNotEmpty())
            <hr>
            <h6 class="text-muted fw-normal small mb-2">Certification Candidates</h6>
            <div class="small d-grid gap-1 mb-0">
                @foreach($agreement->certificationCandidates as $candidate)
                    <div>{{ $candidate->name }}</div>
                @endforeach
            </div>
        @endif

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

    {{-- ── Deliverables ────────────────────────────────────────────────── --}}
    <x-slot:relationships>
        @include('agreements.partials.show-deliverables', ['deliverableGroups' => $deliverableGroups])
    </x-slot:relationships>

    {{-- ── Recent Activity ─────────────────────────────────────────────── --}}
    <x-slot:activity>
        <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
            <a href="{{ route('activities.create') }}?agreement_id={{ $agreement->id }}" class="btn btn-sm btn-success">
                Log Activity
            </a>
            @if($recentActivities->isNotEmpty())
                <a href="{{ route('activities.index') }}?agreement_id={{ $agreement->id }}" class="btn btn-sm btn-outline-secondary">
                    View All Activities
                </a>
            @endif
        </div>

        @if($recentActivities->isNotEmpty())
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
                <p class="text-muted mb-0">No activities logged for this agreement yet.</p>
            </div>
        @endif
    </x-slot:activity>
</x-entity-show>
@endsection
