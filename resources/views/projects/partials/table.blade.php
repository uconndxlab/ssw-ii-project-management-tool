@php
    $s = $sort ?? 'name';
    $d = $direction ?? 'asc';
    $flip = fn ($col) => ($s === $col && $d === 'asc') ? 'desc' : 'asc';
    $url = fn ($col) => route('projects.index', array_merge(request()->query(), ['sort' => $col, 'direction' => $flip($col), 'page' => 1]));
@endphp

<div class="card shadow-sm">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
                <tr>
                    <th style="min-width: 160px;">
                        <x-table-sort-link column="name" label="Name" :sort="$s" :direction="$d" :url="$url('name')" target="#projects-table" />
                    </th>
                    <th>Description</th>
                    <th>
                        <x-table-sort-link column="programs" label="Programs" :sort="$s" :direction="$d" :url="$url('programs')" target="#projects-table" />
                    </th>
                    <th>
                        <x-table-sort-link column="status" label="Status" :sort="$s" :direction="$d" :url="$url('status')" target="#projects-table" />
                    </th>
                    <th class="text-end fw-normal" style="width:130px;">Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse($projects as $project)
                <tr>
                    <td>
                        <a href="{{ route('projects.show', $project) }}" class="fw-semibold text-decoration-none text-dark">
                            {{ $project->name }}
                        </a>
                    </td>
                    <td class="text-muted small">{{ Str::limit($project->description, 60) ?: '—' }}</td>
                    <td>
                        <div class="d-flex flex-wrap gap-1">
                            @forelse($project->programs->sortBy('name') as $program)
                                <x-entity-relation-badge kind="program" :href="route('programs.show', $program)">
                                    {{ $program->name }}
                                </x-entity-relation-badge>
                            @empty
                                <span class="text-muted small">—</span>
                            @endforelse
                        </div>
                    </td>
                    <td>
                        @if($project->active)
                            <span class="badge bg-success">Active</span>
                        @else
                            <span class="badge bg-secondary">Inactive</span>
                        @endif
                    </td>
                    <td class="text-end text-nowrap">
                        @php $actionKey = 'project-actions-' . $project->id; @endphp
                        <div class="btn-group btn-group-sm" role="group" aria-label="Project actions for {{ $project->name }}">
                            <a href="{{ route('projects.show', $project) }}"
                               class="btn btn-outline-primary"
                               data-bs-toggle="tooltip"
                               data-bs-title="View project"
                               aria-label="View project">
                                <i class="bi bi-eye"></i>
                            </a>
                            <a href="{{ route('projects.edit', $project) }}"
                               class="btn btn-outline-secondary"
                               data-bs-toggle="tooltip"
                               data-bs-title="Edit project"
                               aria-label="Edit project">
                                <i class="bi bi-pencil-square"></i>
                            </a>
                            <button type="submit"
                                    form="{{ $actionKey }}-delete"
                                    class="btn btn-outline-danger"
                                    data-bs-toggle="tooltip"
                                    data-bs-title="Delete project"
                                    aria-label="Delete project"
                                    onclick="return confirm('Delete {{ addslashes($project->name) }}? This will also delete all {{ $project->programs_count }} associated programs.')">
                                <i class="bi bi-trash"></i>
                            </button>
                        </div>
                        <form id="{{ $actionKey }}-delete" method="POST" action="{{ route('projects.destroy', $project) }}" class="d-none">
                            @csrf
                            @method('DELETE')
                        </form>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="5" class="text-center py-5">
                        <p class="text-muted mb-2">No projects found.</p>
                        <a href="{{ route('projects.create') }}" class="btn btn-sm btn-primary">Create Project</a>
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="card-footer bg-white">
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
