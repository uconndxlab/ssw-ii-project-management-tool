@extends('layouts.app')

@section('title', $user->name)

@section('content')
<x-entity-show
    title="{{ $user->name }}"
    type="{{ ucfirst($user->role) }}"
    typeBadgeClass="{{ $user->isAdmin() ? 'bg-danger' : ($user->isStaff() ? 'bg-primary' : 'bg-secondary') }}"
    backRoute="{{ route('admin.users.index') }}"
    backLabel="All Users"
>
    {{-- ── Summary ─────────────────────────────────────────────────────── --}}
    <x-slot:summary>
        <dl class="row mb-0">
            <dt class="col-5 text-muted fw-normal small">Name</dt>
            <dd class="col-7 mb-2 fw-semibold">{{ $user->name }}</dd>

            <dt class="col-5 text-muted fw-normal small">Email</dt>
            <dd class="col-7 mb-2">
                <a href="mailto:{{ $user->email }}" class="text-decoration-none small">
                    {{ $user->email }}
                </a>
            </dd>

            <dt class="col-5 text-muted fw-normal small">Role</dt>
            <dd class="col-7 mb-2">
                <span class="badge {{ $user->isAdmin() ? 'bg-danger' : ($user->isStaff() ? 'bg-primary' : 'bg-secondary') }}">
                    {{ ucfirst($user->role) }}
                </span>
            </dd>

            <dt class="col-5 text-muted fw-normal small">Supervisor</dt>
            <dd class="col-7 mb-2">
                @if($user->supervisor)
                    <a href="{{ route('users.show', $user->supervisor) }}" class="text-decoration-none">
                        {{ $user->supervisor->name }}
                    </a>
                @else
                    <span class="text-muted">—</span>
                @endif
            </dd>

            <dt class="col-5 text-muted fw-normal small">Agreements</dt>
            <dd class="col-7 mb-2">
                <span class="badge bg-success rounded-pill">{{ $user->agreements->count() }}</span>
            </dd>

            <dt class="col-5 text-muted fw-normal small">Teams</dt>
            <dd class="col-7 mb-2">
                <span class="badge bg-warning text-dark rounded-pill">{{ $user->teams->count() }}</span>
            </dd>

            <dt class="col-5 text-muted fw-normal small">Joined</dt>
            <dd class="col-7 mb-0 small text-muted">{{ $user->created_at->format('M d, Y') }}</dd>
        </dl>
    </x-slot:summary>

    {{-- ── Relationships ───────────────────────────────────────────────── --}}
    <x-slot:relationships>
        <div class="row g-4">
            {{-- Agreements --}}
            <div class="col-md-6">
                <h6 class="fw-semibold mb-3">
                    Agreements
                    <span class="badge bg-success rounded-pill ms-1">{{ $user->agreements->count() }}</span>
                </h6>
                @forelse($user->agreements->sortBy('name') as $agreement)
                    <div class="py-2 border-bottom">
                        <a href="{{ route('agreements.show', $agreement) }}" class="text-decoration-none fw-semibold d-block">
                            {{ $agreement->name }}
                        </a>
                        @if($agreement->organizations->isNotEmpty())
                            <small class="text-muted">{{ $agreement->organizations->pluck('name')->join(', ') }}</small>
                        @endif
                        @if($agreement->states->isNotEmpty())
                            <div class="mt-1">
                                @foreach($agreement->states as $state)
                                    <span class="badge bg-info text-dark">{{ $state->name }}</span>
                                @endforeach
                            </div>
                        @endif
                    </div>
                @empty
                    <p class="text-muted small mb-0">Not assigned to any agreements.</p>
                @endforelse
            </div>

            {{-- Teams --}}
            <div class="col-md-6">
                <h6 class="fw-semibold mb-3">
                    Teams
                    <span class="badge bg-warning text-dark rounded-pill ms-1">{{ $user->teams->count() }}</span>
                </h6>
                @forelse($user->teams->sortBy('name') as $team)
                    <div class="d-flex justify-content-between align-items-center py-2 border-bottom">
                        <span class="fw-semibold">{{ $team->name }}</span>
                        @if($team->active)
                            <span class="badge bg-success">Active</span>
                        @else
                            <span class="badge bg-secondary">Inactive</span>
                        @endif
                    </div>
                @empty
                    <p class="text-muted small mb-0">Not a member of any teams.</p>
                @endforelse
            </div>
        </div>
    </x-slot:relationships>

    {{-- ── Recent Activity ─────────────────────────────────────────────── --}}
    <x-slot:activity>
        @if($recentActivities->isNotEmpty())
            <div class="table-responsive">
                <table class="table table-sm table-hover mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Date</th>
                            <th>Type</th>
                            <th>Agreement</th>
                            <th class="text-end">Hours</th>
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
                            <td>{{ $activity->activityType->name ?? '—' }}</td>
                            <td>
                                @foreach($activity->agreements->take(2) as $agr)
                                    <a href="{{ route('agreements.show', $agr) }}" class="badge bg-secondary text-decoration-none me-1">
                                        {{ $agr->name }}
                                    </a>
                                @endforeach
                            </td>
                            <td class="text-end">—</td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @else
            <p class="text-muted mb-0">No activities logged by this user yet.</p>
        @endif
    </x-slot:activity>
</x-entity-show>
@endsection
