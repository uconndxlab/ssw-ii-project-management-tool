@php
    $s    = $sort ?? 'name';
    $d    = $direction ?? 'asc';
    $flip = fn($col) => ($s === $col && $d === 'asc') ? 'desc' : 'asc';
    $url  = fn($col) => route('activity-types.index', array_merge(request()->query(), ['sort' => $col, 'direction' => $flip($col), 'page' => 1]));
    $scopeEmptyLabel = fn ($mode, string $allLabel, string $noneLabel) => ($mode?->value ?? $mode) === 'none' ? $noneLabel : $allLabel;
@endphp

<div class="card shadow-sm app-index-table-card">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
                <tr>
                    <th>
                        <x-table-sort-link column="name" label="Name" :sort="$s" :direction="$d" :url="$url('name')" target="#activity-types-table" />
                    </th>
                    <th>
                        <x-table-sort-link column="contact_family" label="Contact Family" :sort="$s" :direction="$d" :url="$url('contact_family')" target="#activity-types-table" />
                    </th>
                    <th>
                        <x-table-sort-link column="projects" label="Projects" :sort="$s" :direction="$d" :url="$url('projects')" target="#activity-types-table" />
                    </th>
                    <th>
                        <x-table-sort-link column="programs" label="Programs" :sort="$s" :direction="$d" :url="$url('programs')" target="#activity-types-table" />
                    </th>
                    <th>
                        <x-table-sort-link column="duration_days" label="Duration (Days)" :sort="$s" :direction="$d" :url="$url('duration_days')" target="#activity-types-table" />
                    </th>
                    <th>
                        <x-table-sort-link column="duration_hours" label="Duration (Hours)" :sort="$s" :direction="$d" :url="$url('duration_hours')" target="#activity-types-table" />
                    </th>
                    <th>
                        <x-table-sort-link column="active" label="Status" :sort="$s" :direction="$d" :url="$url('active')" target="#activity-types-table" />
                    </th>
                    <th class="text-end" style="width:140px;">Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse($activityTypes as $type)
                <tr>
                    <td class="fw-semibold">{{ $type->name }}</td>
                    <td class="text-muted small">{{ $type->contactFamily->name }}</td>
                    <td>
                        <x-table-badge-list
                            kind="project"
                            :items="$type->projects"
                            route-name="projects.show"
                            :empty-label="$scopeEmptyLabel($type->program_scope_mode, 'All projects', 'No projects')"
                        />
                    </td>
                    <td>
                        <x-table-badge-list
                            kind="program"
                            :items="$type->programs"
                            route-name="programs.show"
                            :empty-label="$scopeEmptyLabel($type->program_scope_mode, 'All programs', 'No programs')"
                        />
                    </td>
                    <td>{{ $type->duration_days }}</td>
                    <td>{{ $type->duration_hours }}</td>
                    <td>
                        @if($type->active)
                            <span class="badge bg-success">Active</span>
                        @else
                            <span class="badge bg-secondary">Inactive</span>
                        @endif
                    </td>
                    <td class="text-end text-nowrap">
                        @php $actionKey = 'activity-type-actions-' . $type->id; @endphp
                        <div class="btn-group btn-group-sm" role="group" aria-label="Activity type actions for {{ $type->name }}">
                            <a href="{{ route('activity-types.edit', $type) }}"
                               class="btn btn-outline-secondary"
                               data-bs-toggle="tooltip"
                               data-bs-title="Edit activity type"
                               aria-label="Edit activity type">
                                <i class="bi bi-pencil-square"></i>
                            </a>
                            <button type="submit"
                                    form="{{ $actionKey }}-delete"
                                    class="btn btn-outline-danger"
                                    data-bs-toggle="tooltip"
                                    data-bs-title="Delete activity type"
                                    aria-label="Delete activity type"
                                    onclick="return confirm('Delete {{ addslashes($type->name) }}?')">
                                <i class="bi bi-trash"></i>
                            </button>
                        </div>
                        <form id="{{ $actionKey }}-delete" method="POST" action="{{ route('activity-types.destroy', $type) }}" class="d-none">
                            @csrf
                            @method('DELETE')
                        </form>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="8" class="text-center py-5">
                        <p class="text-muted mb-2">No activity types found.</p>
                        <a href="{{ route('activity-types.create') }}" class="btn btn-sm btn-primary">Add Activity Type</a>
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="card-footer bg-white">
        <x-htmx-pagination :paginator="$activityTypes" target="#activity-types-table" />
    </div>
</div>

@once
    <script>
    (function () {
        document.body.addEventListener('htmx:afterSwap', function (event) {
            if (event.target && event.target.id === 'activity-types-table') {
                var params = new URLSearchParams(window.location.search);
                var form = document.getElementById('activity-type-filters');
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
