@extends('layouts.app')

@section('title', $state->name)

@section('content')
<x-page-header
    context="show"
    :title="$state->name"
    entity-type="State"
    :action-url="auth()->user()->can('update', $state) ? route('states.edit', $state) : null"
/>

<x-entity-show>
    {{-- ── Summary ─────────────────────────────────────────────────────── --}}
    <x-slot:summary>
        <dl class="app-record-metrics">
            <div><dt>Organizations</dt><dd>{{ $state->organizations->count() }}</dd></div>
            <div><dt>Agreements</dt><dd>{{ $state->agreements->count() }}</dd></div>
        </dl>

        <x-relationship-scroll-panel title="Assigned staff" kind="user" :count="$staffMembers->count()" collapsible collapsed class="mt-3">
            @foreach($staffMembers as $member)
                <x-staff-member-row :user="$member" :role="$member->access_profile ? $member->accessLabel() : null">
                    @if($member->email)
                        <x-slot:after><span>{{ $member->email }}</span></x-slot:after>
                    @endif
                </x-staff-member-row>
            @endforeach
        </x-relationship-scroll-panel>
    </x-slot:summary>

    {{-- ── Relationships ───────────────────────────────────────────────── --}}
    <x-slot:relationships>
        <div class="row g-4 align-items-stretch">
            <div class="col-md-6 d-flex">
                <x-relationship-scroll-panel
                    title="Organizations"
                    kind="organization"
                    :count="$state->organizations->count()"
                    collapsible
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
                    collapsible
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
