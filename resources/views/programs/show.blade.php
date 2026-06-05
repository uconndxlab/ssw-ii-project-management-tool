@extends('layouts.app')

@section('title', $program->name)

@section('content')
<x-entity-show
    title="{{ $program->name }}"
    type="Program"
    typeBadgeClass="bg-warning text-dark"
    editRoute="{{ auth()->user()->isAdmin() ? route('programs.edit', $program) : null }}"
    backRoute="{{ route('programs.index') }}"
    backLabel="All Programs"
>
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

            <dt class="col-5 text-muted fw-normal small">Project</dt>
            <dd class="col-7 mb-2">
                @if($program->project)
                    <a href="{{ route('projects.show', $program->project) }}" class="text-decoration-none">
                        {{ $program->project->name }}
                    </a>
                @else
                    <span class="text-muted">—</span>
                @endif
            </dd>

            <dt class="col-5 text-muted fw-normal small">Agreements</dt>
            <dd class="col-7 mb-2">
                <span class="badge bg-success rounded-pill">{{ $agreements->count() }}</span>
            </dd>

            <dt class="col-5 text-muted fw-normal small">Organizations</dt>
            <dd class="col-7 mb-2">
                <span class="badge bg-primary rounded-pill">{{ $organizations->count() }}</span>
            </dd>

            <dt class="col-5 text-muted fw-normal small">Staff</dt>
            <dd class="col-7 mb-2">
                <span class="badge bg-secondary rounded-pill">{{ $users->count() }}</span>
            </dd>

            <dt class="col-5 text-muted fw-normal small">Activities</dt>
            <dd class="col-7 mb-2">
                <span class="badge bg-primary rounded-pill">{{ $program->activities->count() }}</span>
            </dd>

            <dt class="col-5 text-muted fw-normal small">States</dt>
            <dd class="col-7 mb-2">
                @forelse($states as $state)
                    <a href="{{ route('states.show', $state) }}" class="badge bg-info text-dark text-decoration-none me-1">
                        {{ $state->name }}
                    </a>
                @empty
                    <span class="text-muted">—</span>
                @endforelse
            </dd>

            <dt class="col-5 text-muted fw-normal small">Added</dt>
            <dd class="col-7 mb-0 small text-muted">{{ $program->created_at->format('M d, Y') }}</dd>
        </dl>

        @if($program->description)
            <hr>
            <h6 class="text-muted fw-normal small mb-1">Description</h6>
            <p class="mb-0 small">{{ $program->description }}</p>
        @endif
    </x-slot:summary>

    {{-- ── Relationships ───────────────────────────────────────────────── --}}
    <x-slot:relationships>
        <div class="row g-4">

            {{-- Agreements --}}
            <div class="col-md-6">
                <h6 class="fw-semibold mb-3">
                    Agreements
                    <span class="badge bg-success rounded-pill ms-1">{{ $agreements->count() }}</span>
                </h6>
                @forelse($agreements as $agreement)
                    <div class="py-2 border-bottom">
                        <a href="{{ route('agreements.show', $agreement) }}" class="text-decoration-none fw-semibold d-block">
                            {{ $agreement->name }}
                        </a>
                        <div class="d-flex flex-wrap gap-1 mt-1">
                            @foreach($agreement->states as $state)
                                <a href="{{ route('states.show', $state) }}" class="badge bg-info text-dark text-decoration-none">
                                    {{ $state->name }}
                                </a>
                            @endforeach
                            @if($agreement->users->isNotEmpty())
                                <small class="text-muted">{{ $agreement->users->count() }} staff</small>
                            @endif
                        </div>
                    </div>
                @empty
                    <p class="text-muted small mb-0">No agreements linked to this program yet.</p>
                @endforelse
            </div>

            {{-- Organizations --}}
            <div class="col-md-6">
                <h6 class="fw-semibold mb-3">
                    Organizations
                    <span class="badge bg-primary rounded-pill ms-1">{{ $organizations->count() }}</span>
                </h6>
                @forelse($organizations as $org)
                    <div class="py-2 border-bottom">
                        <a href="{{ route('organizations.show', $org) }}" class="text-decoration-none fw-semibold d-block">
                            {{ $org->name }}
                        </a>
                        <div class="d-flex flex-wrap gap-1 mt-1">
                            @foreach($org->states as $state)
                                <a href="{{ route('states.show', $state) }}" class="badge bg-info text-dark text-decoration-none">
                                    {{ $state->name }}
                                </a>
                            @endforeach
                        </div>
                    </div>
                @empty
                    <p class="text-muted small mb-0">No organizations linked.</p>
                @endforelse
            </div>

            {{-- Staff --}}
            <div class="col-12">
                <h6 class="fw-semibold mb-1">
                    Assigned Staff
                    <span class="badge bg-secondary rounded-pill ms-1">{{ $users->count() }}</span>
                </h6>
                <p class="text-muted small mb-3">Staff are assigned at the agreement level.</p>
                <div class="row g-2">
                    @forelse($users as $user)
                        <div class="col-md-4 col-sm-6">
                            <div class="py-2 border-bottom">
                                <a href="{{ route('users.show', $user) }}" class="text-decoration-none fw-semibold small d-block">
                                    {{ $user->name }}
                                </a>
                                @if($user->email)
                                    <div class="small text-muted">{{ $user->email }}</div>
                                @endif
                                @if($user->via_agreements?->isNotEmpty())
                                    <div class="mt-1 d-flex flex-wrap gap-1">
                                        @foreach($user->via_agreements as $agreementName)
                                            <span class="badge bg-success-subtle text-success-emphasis" style="font-size:.7rem;">{{ $agreementName }}</span>
                                        @endforeach
                                    </div>
                                @endif
                            </div>
                        </div>
                    @empty
                        <div class="col-12">
                            <p class="text-muted small mb-0">No staff assigned via agreements.</p>
                        </div>
                    @endforelse
                </div>
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
            @if($program->activities->count() > 10)
                <p class="text-muted small mt-2 mb-0">Showing 10 of {{ $program->activities->count() }} activities.</p>
            @endif
        @else
            <p class="text-muted mb-0">No activities logged for this program yet.</p>
        @endif
    </x-slot:activity>
</x-entity-show>
@endsection
