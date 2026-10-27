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
    $userProfileRoute = fn ($user) => \App\Support\UserProfileLink::route($user);
@endphp

<x-page-header
    context="show"
    :title="$agreement->name"
    entity-type="Agreement"
    :active="$agreement->active"
    :action-url="auth()->user()->can('update', $agreement) ? route('agreements.edit', $agreement) : null"
>
    <x-slot:facts>
        <div><dt>Start date</dt><dd>{{ $agreement->start_date?->format('M d, Y') ?? '—' }}</dd></div>
        <div><dt>End date</dt><dd>{{ $agreement->end_date?->format('M d, Y') ?? '—' }}</dd></div>
        <div>
            <dt>Principal investigators</dt>
            <dd class="app-fact-links">
                @forelse($agreement->principalInvestigators as $investigator)
                    <x-user-link :user="$investigator" />
                @empty
                    Not assigned
                @endforelse
            </dd>
        </div>
    </x-slot:facts>
    <x-slot:more>
        @can('duplicate', $agreement)
            <li>
                <button type="submit"
                        form="agreement-duplicate-form"
                        class="dropdown-item"
                        data-confirm
                        data-confirm-title="Duplicate agreement?"
                        data-confirm-record="{{ $agreement->name }}"
                        data-confirm-message="Activities and logged progress will not be copied. Review the new copy before saving."
                        data-confirm-action="Duplicate agreement"
                        data-confirm-tone="primary">
                    <i class="bi bi-files me-2" aria-hidden="true"></i>Duplicate
                </button>
            </li>
        @endcan
    </x-slot:more>
</x-page-header>

@can('duplicate', $agreement)
    <form id="agreement-duplicate-form" method="POST" action="{{ route('agreements.duplicate', $agreement) }}" class="d-none">
        @csrf
    </form>
@endcan

<x-entity-show mainCardTitle="Deliverables" :activityFirst="true">
    <x-slot:activityHeaderMeta>
        <x-entity-count-badge kind="activity" :count="$lifetimeTotals['activities']" />
        <span class="text-muted small">Lifetime</span>
    </x-slot:activityHeaderMeta>

    {{-- ── Summary ─────────────────────────────────────────────────────── --}}
    <x-slot:summary>
        <p class="app-record-period">Year to date · {{ now()->year }}</p>
        <dl class="app-record-metrics">
            <div><dt>Activities</dt><dd>{{ $ytdTotals['activities'] }}</dd></div>
        </dl>

        @if($agreement->extension_start_date || $agreement->extension_end_date)
            <dl class="row mb-0 mt-3">
                <dt class="col-5 text-muted fw-normal small">Extension Start</dt>
                <dd class="col-7 mb-2 small">{{ $agreement->extension_start_date?->format('M d, Y') ?? '—' }}</dd>

                <dt class="col-5 text-muted fw-normal small">Extension End</dt>
                <dd class="col-7 mb-2 small">{{ $agreement->extension_end_date?->format('M d, Y') ?? '—' }}</dd>
            </dl>
        @endif

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
            <h6 class="app-subhead mb-2">Abstract</h6>
            <p class="small mb-0">{{ $agreement->abstract }}</p>
        @endif

        <hr>
        <h6 class="app-subhead mb-3">Assigned Staff</h6>
        @if($hasAnyUsers)
            <div class="d-grid gap-2">
                @foreach($agreement->teams as $team)
                    @php
                        $teamOnlyUsers = $team->users->whereNotIn('id', $directUserIds);
                    @endphp
                    @if($teamOnlyUsers->isNotEmpty())
                        <x-relationship-scroll-panel
                            :title="$team->name"
                            :title-href="route('teams.show', $team)"
                            kind="team"
                            :count="$teamOnlyUsers->count()"
                            collapsible
                            :collapsed="true"
                            height="220px">
                            @foreach($teamOnlyUsers as $user)
                                <x-relationship-ledger-row
                                    :title="$user->name"
                                    :href="$userProfileRoute($user)"
                                    kind="user"
                                    :context-badges="array_values(array_filter([
                                        filled($user->access_profile) ? ['label' => $user->accessLabel(), 'kind' => 'role'] : null,
                                        $agreement->principalInvestigators->contains('id', $user->id) ? ['label' => 'PI', 'kind' => 'pi'] : null,
                                    ]))"
                                />
                            @endforeach
                        </x-relationship-scroll-panel>
                    @endif
                @endforeach

                @if($directUsers->isNotEmpty())
                    <x-relationship-scroll-panel
                        title="Additional Users"
                        kind="user"
                        :count="$directUsers->count()"
                        collapsible
                        :collapsed="true"
                        height="220px">
                        @foreach($directUsers as $user)
                            <x-relationship-ledger-row
                                :title="$user->name"
                                :href="$userProfileRoute($user)"
                                kind="user"
                                :context-badges="array_values(array_filter([
                                    filled($user->access_profile) ? ['label' => $user->accessLabel(), 'kind' => 'role'] : null,
                                    !empty($user->is_principal_investigator) ? ['label' => 'PI', 'kind' => 'pi'] : null,
                                ]))">
                                <x-slot:footer>
                                    @if(!empty($user->also_in_teams))
                                        <div class="d-flex flex-wrap gap-1">
                                            @foreach($agreement->teams->filter(fn ($team) => $team->users->contains('id', $user->id)) as $team)
                                                <x-entity-relation-badge kind="team" :href="route('teams.show', $team)">
                                                    {{ $team->name }}
                                                </x-entity-relation-badge>
                                            @endforeach
                                        </div>
                                    @endif
                                </x-slot:footer>
                            </x-relationship-ledger-row>
                        @endforeach
                    </x-relationship-scroll-panel>
                @endif
            </div>
        @else
            <p class="text-muted small mb-0">No staff assigned.</p>
        @endif

        @if($agreement->attachments->isNotEmpty())
            <x-relationship-scroll-panel title="Attachments" :count="$agreement->attachments->count()" collapsible collapsed class="mt-3">
                @foreach($agreement->attachments as $attachment)
                    <x-relationship-ledger-row :title="$attachment->filename">
                        <x-slot:actions>
                            <a href="{{ $attachment->download_url }}" class="btn btn-sm btn-outline-primary text-nowrap" target="_blank">View / Download</a>
                        </x-slot:actions>
                    </x-relationship-ledger-row>
                @endforeach
            </x-relationship-scroll-panel>
        @endif
    </x-slot:summary>

    <x-slot:beforeMain>
        <section class="card shadow-sm entity-show-card" aria-labelledby="certificate-assignments-heading">
            <div class="card-header py-2 px-3 d-flex flex-wrap align-items-center gap-2">
                <h2 class="h5 mb-0" id="certificate-assignments-heading">Certificate assignments</h2>
                <span class="text-muted small">{{ $certificationRosterDisplay->count() }} {{ \Illuminate\Support\Str::plural('candidate', $certificationRosterDisplay->count()) }}</span>
                @can('update', $agreement)
                    <a href="{{ route('agreements.edit', $agreement) }}#certification-candidates" class="btn btn-sm btn-outline-primary ms-auto">Manage assignments</a>
                @endcan
            </div>
            <div class="card-body">
                @forelse($certificationRosterDisplay as $entry)
                    @php
                        $person = $entry['roster']->candidate;
                        $assignments = $entry['enrollments'];
                    @endphp
                    @if($person)
                        <div class="py-3 {{ $loop->first ? 'pt-0' : 'border-top' }} {{ $loop->last ? 'pb-0' : '' }}">
                            <div class="d-flex flex-wrap align-items-start gap-2 mb-2">
                                <div class="min-w-0">
                                    <h3 class="h6 mb-0 text-break">{{ $person->name }}</h3>
                                    @if($person->email)<div class="small text-muted text-break">{{ $person->email }}</div>@endif
                                </div>
                                @can('update', $agreement)
                                    <a class="small ms-auto" href="{{ route('agreements.edit', $agreement) }}#certification-candidate-{{ $person->id }}">{{ $assignments->isEmpty() ? 'Assign a certificate' : 'Edit assignments' }}</a>
                                @endcan
                            </div>
                            @forelse($assignments as $enrollment)
                                <div class="d-flex flex-wrap align-items-start justify-content-between gap-2 py-2">
                                    <div class="min-w-0">
                                        <div class="fw-medium text-break">{{ $enrollment->certificate?->name ?? 'Certificate' }}</div>
                                        <div class="small text-muted">
                                            {{ $enrollment->supervisingCoach ? 'Coach: '.$enrollment->supervisingCoach->name : 'No coach selected' }}
                                            @if($enrollment->enrolled_at) · Assigned {{ $enrollment->enrolled_at->format('M j, Y') }}@endif
                                        </div>
                                    </div>
                                    <div class="d-flex flex-wrap align-items-center gap-2">@php $lifecycleBadge = $enrollment->lifecycleBadge(); @endphp<span class="{{ $lifecycleBadge['class'] }}">{{ $lifecycleBadge['label'] }}</span>
                                        <a href="{{ route('certificate-enrollments.show', ['certificateEnrollment' => $enrollment, 'agreement_id' => $agreement->id]) }}" class="small">Progress</a>
                                    </div>
                                </div>
                            @empty
                                <p class="text-muted small mb-0">No certificates assigned.</p>
                            @endforelse
                        </div>
                    @endif
                @empty
                    <p class="text-muted mb-0">Add a candidate to assign certificates on this agreement.</p>
                @endforelse
            </div>
        </section>
    </x-slot:beforeMain>

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
            log-activity-disabled-reason="This agreement is inactive."
            hide-agreements
            empty-message="No activities logged for this agreement yet."
        />
    </x-slot:activity>
</x-entity-show>
@endsection
