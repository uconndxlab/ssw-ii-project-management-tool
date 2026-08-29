@php
    $s = $sort ?? 'name';
    $d = $direction ?? 'asc';
    $flip = fn ($col) => ($s === $col && $d === 'asc') ? 'desc' : 'asc';
    $url = fn ($col) => route('programs.index', array_merge(request()->query(), ['sort' => $col, 'direction' => $flip($col), 'page' => 1]));
@endphp

<div class="card shadow-sm app-index-table-card">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
                <tr>
                    <th style="min-width: 160px;">
                        <x-table-sort-link column="name" label="Name" :sort="$s" :direction="$d" :url="$url('name')" target="#programs-table" />
                    </th>
                    <th>Projects</th>
                    <th>
                        <x-table-sort-link column="status" label="Status" :sort="$s" :direction="$d" :url="$url('status')" target="#programs-table" />
                    </th>
                    <th class="text-nowrap">
                        <x-table-sort-link column="created" label="Created" :sort="$s" :direction="$d" :url="$url('created')" target="#programs-table" />
                    </th>
                    <th class="text-end" style="width:130px;">Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse($programs as $program)
                <tr>
                    <td>
                        <a href="{{ route('programs.show', $program) }}" class="fw-semibold text-decoration-none text-dark">
                            {{ $program->name }}
                        </a>
                    </td>
                    <td>
                        <x-table-badge-list
                            kind="project"
                            :items="$program->projects"
                            route-name="projects.show"
                        />
                    </td>
                    <td>
                        @if($program->active)
                            <span class="badge bg-success">Active</span>
                        @else
                            <span class="badge bg-secondary">Inactive</span>
                        @endif
                    </td>
                    <td class="text-muted small">{{ $program->created_at->format('M d, Y') }}</td>
                    <td class="text-end text-nowrap">
                        @php $actionKey = 'program-actions-' . $program->id; @endphp
                        <div class="btn-group btn-group-sm" role="group" aria-label="Program actions for {{ $program->name }}">
                            <a href="{{ route('programs.show', $program) }}"
                               class="btn btn-outline-primary"
                               data-bs-toggle="tooltip"
                               data-bs-title="View program"
                               aria-label="View program">
                                <i class="bi bi-eye"></i>
                            </a>
                            @can('update', $program)
                            <a href="{{ route('programs.edit', $program) }}"
                               class="btn btn-outline-secondary"
                               data-bs-toggle="tooltip"
                               data-bs-title="Edit program"
                               aria-label="Edit program">
                                <i class="bi bi-pencil-square"></i>
                            </a>
                            @endcan
                            @can('delete', $program)
                            <button type="submit"
                                    form="{{ $actionKey }}-delete"
                                    class="btn btn-outline-danger"
                                    data-bs-toggle="tooltip"
                                    data-bs-title="Delete program"
                                    aria-label="Delete program"
                                    onclick="return confirm('Delete {{ addslashes($program->name) }}?')">
                                <i class="bi bi-trash"></i>
                            </button>
                            @endcan
                        </div>
                        @can('delete', $program)
                        <form id="{{ $actionKey }}-delete" method="POST" action="{{ route('programs.destroy', $program) }}" class="d-none">
                            @csrf
                            @method('DELETE')
                        </form>
                        @endcan
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="5" class="text-center py-5">
                        <p class="text-muted mb-2">No programs found.</p>
                        @can('create', App\Models\Program::class)
                        <a href="{{ route('programs.create') }}" class="btn btn-sm btn-primary">Create Program</a>
                        @endcan
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="card-footer bg-white">
        <x-htmx-pagination :paginator="$programs" target="#programs-table" />
    </div>
</div>

@once
    <script>
    (function () {
        document.body.addEventListener('htmx:afterSwap', function (event) {
            if (event.target && event.target.id === 'programs-table') {
                var params = new URLSearchParams(window.location.search);
                var form = document.getElementById('program-filters');
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
