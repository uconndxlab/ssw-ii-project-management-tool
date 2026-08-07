@extends('layouts.app')

@section('title', $user->name)

@section('content')
@php
    $isProfile = $isProfile ?? false;
    $direct = $scopeBySource['direct'];
    $viaTeams = $scopeBySource['viaTeams'];
@endphp

<x-page-header
    context="show"
    :title="$user->name"
    :entity-type="ucfirst($user->role)"
    badge-kind="role"
    :active="$user->active"
    :action-url="$isProfile ? route('profile.edit') : route('admin.users.edit', $user)"
    :action-label="$isProfile ? 'Edit profile' : 'Edit'"
/>

<x-entity-show mainCardTitle="Agreements" :activityFirst="true">
    {{-- ── Summary ─────────────────────────────────────────────────────── --}}
    <x-slot:summary>
        @if(!$user->active)
            <div class="alert alert-light border small mb-3 py-2">
                Membership was cleared when this user was deactivated. Activity history and contributions below are unchanged.
            </div>
        @endif
        <dl class="row mb-0" style="min-width: 0;">
            <dt class="col-5 text-muted fw-normal small">Status</dt>
            <dd class="col-7 mb-2">
                <x-status-badge :active="$user->active" />
            </dd>

            <dt class="col-5 text-muted fw-normal small">Name</dt>
            <dd class="col-7 mb-2 fw-semibold">{{ $user->name }}</dd>

            <dt class="col-5 text-muted fw-normal small">Email</dt>
            <dd class="col-7 mb-2">
                <a href="mailto:{{ $user->email }}" class="text-decoration-none small">
                    {{ $user->email }}
                </a>
            </dd>

            <dt class="col-5 text-muted fw-normal small">PO Number</dt>
            <dd class="col-7 mb-2">{{ $user->po_number ?: '—' }}</dd>

            <dt class="col-5 text-muted fw-normal small">Supervisor</dt>
            <dd class="col-7 mb-2">
                @if($user->supervisor)
                    <x-user-link :user="$user->supervisor" />
                @else
                    <span class="text-muted">—</span>
                @endif
            </dd>

            <dt class="col-5 text-muted fw-normal small">Projects</dt>
            <dd class="col-7 mb-2">
                <div class="d-flex flex-wrap gap-1">
                    @forelse($direct['projects'] as $project)
                        <x-entity-relation-badge kind="project" :href="route('projects.show', $project)">{{ $project->name }}</x-entity-relation-badge>
                    @empty
                        @if($viaTeams['projects']->isEmpty())
                            <span class="text-muted small">None</span>
                        @endif
                    @endforelse
                </div>
                @if($viaTeams['projects']->isNotEmpty())
                    <div class="small text-muted mt-2 mb-1">Via teams</div>
                    <div class="d-flex flex-wrap gap-1">
                        @foreach($viaTeams['projects'] as $project)
                            <x-entity-relation-badge kind="project" :href="route('projects.show', $project)">{{ $project->name }}</x-entity-relation-badge>
                        @endforeach
                    </div>
                @endif
            </dd>

            <dt class="col-5 text-muted fw-normal small">Programs</dt>
            <dd class="col-7 mb-2">
                <div class="d-flex flex-wrap gap-1">
                    @forelse($direct['programs'] as $program)
                        <x-entity-relation-badge kind="program" :href="route('programs.show', $program)">{{ $program->name }}</x-entity-relation-badge>
                    @empty
                        @if($viaTeams['programs']->isEmpty())
                            <span class="text-muted small">None</span>
                        @endif
                    @endforelse
                </div>
                @if($viaTeams['programs']->isNotEmpty())
                    <div class="small text-muted mt-2 mb-1">Via teams</div>
                    <div class="d-flex flex-wrap gap-1">
                        @foreach($viaTeams['programs'] as $program)
                            <x-entity-relation-badge kind="program" :href="route('programs.show', $program)">{{ $program->name }}</x-entity-relation-badge>
                        @endforeach
                    </div>
                @endif
            </dd>

            <dt class="col-5 text-muted fw-normal small">Teams</dt>
            <dd class="col-7 mb-2">
                <div class="d-flex flex-wrap gap-1">
                    @forelse($user->teams->sortBy('name') as $team)
                        <x-entity-relation-badge
                            kind="team"
                            :href="route('teams.show', $team)"
                            :title="$team->active ? 'Active' : 'Inactive'"
                        >{{ $team->name }}</x-entity-relation-badge>
                    @empty
                        <span class="text-muted small">None</span>
                    @endforelse
                </div>
            </dd>

            <dt class="col-5 text-muted fw-normal small">Joined</dt>
            <dd class="col-7 mb-0 small text-muted">{{ $user->created_at->format('M d, Y') }}</dd>
        </dl>
    </x-slot:summary>

    {{-- ── Agreements & deliverables ───────────────────────────────────── --}}
    <x-slot:relationships>
        @include('admin.users.partials.agreement-reports', ['agreementReports' => $agreementReports])
    </x-slot:relationships>

    {{-- ── Recent Activity ─────────────────────────────────────────────── --}}
    <x-slot:activity>
        <x-recent-activity-table :activities="$recentActivities" :view-all-url="route('activities.index')" empty-message="No activities logged by this user yet." />
    </x-slot:activity>
</x-entity-show>
@endsection
