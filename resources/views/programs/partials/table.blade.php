@php
    $s = $sort ?? 'name';
    $d = $direction ?? 'asc';
    $flip = fn ($col) => ($s === $col && $d === 'asc') ? 'desc' : 'asc';
    $url = fn ($col) => route('programs.index', array_merge(request()->query(), ['sort' => $col, 'direction' => $flip($col), 'page' => 1]));
@endphp

<div class="card shadow-sm app-index-table-card">
    <x-table-scroll>
        <table class="table table-hover align-middle mb-0">
            <thead>
                <tr>
                    <th>
                        <x-table-sort-link column="name" label="Name" :sort="$s" :direction="$d" :url="$url('name')" target="#programs-table" />
                    </th>
                    <th>Projects</th>
                    <th>
                        <x-table-sort-link column="status" label="Status" :sort="$s" :direction="$d" :url="$url('status')" target="#programs-table" />
                    </th>
                    <th class="text-nowrap">
                        <x-table-sort-link column="created" label="Created" :sort="$s" :direction="$d" :url="$url('created')" target="#programs-table" />
                    </th>
                    <th class="app-col-actions"><span class="visually-hidden">Actions</span></th>
                </tr>
            </thead>
            <tbody>
                @forelse($programs as $program)
                <tr>
                    <td>
                        <a href="{{ route('programs.show', $program) }}" class="app-link app-link--strong">
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
                        <x-status-badge :active="$program->active" />
                    </td>
                    <td class="text-muted small">{{ $program->created_at->format('M d, Y') }}</td>
                    <td class="app-col-actions">
                        @php $actionKey = 'program-actions-' . $program->id; @endphp
                        <div class="dropdown app-row-actions d-inline-block">
                            <button type="button" class="btn btn-sm btn-link app-row-actions-toggle" data-bs-toggle="dropdown" data-bs-popper-config='{"strategy":"fixed"}' aria-expanded="false" aria-label="Program actions for {{ $program->name }}"><i class="bi bi-three-dots" aria-hidden="true"></i></button>
                            <div class="dropdown-menu dropdown-menu-end">
                            <a href="{{ route('programs.show', $program) }}" class="dropdown-item"><i class="bi bi-eye me-2" aria-hidden="true"></i>View program</a>
                            @can('update', $program)
                            <a href="{{ route('programs.edit', $program) }}" class="dropdown-item"><i class="bi bi-pencil-square me-2" aria-hidden="true"></i>Edit program</a>
                            @endcan
                            @can('delete', $program)
                            <hr class="dropdown-divider">
                            <button type="submit"
                                    form="{{ $actionKey }}-delete"
                                    onclick="return confirm('Delete {{ addslashes($program->name) }}?')" class="dropdown-item text-danger"><i class="bi bi-trash me-2" aria-hidden="true"></i>Delete program</button>
                            @endcan
                            </div>
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
    </x-table-scroll>
    <div class="card-footer">
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
