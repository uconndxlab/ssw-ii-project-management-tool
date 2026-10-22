@php
    $s    = $sort ?? 'name';
    $d    = $direction ?? 'asc';
    $flip = fn($col) => ($s === $col && $d === 'asc') ? 'desc' : 'asc';
    $url  = fn($col) => route('teams.index', array_merge(request()->query(), ['sort' => $col, 'direction' => $flip($col), 'page' => 1]));
    $scopeEmptyLabel = fn ($mode, string $allLabel, string $noneLabel) => ($mode?->value ?? $mode) === 'none' ? $noneLabel : $allLabel;
@endphp

<div class="card shadow-sm app-index-table-card">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead>
                <tr>
                    <th>
                        <x-table-sort-link column="name" label="Name" :sort="$s" :direction="$d" :url="$url('name')" target="#teams-table" />
                    </th>
                    <th>
                        <x-table-sort-link column="members" label="Members" :sort="$s" :direction="$d" :url="$url('members')" target="#teams-table" />
                    </th>
                    <th>
                        <x-table-sort-link column="active" label="Status" :sort="$s" :direction="$d" :url="$url('active')" target="#teams-table" />
                    </th>
                    <th>
                        <x-table-sort-link column="projects" label="Projects" :sort="$s" :direction="$d" :url="$url('projects')" target="#teams-table" />
                    </th>
                    <th>
                        <x-table-sort-link column="programs" label="Programs" :sort="$s" :direction="$d" :url="$url('programs')" target="#teams-table" />
                    </th>
                    <th class="app-col-actions"><span class="visually-hidden">Actions</span></th>
                </tr>
            </thead>
            <tbody>
                @forelse($teams as $team)
                <tr>
                    <td>
                        <a href="{{ route('teams.show', $team) }}" class="app-link app-link--strong">
                            {{ $team->name }}
                        </a>
                    </td>
                    <td>
                        <span class="app-count">{{ $team->users_count }} {{ Str::plural('member', $team->users_count) }}</span>
                    </td>
                    <td>
                        <x-status-badge :active="$team->active" />
                    </td>
                    <td>
                        <x-table-badge-list
                            kind="project"
                            :items="$team->projects"
                            route-name="projects.show"
                            :empty-label="$scopeEmptyLabel($team->program_scope_mode, 'All projects', 'No projects')"
                        />
                    </td>
                    <td>
                        <x-table-badge-list
                            kind="program"
                            :items="$team->programs"
                            route-name="programs.show"
                            :empty-label="$scopeEmptyLabel($team->program_scope_mode, 'All programs', 'No programs')"
                        />
                    </td>
                    <td class="app-col-actions">
                        @php $actionKey = 'team-actions-' . $team->id; @endphp
                        <div class="dropdown app-row-actions d-inline-block">
                            <button type="button" class="btn btn-sm btn-link app-row-actions-toggle" data-bs-toggle="dropdown" data-bs-popper-config='{"strategy":"fixed"}' aria-expanded="false" aria-label="Team actions for {{ $team->name }}"><i class="bi bi-three-dots" aria-hidden="true"></i></button>
                            <div class="dropdown-menu dropdown-menu-end">
                            <a href="{{ route('teams.show', $team) }}" class="dropdown-item"><i class="bi bi-eye me-2" aria-hidden="true"></i>View team</a>
                            @can('update', $team)
                            <a href="{{ route('teams.edit', $team) }}" class="dropdown-item"><i class="bi bi-pencil-square me-2" aria-hidden="true"></i>Edit team</a>
                            @endcan
                            @can('delete', $team)
                            <hr class="dropdown-divider">
                            <button type="submit"
                                    form="{{ $actionKey }}-delete"
                                    onclick="return confirm('Delete team {{ addslashes($team->name) }}?')" class="dropdown-item text-danger"><i class="bi bi-trash me-2" aria-hidden="true"></i>Delete team</button>
                            @endcan
                            </div>
                        </div>
                        @can('delete', $team)
                        <form id="{{ $actionKey }}-delete" method="POST" action="{{ route('teams.destroy', $team) }}" class="d-none">
                            @csrf
                            @method('DELETE')
                        </form>
                        @endcan
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="6" class="text-center py-5">
                        <p class="text-muted mb-2">No teams found.</p>
                        @can('create', App\Models\Team::class)
                        <a href="{{ route('teams.create') }}" class="btn btn-sm btn-primary">Create Team</a>
                        @endcan
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="card-footer">
        <x-htmx-pagination :paginator="$teams" target="#teams-table" />
    </div>
</div>

@once
    <script>
    (function () {
        document.body.addEventListener('htmx:afterSwap', function (event) {
            if (event.target && event.target.id === 'teams-table') {
                var params = new URLSearchParams(window.location.search);
                var form = document.getElementById('team-filters');
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
