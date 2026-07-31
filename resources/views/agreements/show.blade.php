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
@endphp

<x-entity-show
    title="{{ $agreement->name }}"
    type="Agreement"
    :typeBadgeClass="\App\Support\EntityBadge::typeClasses('agreement')"
    editRoute="{{ auth()->user()->isAdmin() ? route('agreements.edit', $agreement) : null }}"
    backRoute="{{ route('agreements.index') }}"
    backLabel="All Agreements"
    mainCardTitle="Deliverables"
    :activityFirst="true"
>
    {{-- ── Summary ─────────────────────────────────────────────────────── --}}
    <x-slot:summary>
        <dl class="row mb-0" style="min-width: 0;">
            <dt class="col-5 text-muted fw-normal small">Status</dt>
            <dd class="col-7 mb-2">
                <x-status-badge :active="$agreement->active" />
            </dd>

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
                        <x-entity-relation-badge kind="project" :href="route('projects.show', $project)">
                            {{ $project->name }}
                        </x-entity-relation-badge>
                    @empty
                        <span class="text-muted small">None</span>
                    @endforelse
                </div>
            </dd>

            <dt class="col-5 text-muted fw-normal small">Programs</dt>
            <dd class="col-7 mb-2">
                <div class="d-flex flex-wrap gap-1">
                    @forelse($agreement->programs->sortBy('name') as $program)
                        <x-entity-relation-badge kind="program" :href="route('programs.show', $program)">
                            {{ $program->name }}
                        </x-entity-relation-badge>
                    @empty
                        <span class="text-muted small">None</span>
                    @endforelse
                </div>
            </dd>

            <dt class="col-5 text-muted fw-normal small">Organizations</dt>
            <dd class="col-7 mb-2" style="min-width: 0;">
                @forelse($agreement->organizations->sortBy('name') as $org)
                    <x-organization-relation-row :organization="$org" :class="$loop->last ? '' : 'border-bottom'" />
                @empty
                    <span class="text-muted small">None</span>
                @endforelse
            </dd>

            <dt class="col-5 text-muted fw-normal small">States</dt>
            <dd class="col-7 mb-2">
                <div class="d-flex flex-wrap gap-1">
                    @forelse($agreement->states as $state)
                        <x-entity-relation-badge kind="state" :href="route('states.show', $state)">
                            {{ $state->name }}
                        </x-entity-relation-badge>
                    @empty
                        <span class="text-muted small">None</span>
                    @endforelse
                </div>
            </dd>

            <dt class="col-5 text-muted fw-normal small">Activities</dt>
            <dd class="col-7 mb-2">
                <x-entity-count-badge kind="activity" :count="$lifetimeTotals['activities']" />
                <span class="text-muted small ms-1">lifetime</span>
                <x-entity-count-badge kind="activity" :count="$ytdTotals['activities']" class="ms-2" />
                <span class="text-muted small ms-1">YTD</span>
            </dd>

            @if($activityOnlyPrograms->isNotEmpty())
                <dt class="col-5 text-muted fw-normal small">Programs in Activity</dt>
                <dd class="col-7 mb-2">
                    <div class="d-flex flex-wrap gap-1">
                        @foreach($activityOnlyPrograms as $program)
                            <x-entity-relation-badge kind="program" :href="route('programs.show', $program)">
                                {{ $program->name }}
                            </x-entity-relation-badge>
                        @endforeach
                    </div>
                </dd>
            @endif
        </dl>

        @if($agreement->abstract)
            <hr>
            <h6 class="text-muted fw-semibold small text-uppercase mb-2" style="letter-spacing:.05em;">Abstract</h6>
            <p class="small mb-0">{{ $agreement->abstract }}</p>
        @endif

        <hr>
        <h6 class="text-muted fw-semibold small text-uppercase mb-3" style="letter-spacing:.05em;">Assigned Staff</h6>
        @if($hasAnyUsers)
            <div class="d-grid gap-2">
                @foreach($agreement->teams as $team)
                    @php
                        $teamOnlyUsers = $team->users->whereNotIn('id', $directUserIds);
                    @endphp
                    @if($teamOnlyUsers->isNotEmpty())
                        <div class="border rounded overflow-hidden bg-body">
                            <div class="px-3 py-2 border-bottom bg-light d-flex align-items-center gap-2">
                                <x-entity-relation-badge kind="team" :href="route('teams.show', $team)">
                                    {{ $team->name }}
                                </x-entity-relation-badge>
                                <x-entity-count-badge kind="team" :count="$teamOnlyUsers->count()" class="ms-auto" />
                            </div>
                            <div class="px-3 py-1">
                                @foreach($teamOnlyUsers as $user)
                                    <x-staff-member-row
                                        :user="$user"
                                        :role="$user->role"
                                        :is-principal-investigator="$agreement->principalInvestigators->contains('id', $user->id)"
                                        :class="$loop->last ? '' : 'border-bottom'"
                                    />
                                @endforeach
                            </div>
                        </div>
                    @endif
                @endforeach

                @if($directUsers->isNotEmpty())
                    <div class="border rounded overflow-hidden bg-body">
                        <div class="px-3 py-2 border-bottom bg-light">
                            <span class="small fw-semibold text-muted text-uppercase" style="letter-spacing:.05em;">Additional users</span>
                        </div>
                        <div class="px-3 py-1">
                            @foreach($directUsers as $user)
                                <x-staff-member-row
                                    :user="$user"
                                    :role="$user->role"
                                    :is-principal-investigator="!empty($user->is_principal_investigator)"
                                    :class="$loop->last ? '' : 'border-bottom'"
                                >
                                    <x-slot:after>
                                        @if(!empty($user->also_in_teams))
                                            @foreach($agreement->teams->filter(fn ($team) => $team->users->contains('id', $user->id)) as $team)
                                                <x-entity-relation-badge kind="team" :href="route('teams.show', $team)">
                                                    {{ $team->name }}
                                                </x-entity-relation-badge>
                                            @endforeach
                                        @endif
                                    </x-slot:after>
                                </x-staff-member-row>
                            @endforeach
                        </div>
                    </div>
                @endif
            </div>
        @else
            <p class="text-muted small mb-0">No staff assigned.</p>
        @endif

        @if($agreement->certificationCandidates->isNotEmpty())
            <hr>
            <h6 class="text-muted fw-semibold small text-uppercase mb-2" style="letter-spacing:.05em;">Certification Candidates</h6>
            <div class="small d-grid gap-1 mb-0">
                @foreach($agreement->certificationCandidates as $candidate)
                    <div>{{ $candidate->name }}</div>
                @endforeach
            </div>
        @endif

        @if($agreement->attachments->isNotEmpty())
            <hr>
            <h6 class="text-muted fw-semibold small text-uppercase mb-2" style="letter-spacing:.05em;">Attachments</h6>
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
        @if(!$agreement->active)
            <div class="alert alert-secondary py-2 small mb-3 mb-0">
                Activity logging is disabled for inactive agreements. Historical activities are shown below.
            </div>
        @endif
        <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
            @if($agreement->active)
                <a href="{{ route('activities.create') }}?agreement_id={{ $agreement->id }}" class="btn btn-sm btn-success">
                    Log Activity
                </a>
            @else
                <span class="btn btn-sm btn-success disabled" aria-disabled="true">Log Activity</span>
            @endif
            @if($recentActivities->isNotEmpty())
                <a href="{{ route('activities.index') }}?agreement_id={{ $agreement->id }}" class="btn btn-sm btn-outline-secondary">
                    View All Activities
                </a>
            @endif
        </div>

        @if($recentActivities->isNotEmpty())
            <x-recent-activity-table :activities="$recentActivities" variant="agreement" />
        @else
            <div class="text-center py-3">
                <p class="text-muted mb-0">No activities logged for this agreement yet.</p>
            </div>
        @endif
    </x-slot:activity>
</x-entity-show>
@endsection
