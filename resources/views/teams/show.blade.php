@extends('layouts.app')

@section('title', $team->name)

@section('content')
<div class="row mb-4">
    <div class="col-12">
        <x-page-header context="show" :title="$team->name" entity-type="Team" :active="$team->active" :action-url="auth()->user()->can('update', $team) ? route('teams.edit', $team) : null" />
    </div>
</div>

<div class="row">
    <!-- Team Details -->
    <div class="col-md-4">
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="mb-0">Team Details</h5>
            </div>
            <div class="card-body">
                <dl class="row mb-0">
                    <dt class="col-sm-4">Status:</dt>
                    <dd class="col-sm-8">
                        @if($team->active)
                            <span class="badge bg-success">Active</span>
                        @else
                            <span class="badge bg-secondary">Inactive</span>
                        @endif
                    </dd>

                    <dt class="col-sm-4">Members:</dt>
                    <dd class="col-sm-8">{{ $team->users->count() }}</dd>

                    <dt class="col-sm-4">Agreements:</dt>
                    <dd class="col-sm-8">{{ $team->agreements->count() }}</dd>

                    <dt class="col-sm-4">Created:</dt>
                    <dd class="col-sm-8">{{ $team->created_at->format('M d, Y') }}</dd>

                    <dt class="col-sm-4">Updated:</dt>
                    <dd class="col-sm-8">{{ $team->updated_at->format('M d, Y') }}</dd>
                </dl>
            </div>
        </div>
    </div>

    <!-- Team Members -->
    <div class="col-md-8">
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="mb-0">Team Members ({{ $team->users->count() }})</h5>
            </div>
            <div class="card-body">
                @if($team->users->isNotEmpty())
                    <div class="list-group list-group-flush">
                        @foreach($team->users as $user)
                        <div class="list-group-item px-0 py-2">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <x-user-link :user="$user" class="d-block fw-semibold" />
                                    <small class="text-muted">{{ $user->email }}</small>
                                </div>
                                <x-category-badge kind="role">{{ $user->accessLabel() }}</x-category-badge>
                            </div>
                        </div>
                        @endforeach
                    </div>
                @else
                    <p class="text-muted mb-0">No members in this team.</p>
                @endif
            </div>
        </div>

        <!-- Team Deliverables -->
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="mb-0">Team Deliverables</h5>
            </div>
            <div class="card-body">
                @php
                    $agreementsWithDeliverables = $team->agreements->filter(fn($a) => $a->deliverables->isNotEmpty());
                @endphp
                @if($team->agreements->isEmpty())
                    <p class="text-muted mb-0">This team is not assigned to any agreements.</p>
                @elseif($agreementsWithDeliverables->isEmpty())
                    <p class="text-muted mb-0">No deliverables have been defined for the agreements assigned to this team.</p>
                @else
                    @foreach($agreementsWithDeliverables as $agreement)
                        <div class="mb-4">
                            <h6 class="mb-2">
                                @if($agreement->isLinkable())
                                    <a href="{{ route('agreements.show', $agreement) }}" class="text-decoration-none">
                                        {{ $agreement->name }}
                                    </a>
                                @else
                                    <span class="text-body">{{ $agreement->name }}</span>
                                @endif
                                @if($agreement->start_date && $agreement->end_date)
                                    <small class="text-muted fw-normal ms-2">
                                        {{ $agreement->start_date->format('M Y') }} – {{ $agreement->end_date->format('M Y') }}
                                    </small>
                                @endif
                            </h6>
                            <div class="table-responsive">
                                <table class="table table-sm table-bordered mb-0">
                                    <thead class="table-light">
                                        <tr>
                                            <th>Activity Type</th>
                                            <th>Activity Family</th>
                                            <th class="text-center">Metric</th>
                                            <th class="text-center">Target</th>
                                            <th>Notes</th>
                                            <th>Assigned Members</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach($agreement->deliverables as $deliverable)
                                        @php
                                            $activeAssignedUsers = $deliverable->users
                                                ->filter(fn ($assignedUser) => !$assignedUser->pivot->unassigned_at)
                                                ->whereIn('id', $team->users->pluck('id'));
                                        @endphp
                                        <tr>
                                            <td>{{ $deliverable->activityType?->name ?? '—' }}</td>
                                            <td>{{ $deliverable->contactFamily?->name ?? '—' }}</td>
                                            <td class="text-center">
                                                @if($deliverable->metric_type === 'time')
                                                    {{ ($deliverable->time_basis ?? 'observed') === 'allotted' ? 'Allotted time' : 'Time' }}
                                                @else
                                                    {{ $deliverable->metric_type ? ucfirst($deliverable->metric_type) : '—' }}
                                                @endif
                                            </td>
                                            <td class="text-center">{{ $deliverable->target_quantity !== null ? number_format((float) $deliverable->target_quantity, 1) : '—' }}</td>
                                            <td>{{ $deliverable->notes ?? '' }}</td>
                                            <td>
                                                @forelse($activeAssignedUsers as $assignedUser)
                                                    <span class="badge bg-secondary me-1">{{ $assignedUser->name }}</span>
                                                @empty
                                                    <span class="text-muted small">—</span>
                                                @endforelse
                                            </td>
                                        </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    @endforeach
                @endif
            </div>
        </div>

        <!-- Individual Assignments -->
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">Individual Assignments</h5>
            </div>
            <div class="card-body">
                @if($team->users->isEmpty())
                    <p class="text-muted mb-0">No members on this team.</p>
                @else
                    @foreach($team->users as $member)
                        <div class="mb-4">
                            <div class="d-flex align-items-center mb-2">
                                <x-user-link :user="$member" class="fw-semibold" />
                                <x-category-badge kind="role" class="ms-2">{{ $member->accessLabel() }}</x-category-badge>
                            </div>
                            @if(!empty($memberDeliverables[$member->id]))
                                <div class="table-responsive">
                                    <table class="table table-sm table-bordered mb-0">
                                        <thead class="table-light">
                                            <tr>
                                                <th>Agreement</th>
                                                <th>Activity Type</th>
                                                <th class="text-center">Metric</th>
                                                <th class="text-center">Target</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @foreach($memberDeliverables[$member->id] as $entry)
                                            <tr>
                                                <td>
                                                    @if($entry['agreement']->isLinkable())
                                                        <a href="{{ route('agreements.show', $entry['agreement']) }}" class="text-decoration-none">
                                                            {{ $entry['agreement']->name }}
                                                        </a>
                                                    @else
                                                        <span>{{ $entry['agreement']->name }}</span>
                                                    @endif
                                                </td>
                                                <td>{{ $entry['deliverable']->activityType?->name ?? '—' }}</td>
                                                <td class="text-center">
                                                    @if($entry['deliverable']->metric_type === 'time')
                                                        {{ ($entry['deliverable']->time_basis ?? 'observed') === 'allotted' ? 'Allotted time' : 'Time' }}
                                                    @else
                                                        {{ $entry['deliverable']->metric_type ? ucfirst($entry['deliverable']->metric_type) : '—' }}
                                                    @endif
                                                </td>
                                                <td class="text-center">{{ $entry['deliverable']->target_quantity !== null ? number_format((float) $entry['deliverable']->target_quantity, 1) : '—' }}</td>
                                            </tr>
                                            @endforeach
                                        </tbody>
                                    </table>
                                </div>
                            @else
                                <p class="text-muted small mb-0">No deliverables assigned.</p>
                            @endif
                        </div>
                        @if(!$loop->last)
                            <hr class="my-3">
                        @endif
                    @endforeach
                @endif
            </div>
        </div>
    </div>
</div>
@endsection
