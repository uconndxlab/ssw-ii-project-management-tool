@php
    $s = $sort ?? 'name';
    $d = $direction ?? 'asc';
    $flip = fn ($col) => ($s === $col && $d === 'asc') ? 'desc' : 'asc';
    $url = fn ($col) => route('programs.index', array_merge(request()->query(), ['sort' => $col, 'direction' => $flip($col), 'page' => 1]));
@endphp

<div class="card shadow-sm app-index-table-card" data-table-sort-sync>
    <x-table-scroll>
        <table class="table table-hover align-middle mb-0 app-cards">
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
                    <td class="app-card-lead">
                        <a href="{{ route('programs.show', $program) }}" class="app-link app-link--strong app-row-link">
                            {{ $program->name }}
                        </a>
                    </td>
                    <td data-label="Projects">
                        <x-table-badge-list
                            kind="project"
                            :items="$program->projects"
                            route-name="projects.show"
                        />
                    </td>
                    <td class="app-card-status">
                        <x-status-badge :active="$program->active" />
                    </td>
                    <td data-label="Created" class="text-muted small">{{ $program->created_at->format('M d, Y') }}</td>
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
                                    data-confirm
                                    data-confirm-title="Delete program?"
                                    data-confirm-record="{{ $program->name }}"
                                    data-confirm-message="This action cannot be undone."
                                    data-confirm-action="Delete program" class="dropdown-item text-danger"><i class="bi bi-trash me-2" aria-hidden="true"></i>Delete program</button>
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
                <x-table-empty colspan="5" plural="programs" index-route="programs.index">
                    <p class="text-muted mb-2">No programs found.</p>
                    @can('create', App\Models\Program::class)
                    <a href="{{ route('programs.create') }}" class="btn btn-sm btn-primary">Create Program</a>
                    @endcan
                </x-table-empty>
                @endforelse
            </tbody>
        </table>
    </x-table-scroll>
    <div class="card-footer">
        <x-htmx-pagination :paginator="$programs" target="#programs-table" />
    </div>
</div>
