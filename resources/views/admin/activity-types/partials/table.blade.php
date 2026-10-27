@php
    $s    = $sort ?? 'name';
    $d    = $direction ?? 'asc';
    $flip = fn($col) => ($s === $col && $d === 'asc') ? 'desc' : 'asc';
    $url  = fn($col) => route('activity-types.index', array_merge(request()->query(), ['sort' => $col, 'direction' => $flip($col), 'page' => 1]));
    $scopeEmptyLabel = fn ($mode, string $allLabel, string $noneLabel) => ($mode?->value ?? $mode) === 'none' ? $noneLabel : $allLabel;
@endphp

<div class="card shadow-sm app-index-table-card">
    <x-table-scroll>
        <table class="table table-hover align-middle mb-0">
            <thead>
                <tr>
                    <th>
                        <x-table-sort-link column="name" label="Name" :sort="$s" :direction="$d" :url="$url('name')" target="#activity-types-table" />
                    </th>
                    <th>
                        <x-table-sort-link column="contact_family" label="Activity Family" :sort="$s" :direction="$d" :url="$url('contact_family')" target="#activity-types-table" />
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
                    <th class="app-col-actions"><span class="visually-hidden">Actions</span></th>
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
                        <x-status-badge :active="$type->active" />
                    </td>
                    <td class="app-col-actions">
                        @php $actionKey = 'activity-type-actions-' . $type->id; @endphp
                        <div class="dropdown app-row-actions d-inline-block">
                            <button type="button" class="btn btn-sm btn-link app-row-actions-toggle" data-bs-toggle="dropdown" data-bs-popper-config='{"strategy":"fixed"}' aria-expanded="false" aria-label="Activity type actions for {{ $type->name }}"><i class="bi bi-three-dots" aria-hidden="true"></i></button>
                            <div class="dropdown-menu dropdown-menu-end">
                            @can('update', $type)
                            <a href="{{ route('activity-types.edit', $type) }}" class="dropdown-item"><i class="bi bi-pencil-square me-2" aria-hidden="true"></i>Edit activity type</a>
                            @endcan
                            @can('delete', $type)
                            <hr class="dropdown-divider">
                            <button type="submit"
                                    form="{{ $actionKey }}-delete"
                                    data-confirm
                                    data-confirm-title="Delete activity type?"
                                    data-confirm-record="{{ $type->name }}"
                                    data-confirm-message="This action cannot be undone."
                                    data-confirm-action="Delete activity type" class="dropdown-item text-danger"><i class="bi bi-trash me-2" aria-hidden="true"></i>Delete activity type</button>
                            @endcan
                            </div>
                        </div>
                        @can('delete', $type)
                        <form id="{{ $actionKey }}-delete" method="POST" action="{{ route('activity-types.destroy', $type) }}" class="d-none">
                            @csrf
                            @method('DELETE')
                        </form>
                        @endcan
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="8" class="text-center py-5">
                        <p class="text-muted mb-2">No activity types found.</p>
                        @can('create', App\Models\ActivityType::class)
                        <a href="{{ route('activity-types.create') }}" class="btn btn-sm btn-primary">Add Activity Type</a>
                        @endcan
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </x-table-scroll>
    <div class="card-footer">
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
