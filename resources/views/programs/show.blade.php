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
            <dt class="col-5 text-muted fw-normal small">Name</dt>
            <dd class="col-7 mb-2 fw-semibold">{{ $program->name }}</dd>

            <dt class="col-5 text-muted fw-normal small">Status</dt>
            <dd class="col-7 mb-2">
                @if($program->active)
                    <span class="badge bg-success">Active</span>
                @else
                    <span class="badge bg-secondary">Inactive</span>
                @endif
            </dd>

            <dt class="col-5 text-muted fw-normal small">Parent Project</dt>
            <dd class="col-7 mb-2">
                @if($program->project)
                    <a href="{{ route('projects.show', $program->project) }}" class="text-decoration-none">
                        {{ $program->project->name }}
                    </a>
                @else
                    <span class="text-muted">—</span>
                @endif
            </dd>

            <dt class="col-5 text-muted fw-normal small">Activities</dt>
            <dd class="col-7 mb-2">
                <span class="badge bg-primary rounded-pill">{{ $program->activities->count() }}</span>
            </dd>

            <dt class="col-5 text-muted fw-normal small">Added</dt>
            <dd class="col-7 mb-0 small text-muted">{{ $program->created_at->format('M d, Y') }}</dd>
        </dl>
    </x-slot:summary>

    {{-- ── Relationships ───────────────────────────────────────────────── --}}
    <x-slot:relationships>
        @if($program->project)
        <div class="mb-4">
            <h6 class="fw-semibold mb-2">Parent Project</h6>
            <div class="d-flex align-items-center gap-2 p-3 rounded border">
                <span class="badge bg-dark">Project</span>
                <a href="{{ route('projects.show', $program->project) }}" class="fw-semibold text-decoration-none">
                    {{ $program->project->name }}
                </a>
                @if(!$program->project->active)
                    <span class="badge bg-secondary ms-auto">Inactive</span>
                @endif
            </div>
        </div>
        @endif

        {{-- Agreements linked via activities --}}
        @php
            $linkedAgreements = $program->activities
                ->flatMap(fn($a) => $a->agreements)
                ->unique('id')
                ->sortBy('name');
        @endphp
        <div>
            <h6 class="fw-semibold mb-2">
                Agreements (via activities)
                <span class="badge bg-success rounded-pill ms-1">{{ $linkedAgreements->count() }}</span>
            </h6>
            @forelse($linkedAgreements as $agreement)
                <div class="py-2 border-bottom">
                    <a href="{{ route('agreements.show', $agreement) }}" class="text-decoration-none fw-semibold">
                        {{ $agreement->name }}
                    </a>
                </div>
            @empty
                <p class="text-muted small mb-0">No agreements linked to this program yet.</p>
            @endforelse
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
