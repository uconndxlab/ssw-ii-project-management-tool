@extends('layouts.app')

@section('title', $project->name)

@section('content')
<x-entity-show
    title="{{ $project->name }}"
    type="Project"
    typeBadgeClass="bg-dark"
    editRoute="{{ route('projects.edit', $project) }}"
    backRoute="{{ route('projects.index') }}"
    backLabel="All Projects"
>
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
                <span class="badge bg-warning text-dark rounded-pill">{{ $project->programs->count() }}</span>
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
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h6 class="fw-semibold mb-0">
                Programs
                <span class="badge bg-warning text-dark rounded-pill ms-1">{{ $project->programs->count() }}</span>
            </h6>
            <a href="{{ route('programs.create') }}" class="btn btn-sm btn-outline-primary">+ Add Program</a>
        </div>

        @forelse($project->programs->sortBy('name') as $program)
            <div class="d-flex justify-content-between align-items-center py-2 border-bottom">
                <div>
                    <a href="{{ route('programs.show', $program) }}" class="text-decoration-none fw-semibold d-block">
                        {{ $program->name }}
                    </a>
                    <small class="text-muted">{{ $program->activities->count() }} activities</small>
                </div>
                <div class="d-flex gap-2 align-items-center">
                    @if($program->active)
                        <span class="badge bg-success">Active</span>
                    @else
                        <span class="badge bg-secondary">Inactive</span>
                    @endif
                    <a href="{{ route('programs.edit', $program) }}" class="btn btn-sm btn-outline-secondary">Edit</a>
                </div>
            </div>
        @empty
            <p class="text-muted small mb-0">No programs in this project yet.</p>
        @endforelse
    </x-slot:relationships>

    {{-- ── Recent Activity ─────────────────────────────────────────────── --}}
    <x-slot:activity>
        @if(isset($recentActivities) && $recentActivities->isNotEmpty())
            <div class="table-responsive">
                <table class="table table-sm table-hover mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Date</th>
                            <th>Program</th>
                            <th>Type</th>
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
                            <td>
                                @foreach($activity->programs->take(2) as $prog)
                                    <a href="{{ route('programs.show', $prog) }}" class="badge bg-warning text-dark text-decoration-none me-1">
                                        {{ $prog->name }}
                                    </a>
                                @endforeach
                            </td>
                            <td>{{ $activity->activityType->name ?? '—' }}</td>
                            <td class="text-end">—</td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @else
            <p class="text-muted mb-0">No activities logged for this project yet.</p>
        @endif
    </x-slot:activity>
</x-entity-show>
@endsection

