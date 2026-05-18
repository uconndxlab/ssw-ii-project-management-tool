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

            <dt class="col-5 text-muted fw-normal small">State(s)</dt>
            <dd class="col-7 mb-2">
                @forelse($organization->states as $state)
                    <a href="{{ route('states.show', $state) }}" class="badge bg-info text-dark text-decoration-none me-1">
                        {{ $state->name }}
                    </a>
                @empty
                    <span class="text-muted">—</span>
                @endforelse
            </dd>

            <dt class="col-5 text-muted fw-normal small">Agreements</dt>
            <dd class="col-7 mb-2">
                <span class="badge bg-success rounded-pill">{{ $agreements->count() }}</span>
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
        <div class="row g-4">
            {{-- Agreements --}}
            <div class="col-md-7">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h6 class="fw-semibold mb-0">
                        Agreements
                        <span class="badge bg-success rounded-pill ms-1">{{ $agreements->count() }}</span>
                    </h6>
                    @if(auth()->user()->isAdmin())
                        <a href="{{ route('agreements.create') }}" class="btn btn-sm btn-outline-success">+ New</a>
                    @endif
                </div>
                @forelse($agreements as $agreement)
                    <div class="py-2 border-bottom">
                        <a href="{{ route('agreements.show', $agreement) }}" class="text-decoration-none fw-semibold d-block">
                            {{ $agreement->name }}
                        </a>
                        <div class="d-flex gap-2 mt-1 flex-wrap">
                            @foreach($agreement->states as $state)
                                <a href="{{ route('states.show', $state) }}" class="badge bg-info text-dark text-decoration-none">
                                    {{ $state->name }}
                                </a>
                            @endforeach
                            <small class="text-muted">{{ $agreement->users->count() }} staff</small>
                        </div>
                    </div>
                @empty
                    <p class="text-muted small mb-0">No agreements yet.</p>
                @endforelse
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
                            <a href="{{ route('users.show', $member) }}" class="text-decoration-none fw-semibold small">
                                {{ $member->name }}
                            </a>
                            @if($member->role)
                                <span class="badge bg-light text-dark border ms-2">{{ ucfirst($member->role) }}</span>
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
        @if($recentActivities->isNotEmpty())
            <div class="d-flex justify-content-end mb-2">
                <a href="{{ route('activities.create') }}" class="btn btn-sm btn-outline-success">Log Activity</a>
            </div>
            <div class="table-responsive">
                <table class="table table-sm table-hover mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Date</th>
                            <th>Agreement</th>
                            <th>Type</th>
                            <th class="text-end">Hours</th>
                            <th>By</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($recentActivities as $activity)
                        <tr>
                            <td>
                                <a href="{{ route('activities.show', $activity) }}" class="text-decoration-none text-dark">
                                    {{ $activity->engagement_date->format('M d, Y') }}
                                </a>
                            </td>
                            <td>
                                @foreach($activity->agreements->take(1) as $agr)
                                    <a href="{{ route('agreements.show', $agr) }}" class="text-decoration-none badge bg-secondary">
                                        {{ $agr->name }}
                                    </a>
                                @endforeach
                            </td>
                            <td class="small">{{ $activity->activityType->name }}</td>
                            <td class="text-end">{{ number_format($activity->total_hours, 1) }}</td>
                            <td class="small text-muted">{{ $activity->user->name }}</td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @else
            <div class="text-center py-3">
                <p class="text-muted mb-2">No activities logged yet.</p>
                <a href="{{ route('activities.create') }}" class="btn btn-sm btn-outline-success">Log First Activity</a>
            </div>
        @endif
    </x-slot:activity>
</x-entity-show>
@endsection
