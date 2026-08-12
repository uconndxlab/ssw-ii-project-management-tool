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
            <thead class="table-light">
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
                    <th class="text-end" style="width:170px;">Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse($teams as $team)
                <tr>
                    <td>
                        <a href="{{ route('teams.show', $team) }}" class="fw-semibold text-decoration-none text-dark">
                            {{ $team->name }}
                        </a>
                    </td>
                    <td>
                        <span class="badge bg-secondary">{{ $team->users_count }} {{ Str::plural('member', $team->users_count) }}</span>
                    </td>
                    <td>
                        @if($team->active)
                            <span class="badge bg-success">Active</span>
                        @else
                            <span class="badge bg-secondary">Inactive</span>
                        @endif
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
                    <td class="text-end text-nowrap">
                        @php $actionKey = 'team-actions-' . $team->id; @endphp
                        <div class="btn-group btn-group-sm" role="group" aria-label="Team actions for {{ $team->name }}">
                            <a href="{{ route('teams.show', $team) }}"
                               class="btn btn-outline-primary"
                               data-bs-toggle="tooltip"
                               data-bs-title="View team"
                               aria-label="View team">
                                <i class="bi bi-eye"></i>
                            </a>
                            <a href="{{ route('teams.edit', $team) }}"
                               class="btn btn-outline-secondary"
                               data-bs-toggle="tooltip"
                               data-bs-title="Edit team"
                               aria-label="Edit team">
                                <i class="bi bi-pencil-square"></i>
                            </a>
                            <button type="submit"
                                    form="{{ $actionKey }}-delete"
                                    class="btn btn-outline-danger"
                                    data-bs-toggle="tooltip"
                                    data-bs-title="Delete team"
                                    aria-label="Delete team"
                                    onclick="return confirm('Delete team {{ addslashes($team->name) }}?')">
                                <i class="bi bi-trash"></i>
                            </button>
                        </div>
                        <form id="{{ $actionKey }}-delete" method="POST" action="{{ route('teams.destroy', $team) }}" class="d-none">
                            @csrf
                            @method('DELETE')
                        </form>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="6" class="text-center py-5">
                        <p class="text-muted mb-2">No teams found.</p>
                        <a href="{{ route('teams.create') }}" class="btn btn-sm btn-primary">Create Team</a>
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="card-footer bg-white">
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
