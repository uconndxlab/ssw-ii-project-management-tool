@extends('layouts.app')

@section('title', $program->name)

@section('content')
<x-page-header
    context="show"
    :title="$program->name"
    entity-type="Program"
    :active="$program->active"
    :action-url="auth()->user()->can('update', $program) ? route('programs.edit', $program) : null"
/>

<x-entity-show>
    {{-- ── Summary ─────────────────────────────────────────────────────── --}}
    <x-slot:summary>
        <dl class="app-record-metrics">
            <div><dt>Activities</dt><dd>{{ $activityCount }}</dd></div>
            <div><dt>Agreements</dt><dd>{{ $agreements->count() }}</dd></div>
            <div><dt>Organizations</dt><dd>{{ $organizations->count() }}</dd></div>
        </dl>

        <x-relationship-scroll-panel title="Projects" kind="project" :count="$program->projects->count()" collapsible collapsed class="mt-3">
            @foreach($program->projects->sortBy('name') as $project)
                <x-relationship-ledger-row :title="$project->name" :href="route('projects.show', $project)" />
            @endforeach
        </x-relationship-scroll-panel>

        <x-relationship-scroll-panel
            title="States"
            kind="state"
            :count="$states->count()"
            height="220px"
            collapsible
            :collapsed="true"
            class="mt-3">
            @forelse($states->sortBy('name') as $state)
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

        @if($program->description)
            <hr>
            <h6 class="text-muted fw-normal small mb-1">Description</h6>
            <p class="mb-0 small">{{ $program->description }}</p>
        @endif
    </x-slot:summary>

    {{-- ── Relationships ───────────────────────────────────────────────── --}}
    <x-slot:relationships>
        <div class="row g-4 align-items-stretch">
            <div class="col-md-6 d-flex">
                <x-relationship-scroll-panel
                    title="Agreements"
                    kind="agreement"
                    :count="$agreements->count()"
                    collapsible
                    class="w-100"
                >
                    @forelse($agreements as $agreement)
                        <x-relationship-list-item
                            :href="$agreement->isLinkable() ? route('agreements.show', $agreement) : null"
                            :title="$agreement->name"
                            :subtitle="$agreement->abstract ? \Illuminate\Support\Str::limit($agreement->abstract, 150) : null"
                            kind="agreement"
                            title-as-badge
                            wrap-title
                        />
                    @empty
                        <p class="text-muted small mb-0 py-2">No agreements linked to this program yet.</p>
                    @endforelse
                </x-relationship-scroll-panel>
            </div>

            <div class="col-md-6 d-flex">
                <x-relationship-scroll-panel
                    title="Organizations"
                    kind="organization"
                    :count="$organizations->count()"
                    collapsible
                    class="w-100"
                >
                    @forelse($organizations as $org)
                        <x-relationship-list-item
                            :href="route('organizations.show', $org)"
                            :title="$org->name"
                            kind="organization"
                            title-as-badge
                        />
                    @empty
                        <p class="text-muted small mb-0 py-2">No organizations linked.</p>
                    @endforelse
                </x-relationship-scroll-panel>
            </div>
        </div>
    </x-slot:relationships>

    {{-- ── Recent Activity ─────────────────────────────────────────────── --}}
    <x-slot:activity>
        <x-recent-activity-table
            :activities="$recentActivities"
            :view-all-url="route('activities.index')"
            empty-message="No activities logged for this program yet."
        />
    </x-slot:activity>
</x-entity-show>
@endsection
