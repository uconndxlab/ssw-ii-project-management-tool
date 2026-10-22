@extends('layouts.app')

@section('title', $organization->name)

@section('content')
<x-page-header
    context="show"
    :title="$organization->name"
    entity-type="Organization"
    :active="$organization->active"
    :action-url="auth()->user()->can('update', $organization) ? route('organizations.edit', $organization) : null"
>
    <x-slot:badges>
        @if(filled($organization->po_number))
            <span class="badge app-tag">PO number: {{ $organization->po_number }}</span>
        @endif
    </x-slot:badges>
</x-page-header>

<x-entity-show>
    {{-- ── Summary ─────────────────────────────────────────────────────── --}}
    <x-slot:summary>
        <p class="app-record-period">Year to date · {{ now()->year }}</p>
        <dl class="app-record-metrics">
            <div><dt>Activities</dt><dd>{{ $ytdTotals['activities'] }}</dd></div>
            <div><dt>Hours</dt><dd>{{ number_format($ytdTotals['hours'], 1) }}</dd></div>
            <div><dt>Participants</dt><dd>{{ number_format($ytdTotals['participants']) }}</dd></div>
        </dl>

        <x-relationship-scroll-panel
            title="Contacts"
            kind="user"
            :count="$organization->contacts->count()"
            height="220px"
            collapsible
            :collapsed="true"
            class="mt-3">
            <div class="d-flex flex-column gap-2">
                @forelse($organization->contacts as $contact)
                    <x-organization-contact-row :contact="$contact" />
                @empty
                    <p class="text-muted small mb-0 py-1">No contacts added.</p>
                @endforelse
            </div>
        </x-relationship-scroll-panel>

        <x-relationship-scroll-panel
            title="States"
            kind="state"
            :count="$organization->states->count()"
            height="220px"
            collapsible
            :collapsed="true"
            class="mt-3">
            @forelse($organization->states->sortBy('name') as $state)
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

        <x-relationship-scroll-panel title="Associated users" kind="user" :count="$organization->users->count()" collapsible collapsed class="mt-3">
            @foreach($organization->users->sortBy('name') as $user)
                <x-relationship-ledger-row :title="$user->name" :href="\App\Support\UserProfileLink::route($user)" :subtitle="$user->email" />
            @endforeach
        </x-relationship-scroll-panel>
    </x-slot:summary>

    {{-- ── Relationships ───────────────────────────────────────────────── --}}
    <x-slot:relationships>
        <div class="row g-4 align-items-stretch">
            <div class="col-md-6 d-flex">
                <x-relationship-scroll-panel
                    title="Linked agreements"
                    kind="agreement"
                    :count="$agreements->count()"
                    collapsible
                    class="w-100"
                >
                    @can('create', App\Models\Agreement::class)
                        <x-slot:headerActions>
                            <a href="{{ route('agreements.create') }}" class="btn btn-sm btn-outline-primary">+ New</a>
                        </x-slot:headerActions>
                    @endcan

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
                        <p class="text-muted small mb-0 py-2">No agreements yet.</p>
                    @endforelse
                </x-relationship-scroll-panel>
            </div>

            <div class="col-md-6 d-flex">
                <x-relationship-scroll-panel
                    title="Assigned Staff"
                    kind="user"
                    :count="$teamMembers->count()"
                    collapsible
                    class="w-100"
                >
                    @forelse($teamMembers as $member)
                        <x-relationship-ledger-row
                            :title="$member->name"
                            :href="\App\Support\UserProfileLink::route($member)"
                            kind="user"
                            :subtitle="$member->email"
                        >
                            @if($member->via_agreements?->isNotEmpty())
                                <x-slot:footer>
                                    <span>Via {{ $member->via_agreements->join(', ') }}</span>
                                </x-slot:footer>
                            @endif
                        </x-relationship-ledger-row>
                    @empty
                        <p class="text-muted small mb-0 py-2">No staff assigned.</p>
                    @endforelse
                </x-relationship-scroll-panel>
            </div>
        </div>

        {{-- YTD Breakdown --}}
        @if($contactFamilyBreakdown->isNotEmpty())
        <hr>
        <h6 class="fw-semibold mb-3">YTD Activity Breakdown</h6>
        <div class="table-responsive">
            <table class="table table-sm mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Activity Family</th>
                        <th class="text-end">Activities</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($contactFamilyBreakdown as $family => $count)
                    <tr>
                        <td><span class="badge app-tag">{{ $family }}</span></td>
                        <td class="text-end fw-semibold">{{ $count }}</td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        @endif
    </x-slot:relationships>

    {{-- ── Recent Activity ─────────────────────────────────────────────── --}}
    <x-slot:activity>
        <x-recent-activity-table
            :activities="$recentActivities"
            :view-all-url="route('activities.index', ['organization_id' => $organization->id])"
        />
    </x-slot:activity>
</x-entity-show>
@endsection
