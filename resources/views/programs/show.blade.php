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
        <dl class="row mb-0">
            <dt class="col-5 text-muted fw-normal small">Status</dt>
            <dd class="col-7 mb-2">
                @if($program->active)
                    <span class="badge bg-success">Active</span>
                @else
                    <span class="badge bg-secondary">Inactive</span>
                @endif
            </dd>

            <dt class="col-5 text-muted fw-normal small">Projects</dt>
            <dd class="col-7 mb-2">
                @if($program->projects->isNotEmpty())
                    <div class="d-flex flex-wrap gap-1">
                        @foreach($program->projects->sortBy('name') as $project)
                            <x-entity-relation-badge kind="project" :href="route('projects.show', $project)">{{ $project->name }}</x-entity-relation-badge>
                        @endforeach
                    </div>
                @else
                    <span class="text-muted">—</span>
                @endif
            </dd>

            <dt class="col-5 text-muted fw-normal small">Agreements</dt>
            <dd class="col-7 mb-2">
                <x-entity-count-badge kind="agreement" :count="$agreements->count()" />
            </dd>

            <dt class="col-5 text-muted fw-normal small">Organizations</dt>
            <dd class="col-7 mb-2">
                <x-entity-count-badge kind="organization" :count="$organizations->count()" />
            </dd>

            <dt class="col-5 text-muted fw-normal small">Activities</dt>
            <dd class="col-7 mb-2">
                <x-entity-count-badge kind="activity" :count="$activityCount" />
            </dd>

            <dt class="col-5 text-muted fw-normal small">Added</dt>
            <dd class="col-7 mb-0 small text-muted">{{ $program->created_at->format('M d, Y') }}</dd>
        </dl>

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
