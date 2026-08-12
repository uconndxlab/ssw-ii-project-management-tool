@extends('layouts.app')

@section('title', $project->name)

@section('content')
<x-page-header
    context="show"
    :title="$project->name"
    entity-type="Project"
    :active="$project->active"
    :action-url="route('projects.edit', $project)"
/>

<x-entity-show>
    {{-- ── Summary ─────────────────────────────────────────────────────── --}}
    <x-slot:summary>
        <dl class="row mb-0">
            <dt class="col-5 text-muted fw-normal small">Status</dt>
            <dd class="col-7 mb-2">
                @if($project->active)
                    <span class="badge bg-success">Active</span>
                @else
                    <span class="badge bg-secondary">Inactive</span>
                @endif
            </dd>

            <dt class="col-5 text-muted fw-normal small">Programs</dt>
            <dd class="col-7 mb-2">
                <span class="badge bg-warning-subtle text-warning-emphasis border rounded-pill">{{ $project->programs->count() }}</span>
            </dd>

            <dt class="col-5 text-muted fw-normal small">Organizations</dt>
            <dd class="col-7 mb-2">
                <span class="badge bg-primary-subtle text-primary-emphasis border rounded-pill">{{ $project->organizations->count() }}</span>
            </dd>

            <dt class="col-5 text-muted fw-normal small">Agreements</dt>
            <dd class="col-7 mb-2">
                <span class="badge bg-success-subtle text-success-emphasis border rounded-pill">{{ $agreements->count() }}</span>
            </dd>

            <dt class="col-5 text-muted fw-normal small">States</dt>
            <dd class="col-7 mb-2">
                <div class="d-flex flex-wrap gap-1">
                    @forelse($states as $state)
                        <x-entity-relation-badge kind="state" :href="route('states.show', $state)">{{ $state->name }}</x-entity-relation-badge>
                    @empty
                        <span class="text-muted small">—</span>
                    @endforelse
                </div>
            </dd>

            <dt class="col-5 text-muted fw-normal small">Created</dt>
            <dd class="col-7 mb-2 small text-muted">{{ $project->created_at->format('M d, Y') }}</dd>

            <dt class="col-5 text-muted fw-normal small">Updated</dt>
            <dd class="col-7 mb-0 small text-muted">{{ $project->updated_at->format('M d, Y') }}</dd>
        </dl>

        @if($project->description)
            <hr>
            <h6 class="text-muted fw-normal small mb-1">Description</h6>
            <p class="mb-0 small">{{ $project->description }}</p>
        @endif
    </x-slot:summary>

    {{-- ── Relationships ───────────────────────────────────────────────── --}}
    <x-slot:relationships>
        <div class="row g-4">

            <div class="col-12">
                <x-relationship-scroll-panel
                    title="Programs"
                    kind="program"
                    :count="$project->programs->count()"
                >
                    <x-slot:headerActions>
                        <a href="{{ route('programs.create') }}" class="btn btn-sm btn-outline-primary">+ Add Program</a>
                    </x-slot:headerActions>

                    @forelse($project->programs->sortBy('name') as $program)
                        <x-relationship-ledger-row
                            :title="$program->name"
                            :href="route('programs.show', $program)"
                            kind="program"
                            title-as-badge
                            wrap-title
                            :meta-lines="[number_format($program->activities->count()) . ' activities']">
                            <x-slot:actions>
                                <div class="d-flex align-items-center gap-2 flex-shrink-0">
                                    <x-status-badge :active="$program->active" />
                                    <a href="{{ route('programs.edit', $program) }}"
                                       class="btn btn-sm btn-outline-secondary"
                                       data-bs-toggle="tooltip"
                                       data-bs-placement="top"
                                       data-bs-title="Edit program"
                                       aria-label="Edit {{ $program->name }}">
                                        <i class="bi bi-pencil-square"></i>
                                    </a>
                                </div>
                            </x-slot:actions>
                        </x-relationship-ledger-row>
                    @empty
                        <p class="text-muted small mb-0 py-2">No programs in this project yet.</p>
                    @endforelse
                </x-relationship-scroll-panel>
            </div>

            <div class="col-12">
                <div class="row g-4 align-items-stretch">
                    <div class="col-md-6 d-flex">
                        <x-relationship-scroll-panel
                            title="Organizations"
                            kind="organization"
                            :count="$project->organizations->count()"
                            class="w-100"
                        >
                            @forelse($project->organizations->sortBy('name') as $org)
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

                    <div class="col-md-6 d-flex">
                        <x-relationship-scroll-panel
                            title="Agreements"
                            kind="agreement"
                            :count="$agreements->count()"
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
                                <p class="text-muted small mb-0 py-2">No agreements linked via programs.</p>
                            @endforelse
                        </x-relationship-scroll-panel>
                    </div>
                </div>
            </div>

        </div>
    </x-slot:relationships>

    {{-- ── Recent Activity ─────────────────────────────────────────────── --}}
    <x-slot:activity>
        <x-recent-activity-table :activities="$recentActivities" :view-all-url="route('activities.index')" empty-message="No activities logged for this project yet." />
    </x-slot:activity>
</x-entity-show>
@endsection
