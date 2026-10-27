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
    :entity-type="$user->accessLabel()"
    badge-kind="role"
    :active="$user->active"
    :action-url="$isProfile ? route('profile.edit') : (($canEditUser ?? false) ? route('admin.users.edit', $user) : null)"
    :action-label="$isProfile ? 'Edit profile' : 'Edit'"
>
    <x-slot:facts>
        <div><dt>Email</dt><dd class="app-fact-links"><a href="mailto:{{ $user->email }}">{{ $user->email }}</a></dd></div>
        @if(filled($user->po_number))
            <div><dt>PO number</dt><dd>{{ $user->po_number }}</dd></div>
        @endif
        @if($user->supervisor)
            <div><dt>Supervisor</dt><dd class="app-fact-links"><x-user-link :user="$user->supervisor" /></dd></div>
        @endif
    </x-slot:facts>
</x-page-header>

<x-entity-show mainCardTitle="Agreements" :activityFirst="true">
    {{-- ── Summary ─────────────────────────────────────────────────────── --}}
    <x-slot:summary>
        @if(!$user->active)
            <div class="alert alert-light border small mb-3 py-2">
                Membership was cleared when this user was deactivated. Activity history and contributions below are unchanged.
            </div>
        @endif
        @php
            $summaryProjects = $direct['projects']->merge($viaTeams['projects'])->unique('id')->sortBy('name');
            $summaryPrograms = $direct['programs']->merge($viaTeams['programs'])->unique('id')->sortBy('name');
        @endphp
        <dl class="app-record-metrics">
            <div><dt>Projects</dt><dd>{{ $summaryProjects->count() }}</dd></div>
            <div><dt>Programs</dt><dd>{{ $summaryPrograms->count() }}</dd></div>
            <div><dt>Teams</dt><dd>{{ $user->teams->count() }}</dd></div>
        </dl>
        <x-relationship-scroll-panel title="Projects" kind="project" :count="$summaryProjects->count()" collapsible collapsed class="mt-3">
            @foreach($summaryProjects as $record)
                <x-relationship-ledger-row
                    :title="$record->name"
                    :href="route('projects.show', $record)"
                    :context-badges="array_filter([
                        $direct['projects']->contains('id', $record->id) ? ['label' => 'Direct'] : null,
                        $viaTeams['projects']->contains('id', $record->id) ? ['label' => 'Via teams'] : null,
                    ])"
                />
            @endforeach
        </x-relationship-scroll-panel>
        <x-relationship-scroll-panel title="Programs" kind="program" :count="$summaryPrograms->count()" collapsible collapsed class="mt-3">
            @foreach($summaryPrograms as $record)
                <x-relationship-ledger-row
                    :title="$record->name"
                    :href="route('programs.show', $record)"
                    :context-badges="array_filter([
                        $direct['programs']->contains('id', $record->id) ? ['label' => 'Direct'] : null,
                        $viaTeams['programs']->contains('id', $record->id) ? ['label' => 'Via teams'] : null,
                    ])"
                />
            @endforeach
        </x-relationship-scroll-panel>
        <x-relationship-scroll-panel title="Teams" kind="team" :count="$user->teams->count()" collapsible collapsed class="mt-3">
            @foreach($user->teams->sortBy('name') as $team)
                <x-relationship-ledger-row :title="$team->name" :href="route('teams.show', $team)">
                    <x-slot:actions><x-status-badge :active="$team->active" /></x-slot:actions>
                </x-relationship-ledger-row>
            @endforeach
        </x-relationship-scroll-panel>
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
