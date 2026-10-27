@php
    $s = $sort ?? 'name';
    $d = $direction ?? 'asc';
    $flip = fn ($col) => ($s === $col && $d === 'asc') ? 'desc' : 'asc';
    $url = fn ($col) => route('projects.index', array_merge(request()->query(), ['sort' => $col, 'direction' => $flip($col), 'page' => 1]));
@endphp

<div class="card shadow-sm app-index-table-card">
    <x-table-scroll>
        <table class="table table-hover align-middle mb-0">
            <thead>
                <tr>
                    <th>
                        <x-table-sort-link column="name" label="Name" :sort="$s" :direction="$d" :url="$url('name')" target="#projects-table" />
                    </th>
                    <th>Description</th>
                    <th>
                        <x-table-sort-link column="programs" label="Programs" :sort="$s" :direction="$d" :url="$url('programs')" target="#projects-table" />
                    </th>
                    <th>
                        <x-table-sort-link column="status" label="Status" :sort="$s" :direction="$d" :url="$url('status')" target="#projects-table" />
                    </th>
                    <th class="app-col-actions"><span class="visually-hidden">Actions</span></th>
                </tr>
            </thead>
            <tbody>
                @forelse($projects as $project)
                <tr>
                    <td>
                        <a href="{{ route('projects.show', $project) }}" class="app-link app-link--strong app-row-link">
                            {{ $project->name }}
                        </a>
                    </td>
                    <td class="text-muted small">{{ Str::limit($project->description, 60) ?: '—' }}</td>
                    <td>
                        <x-table-badge-list
                            kind="program"
                            :items="$project->programs"
                            route-name="programs.show"
                        />
                    </td>
                    <td>
                        <x-status-badge :active="$project->active" />
                    </td>
                    <td class="app-col-actions">
                        @php $actionKey = 'project-actions-' . $project->id; @endphp
                        <div class="dropdown app-row-actions d-inline-block">
                            <button type="button" class="btn btn-sm btn-link app-row-actions-toggle" data-bs-toggle="dropdown" data-bs-popper-config='{"strategy":"fixed"}' aria-expanded="false" aria-label="Project actions for {{ $project->name }}"><i class="bi bi-three-dots" aria-hidden="true"></i></button>
                            <div class="dropdown-menu dropdown-menu-end">
                            <a href="{{ route('projects.show', $project) }}" class="dropdown-item"><i class="bi bi-eye me-2" aria-hidden="true"></i>View project</a>
                            @can('update', $project)
                            <a href="{{ route('projects.edit', $project) }}" class="dropdown-item"><i class="bi bi-pencil-square me-2" aria-hidden="true"></i>Edit project</a>
                            @endcan
                            @can('delete', $project)
                            <hr class="dropdown-divider">
                            <button type="submit"
                                    form="{{ $actionKey }}-delete"
                                    data-confirm
                                    data-confirm-title="Delete project?"
                                    data-confirm-record="{{ $project->name }}"
                                    data-confirm-message="Programs are kept. If a program depends only on this project, assign it to another project before deleting."
                                    data-confirm-action="Delete project" class="dropdown-item text-danger"><i class="bi bi-trash me-2" aria-hidden="true"></i>Delete project</button>
                            @endcan
                            </div>
                        </div>
                        @can('delete', $project)
                        <form id="{{ $actionKey }}-delete" method="POST" action="{{ route('projects.destroy', $project) }}" class="d-none">
                            @csrf
                            @method('DELETE')
                        </form>
                        @endcan
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="5" class="text-center py-5">
                        <p class="text-muted mb-2">No projects found.</p>
                        @can('create', App\Models\Project::class)
                        <a href="{{ route('projects.create') }}" class="btn btn-sm btn-primary">Create Project</a>
                        @endcan
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </x-table-scroll>
    <div class="card-footer">
        <x-htmx-pagination :paginator="$projects" target="#projects-table" />
    </div>
</div>

@once
    <script>
    (function () {
        document.body.addEventListener('htmx:afterSwap', function (event) {
            if (event.target && event.target.id === 'projects-table') {
                var params = new URLSearchParams(window.location.search);
                var form = document.getElementById('project-filters');
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
