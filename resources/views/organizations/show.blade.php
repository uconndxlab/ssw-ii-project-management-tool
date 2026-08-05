@extends('layouts.app')

@section('title', $organization->name)

@section('content')
<x-entity-show
    title="{{ $organization->name }}"
    type="Organization"
    typeBadgeClass="bg-primary"
    editRoute="{{ auth()->user()->isAdmin() ? route('organizations.edit', $organization) : null }}"
    backRoute="{{ route('organizations.index') }}"
    backLabel="All Organizations"
>
    {{-- ── Summary ─────────────────────────────────────────────────────── --}}
    <x-slot:summary>
        <dl class="row mb-0">
            <dt class="col-5 text-muted fw-normal small">Name</dt>
            <dd class="col-7 mb-2 fw-semibold">{{ $organization->name }}</dd>

            <dt class="col-5 text-muted fw-normal small">Status</dt>
            <dd class="col-7 mb-2">
                @if($organization->active)
                    <span class="badge bg-success">Active</span>
                @else
                    <span class="badge bg-secondary">Inactive</span>
                @endif
            </dd>

            <dt class="col-5 text-muted fw-normal small">KFS Number</dt>
            <dd class="col-7 mb-2">{{ $organization->kfs_number ?: '—' }}</dd>

            <dt class="col-5 text-muted fw-normal small">State(s)</dt>
            <dd class="col-7 mb-2">
                <div class="d-flex flex-wrap gap-1">
                    @forelse($organization->states as $state)
                        <x-entity-relation-badge kind="state" :href="route('states.show', $state)">{{ $state->name }}</x-entity-relation-badge>
                    @empty
                        <span class="text-muted small">—</span>
                    @endforelse
                </div>
            </dd>

            <dt class="col-5 text-muted fw-normal small">Associated Users</dt>
            <dd class="col-7 mb-2">
                @forelse($organization->users as $user)
                    <x-user-link :user="$user" class="badge bg-primary-subtle text-primary-emphasis border me-1 mb-1" />
                @empty
                    <span class="text-muted">—</span>
                @endforelse
            </dd>

            <dt class="col-5 text-muted fw-normal small">Agreements</dt>
            <dd class="col-7 mb-2">
                <span class="badge bg-success-subtle text-success-emphasis border rounded-pill">{{ $agreements->count() }}</span>
            </dd>

            <dt class="col-5 text-muted fw-normal small">Staff</dt>
            <dd class="col-7 mb-2">
                <span class="badge bg-primary rounded-pill">{{ $teamMembers->count() }}</span>
            </dd>
        </dl>

        <hr>

        <h6 class="text-muted fw-normal small mb-2">Year-to-Date ({{ now()->year }})</h6>
        <dl class="row mb-0">
            <dt class="col-7 text-muted fw-normal small">Activities</dt>
            <dd class="col-5 mb-1 fw-semibold text-end">{{ $ytdTotals['activities'] }}</dd>

            <dt class="col-7 text-muted fw-normal small">Hours</dt>
            <dd class="col-5 mb-1 fw-semibold text-end">{{ number_format($ytdTotals['hours'], 1) }}</dd>

            <dt class="col-7 text-muted fw-normal small">Participants</dt>
            <dd class="col-5 mb-0 fw-semibold text-end">{{ number_format($ytdTotals['participants']) }}</dd>
        </dl>
    </x-slot:summary>

    {{-- ── Relationships ───────────────────────────────────────────────── --}}
    <x-slot:relationships>
        <div class="row g-4 align-items-stretch">
            <div class="col-md-7 d-flex flex-column">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h6 class="fw-semibold mb-0">
                        Agreements
                        <span class="badge bg-success-subtle text-success-emphasis border rounded-pill ms-1">{{ $agreements->count() }}</span>
                    </h6>
                    @if(auth()->user()->isAdmin())
                        <a href="{{ route('agreements.create') }}" class="btn btn-sm btn-outline-success">+ New</a>
                    @endif
                </div>
                <x-relationship-scroll-panel
                    title="Linked agreements"
                    :count="$agreements->count()"
                    header-badge-class="bg-success-subtle text-success-emphasis border"
                    class="flex-grow-1"
                >
                    @forelse($agreements as $agreement)
                        <x-relationship-list-item
                            :href="$agreement->isLinkable() ? route('agreements.show', $agreement) : null"
                            :title="$agreement->name"
                            :subtitle="$agreement->abstract ? \Illuminate\Support\Str::limit($agreement->abstract, 150) : null"
                        />
                    @empty
                        <p class="text-muted small mb-0 py-2">No agreements yet.</p>
                    @endforelse
                </x-relationship-scroll-panel>
            </div>

            {{-- Team Members --}}
            <div class="col-md-5">
                <h6 class="fw-semibold mb-3">
                    Assigned Staff
                    <span class="badge bg-primary rounded-pill ms-1">{{ $teamMembers->count() }}</span>
                </h6>
                @forelse($teamMembers as $member)
                    <div class="py-2 border-bottom">
                        <div class="d-flex justify-content-between align-items-start">
                            <x-user-link :user="$member" class="text-decoration-none fw-semibold small" />
                            @if($member->role)
                                <x-category-badge kind="role" class="ms-2">{{ ucfirst($member->role) }}</x-category-badge>
                            @endif
                        </div>
                        @if($member->email)
                            <div class="small text-muted">{{ $member->email }}</div>
                        @endif
                        @if($member->via_agreements?->isNotEmpty())
                            <div class="mt-1 d-flex flex-wrap gap-1">
                                @foreach($member->via_agreements as $agreementName)
                                    <span class="badge bg-success-subtle text-success-emphasis" style="font-size:.7rem;">{{ $agreementName }}</span>
                                @endforeach
                            </div>
                        @endif
                    </div>
                @empty
                    <p class="text-muted small mb-0">No staff assigned.</p>
                @endforelse
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
                        <th>Contact Family</th>
                        <th class="text-end">Activities</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($contactFamilyBreakdown as $family => $count)
                    <tr>
                        <td><span class="badge bg-primary">{{ $family }}</span></td>
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
