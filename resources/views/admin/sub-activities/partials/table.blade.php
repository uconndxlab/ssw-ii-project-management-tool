@php
    $s    = $sort ?? 'name';
    $d    = $direction ?? 'asc';
    $flip = fn($col) => ($s === $col && $d === 'asc') ? 'desc' : 'asc';
    $url  = fn($col) => route('sub-activities.index', array_merge(request()->query(), ['sort' => $col, 'direction' => $flip($col), 'page' => 1]));
    $scopeEmptyLabel = fn ($mode, string $allLabel, string $noneLabel) => ($mode?->value ?? $mode) === 'none' ? $noneLabel : $allLabel;
@endphp

<div class="card shadow-sm app-index-table-card">
    <x-table-scroll>
        <table class="table table-hover align-middle mb-0 app-cards">
            <thead>
                <tr>
                    <th>
                        <x-table-sort-link column="name" label="Name" :sort="$s" :direction="$d" :url="$url('name')" target="#sub-activities-table" />
                    </th>
                    <th>
                        <x-table-sort-link column="activity_type" label="Activity Type" :sort="$s" :direction="$d" :url="$url('activity_type')" target="#sub-activities-table" />
                    </th>
                    <th>
                        <x-table-sort-link column="projects" label="Projects" :sort="$s" :direction="$d" :url="$url('projects')" target="#sub-activities-table" />
                    </th>
                    <th>
                        <x-table-sort-link column="programs" label="Programs" :sort="$s" :direction="$d" :url="$url('programs')" target="#sub-activities-table" />
                    </th>
                    <th>
                        <x-table-sort-link column="duration_days" label="Duration (Days)" :sort="$s" :direction="$d" :url="$url('duration_days')" target="#sub-activities-table" />
                    </th>
                    <th>
                        <x-table-sort-link column="duration_hours" label="Duration (Hours)" :sort="$s" :direction="$d" :url="$url('duration_hours')" target="#sub-activities-table" />
                    </th>
                    <th>
                        <x-table-sort-link column="active" label="Status" :sort="$s" :direction="$d" :url="$url('active')" target="#sub-activities-table" />
                    </th>
                    <th class="app-col-actions"><span class="visually-hidden">Actions</span></th>
                </tr>
            </thead>
            <tbody>
                @forelse($subActivities as $type)
                <tr>
                    <td class="app-card-lead fw-semibold">{{ $type->name }}</td>
                    <td data-label="Family" class="text-muted small">{{ $type->activityType->name }} <span class="text-muted">({{ $type->activityType->contactFamily->name }})</span></td>
                    <td data-label="Projects">
                        <x-table-badge-list
                            kind="project"
                            :items="$type->projects"
                            route-name="projects.show"
                            :empty-label="$scopeEmptyLabel($type->program_scope_mode, 'All projects', 'No projects')"
                        />
                    </td>
                    <td data-label="Programs">
                        <x-table-badge-list
                            kind="program"
                            :items="$type->programs"
                            route-name="programs.show"
                            :empty-label="$scopeEmptyLabel($type->program_scope_mode, 'All programs', 'No programs')"
                        />
                    </td>
                    @php
                        $duration = \App\Support\ActivityTypeDuration::effective($type->activityType, $type);
                        $source = \App\Support\ActivityTypeDuration::effectiveSource($type->activityType, $type);
                    @endphp
                    <td data-label="Days">@if($duration->unit() === 'days'){{ $duration->formatLabel() }}{{ $source === 'activity_type' ? ' (from type)' : '' }}@endif</td>
                    <td data-label="Hours">@if($duration->unit() === 'hours'){{ $duration->formatLabel() }}{{ $source === 'activity_type' ? ' (from type)' : '' }}@endif</td>
                    <td class="app-card-status">
                        <x-status-badge :active="$type->active" />
                        @if($type->active && ! $type->activityType->active)<span class="text-muted small">Type inactive</span>@endif
                    </td>
                    <td class="app-col-actions">
                        @php $actionKey = 'sub-activity-actions-' . $type->id; @endphp
                        <div class="dropdown app-row-actions d-inline-block">
                            <button type="button" class="btn btn-sm btn-link app-row-actions-toggle" data-bs-toggle="dropdown" data-bs-popper-config='{"strategy":"fixed"}' aria-expanded="false" aria-label="Sub-activity actions for {{ $type->name }}"><i class="bi bi-three-dots" aria-hidden="true"></i></button>
                            <div class="dropdown-menu dropdown-menu-end">
                            @can('update', $type)
                            <a href="{{ route('sub-activities.edit', $type) }}" class="dropdown-item"><i class="bi bi-pencil-square me-2" aria-hidden="true"></i>Edit sub-activity</a>
                            @endcan
                            @can('delete', $type)
                            <hr class="dropdown-divider">
                            <button type="submit"
                                    form="{{ $actionKey }}-delete"
                                    data-confirm
                                    data-confirm-title="Delete sub-activity?"
                                    data-confirm-record="{{ $type->name }}"
                                    data-confirm-message="This action cannot be undone."
                                    data-confirm-action="Delete sub-activity" class="dropdown-item text-danger"><i class="bi bi-trash me-2" aria-hidden="true"></i>Delete sub-activity</button>
                            @endcan
                            </div>
                        </div>
                        @can('delete', $type)
                        <form id="{{ $actionKey }}-delete" method="POST" action="{{ route('sub-activities.destroy', $type) }}" class="d-none">
                            @csrf
                            @method('DELETE')
                        </form>
                        @endcan
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="8" class="text-center py-5">
                        <p class="text-muted mb-2">No sub-activities found.</p>
                        @can('create', App\Models\SubActivity::class)
                        <a href="{{ route('sub-activities.create') }}" class="btn btn-sm btn-primary">Add Sub-activity</a>
                        @endcan
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </x-table-scroll>
    <div class="card-footer">
        <x-htmx-pagination :paginator="$subActivities" target="#sub-activities-table" />
    </div>
</div>

@once
    <script>
    (function () {
        document.body.addEventListener('htmx:afterSwap', function (event) {
            if (event.target && event.target.id === 'sub-activities-table') {
                var params = new URLSearchParams(window.location.search);
                var form = document.getElementById('sub-activity-filters');
                if (form) {
                    if (params.has('sort')) {
                        var sortInput = form.querySelector('[name=sort]');
                        if (sortInput) {
                            sortInput.value = params.get('sort');
                        }
                    }
                    if (params.has('direction')) {
                        var directionInput = form.querySelector('[name=direction]');
                        if (directionInput) {
                            directionInput.value = params.get('direction');
                        }
                    }
                }
            }
        });
    })();
    </script>
@endonce
