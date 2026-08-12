@extends('layouts.app')

@section('title', $state->name)

@section('content')
<x-page-header
    context="show"
    :title="$state->name"
    entity-type="State"
    :action-url="auth()->user()->isAdmin() ? route('states.edit', $state) : null"
/>

<x-entity-show>
    {{-- ── Summary ─────────────────────────────────────────────────────── --}}
    <x-slot:summary>
        <dl class="row mb-0">
            <dt class="col-5 text-muted fw-normal small">Name</dt>
            <dd class="col-7 mb-2 fw-semibold">{{ $state->name }}</dd>

            <dt class="col-5 text-muted fw-normal small">Organizations</dt>
            <dd class="col-7 mb-2">
                <span class="badge bg-primary-subtle text-primary-emphasis border rounded-pill">{{ $state->organizations->count() }}</span>
            </dd>

            <dt class="col-5 text-muted fw-normal small">Agreements</dt>
            <dd class="col-7 mb-2">
                <span class="badge bg-success-subtle text-success-emphasis border rounded-pill">{{ $state->agreements->count() }}</span>
            </dd>

            <dt class="col-5 text-muted fw-normal small">Added</dt>
            <dd class="col-7 mb-0 small text-muted">{{ $state->created_at->format('M d, Y') }}</dd>
        </dl>

        <hr>
        <h6 class="text-muted fw-normal small mb-2">Assigned Staff</h6>
        @forelse($staffMembers as $member)
            <div class="small py-1 {{ $loop->last ? '' : 'border-bottom' }}">
                <x-user-link :user="$member" class="fw-semibold" />
                @if($member->role)
                    <span class="text-muted">· {{ ucfirst($member->role) }}</span>
                @endif
                @if($member->email)
                    <div class="text-muted">{{ $member->email }}</div>
                @endif
            </div>
        @empty
            <p class="text-muted small mb-0">No staff linked to this state yet.</p>
        @endforelse
    </x-slot:summary>

    {{-- ── Relationships ───────────────────────────────────────────────── --}}
    <x-slot:relationships>
        <div class="row g-4 align-items-stretch">
            <div class="col-md-6 d-flex">
                <x-relationship-scroll-panel
                    title="Organizations"
                    kind="organization"
                    :count="$state->organizations->count()"
                    class="w-100"
                >
                    @forelse($state->organizations->sortBy('name') as $org)
                        <x-relationship-list-item
                            :href="route('organizations.show', $org)"
                            :title="$org->name"
                            kind="organization"
                            title-as-badge
                        />
                    @empty
                        <p class="text-muted small mb-0 py-2">No organizations in this state yet.</p>
                    @endforelse
                </x-relationship-scroll-panel>
            </div>

            <div class="col-md-6 d-flex">
                <x-relationship-scroll-panel
                    title="Agreements"
                    kind="agreement"
                    :count="$state->agreements->count()"
                    class="w-100"
                >
                    @forelse($state->agreements->sortBy('name') as $agreement)
                        <x-relationship-list-item
                            :href="$agreement->isLinkable() ? route('agreements.show', $agreement) : null"
                            :title="$agreement->name"
                            :subtitle="$agreement->abstract ? \Illuminate\Support\Str::limit($agreement->abstract, 150) : null"
                            kind="agreement"
                            title-as-badge
                            wrap-title
                        />
                    @empty
                        <p class="text-muted small mb-0 py-2">No agreements linked to this state yet.</p>
                    @endforelse
                </x-relationship-scroll-panel>
            </div>
        </div>
    </x-slot:relationships>

    {{-- ── Recent Activity ─────────────────────────────────────────────── --}}
    <x-slot:activity>
        <x-recent-activity-table :activities="$recentActivities" :view-all-url="route('activities.index', ['state_id' => $state->id])" empty-message="No activities logged for this state yet." />
    </x-slot:activity>
</x-entity-show>
@endsection
