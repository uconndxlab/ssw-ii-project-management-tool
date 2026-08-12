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
    $organizationKfsNumbers = $agreement->organizationKfsAccounts
        ->groupBy(fn ($account) => (int) $account->pivot->organization_id)
        ->map(fn ($accounts) => $accounts->pluck('number')->sort()->values()->all())
        ->all();
@endphp

<x-page-header
    context="show"
    :title="$agreement->name"
    entity-type="Agreement"
    :active="$agreement->active"
    :action-url="auth()->user()->isAdmin() ? route('agreements.edit', $agreement) : null"
/>

<x-entity-show mainCardTitle="Deliverables" :activityFirst="true">
    <x-slot:activityHeaderMeta>
        <x-entity-count-badge kind="activity" :count="$lifetimeTotals['activities']" />
        <span class="text-muted small">Lifetime</span>
        <x-entity-count-badge kind="activity" :count="$ytdTotals['activities']" />
        <span class="text-muted small">YTD</span>
    </x-slot:activityHeaderMeta>

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
        </dl>

        <div class="d-grid gap-3 mt-3">
            <x-relationship-scroll-panel
                title="Projects"
                kind="project"
                :count="$agreement->projects->count()"
                collapsible
                :collapsed="true">
                @forelse($agreement->projects->sortBy('name') as $project)
                    <x-relationship-ledger-row
                        :title="$project->name"
                        :href="route('projects.show', $project)"
                        kind="project"
                        title-as-badge
                        wrap-title
                    />
                @empty
                    <p class="text-muted small mb-0 py-1">No projects linked.</p>
                @endforelse
            </x-relationship-scroll-panel>

            <x-relationship-scroll-panel
                title="Programs"
                kind="program"
                :count="$agreement->programs->count()"
                collapsible
                :collapsed="true">
                @forelse($agreement->programs->sortBy('name') as $program)
                    <x-relationship-ledger-row
                        :title="$program->name"
                        :href="route('programs.show', $program)"
                        kind="program"
                        title-as-badge
                        wrap-title
                    />
                @empty
                    <p class="text-muted small mb-0 py-1">No programs linked.</p>
                @endforelse
            </x-relationship-scroll-panel>

            <x-relationship-scroll-panel
                title="Organizations"
                kind="organization"
                :count="$agreement->organizations->count()"
                collapsible
                :collapsed="true">
                @forelse($agreement->organizations->sortBy('name') as $org)
                    <x-organization-relation-row
                        :organization="$org"
                        :kfs-numbers="$organizationKfsNumbers[(int) $org->id] ?? []"
                    />
                @empty
                    <p class="text-muted small mb-0 py-1">No organizations linked.</p>
                @endforelse
            </x-relationship-scroll-panel>

            <x-relationship-scroll-panel
                title="States"
                kind="state"
                :count="$agreement->states->count()"
                collapsible
                :collapsed="true">
                @forelse($agreement->states->sortBy('name') as $state)
                    <x-relationship-ledger-row
                        :title="$state->name"
                        :href="route('states.show', $state)"
                        kind="state"
                        title-as-badge
                        wrap-title
                    />
                @empty
                    <p class="text-muted small mb-0 py-1">No states linked.</p>
                @endforelse
            </x-relationship-scroll-panel>

            @if($activityOnlyPrograms->isNotEmpty())
                <x-relationship-scroll-panel
                    title="Programs in Activity"
                    kind="program"
                    :count="$activityOnlyPrograms->count()"
                    collapsible
                    :collapsed="true">
                    @foreach($activityOnlyPrograms->sortBy('name') as $program)
                        <x-relationship-ledger-row
                            :title="$program->name"
                            :href="route('programs.show', $program)"
                            kind="program"
                            title-as-badge
                            wrap-title
                        />
                    @endforeach
                </x-relationship-scroll-panel>
            @endif
        </div>

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
        <x-recent-activity-table
            :activities="$recentActivities"
            :view-all-url="route('activities.index', ['agreement_id' => $agreement->id])"
            view-all-label="View Activities"
            :log-activity-url="route('activities.create') . '?agreement_id=' . $agreement->id"
            :log-activity-enabled="$agreement->active"
            empty-message="No activities logged for this agreement yet."
        />
    </x-slot:activity>
</x-entity-show>
@endsection
