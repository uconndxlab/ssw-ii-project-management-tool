@php
    $s    = $sort ?? 'name';
    $d    = $direction ?? 'asc';
    $flip = fn($col) => ($s === $col && $d === 'asc') ? 'desc' : 'asc';
    $url  = fn($col) => route('admin.users.index', array_merge(request()->query(), ['sort' => $col, 'direction' => $flip($col), 'page' => 1]));
@endphp

<div class="card shadow-sm">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
                <tr>
                    <th>
                        <x-table-sort-link column="name" label="Name" :sort="$s" :direction="$d" :url="$url('name')" />
                    </th>
                    <th>
                        <x-table-sort-link column="email" label="Email" :sort="$s" :direction="$d" :url="$url('email')" />
                    </th>
                    <th>
                        <x-table-sort-link column="role" label="Role" :sort="$s" :direction="$d" :url="$url('role')" />
                    </th>
                    <th>
                        <x-table-sort-link column="supervisor" label="Supervisor" :sort="$s" :direction="$d" :url="$url('supervisor')" />
                    </th>
                    <th>
                        <x-table-sort-link column="active" label="Active" :sort="$s" :direction="$d" :url="$url('active')" />
                    </th>
                    <th>
                        <x-table-sort-link column="projects" label="Projects" :sort="$s" :direction="$d" :url="$url('projects')" />
                    </th>
                    <th>
                        <x-table-sort-link column="programs" label="Programs" :sort="$s" :direction="$d" :url="$url('programs')" />
                    </th>
                    <th class="text-end fw-normal" style="width:220px;">Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse($users as $user)
                @php $scope = $user->getScopeBySource(); @endphp
                <tr>
                    <td>
                        <a href="{{ route('users.show', $user) }}" class="fw-semibold text-decoration-none text-dark">
                            {{ $user->name }}
                        </a>
                    </td>
                    <td class="text-muted small">{{ $user->email }}</td>
                    <td>
                        <span class="badge
                            @if($user->role === 'admin') bg-danger
                            @elseif($user->role === 'consultant') bg-info text-dark
                            @else bg-secondary
                            @endif">
                            {{ ucfirst($user->role) }}
                        </span>
                    </td>
                    <td class="small">
                        @if($user->supervisor)
                            <a href="{{ route('users.show', $user->supervisor) }}" class="text-decoration-none">
                                {{ $user->supervisor->name }}
                            </a>
                        @else
                            <span class="text-muted">—</span>
                        @endif
                    </td>
                    <td>
                        @if($user->active)
                            <span class="badge bg-success">Active</span>
                        @else
                            <span class="badge bg-secondary">Inactive</span>
                        @endif
                    </td>
                    <td>
                        <div class="d-flex flex-wrap gap-1">
                            @forelse($scope['index']['projects'] as $entry)
                                @php
                                    $project = $entry['model'];
                                    $viaTeamTitle = $entry['viaTeam'] && $entry['teamNames']
                                        ? 'Via team: ' . $entry['teamNames']
                                        : null;
                                @endphp
                                <x-entity-relation-badge
                                    kind="project"
                                    :href="route('projects.show', $project)"
                                    :title="$viaTeamTitle"
                                >{{ $project->name }}</x-entity-relation-badge>
                            @empty
                                <span class="text-muted small">—</span>
                            @endforelse
                        </div>
                    </td>
                    <td>
                        <div class="d-flex flex-wrap gap-1">
                            @forelse($scope['index']['programs'] as $entry)
                                @php
                                    $program = $entry['model'];
                                    $viaTeamTitle = $entry['viaTeam'] && $entry['teamNames']
                                        ? 'Via team: ' . $entry['teamNames']
                                        : null;
                                @endphp
                                <x-entity-relation-badge
                                    kind="program"
                                    :href="route('programs.show', $program)"
                                    :title="$viaTeamTitle"
                                >{{ $program->name }}</x-entity-relation-badge>
                            @empty
                                <span class="text-muted small">—</span>
                            @endforelse
                        </div>
                    </td>
                    <td class="text-end text-nowrap">
                        @php $actionKey = 'user-actions-' . $user->id; @endphp
                        <div class="btn-group btn-group-sm" role="group" aria-label="User actions for {{ $user->name }}">
                            <a href="{{ route('users.show', $user) }}"
                               class="btn btn-outline-primary"
                               data-bs-toggle="tooltip"
                               data-bs-title="View user"
                               aria-label="View user">
                                <i class="bi bi-eye"></i>
                            </a>
                            <a href="{{ route('admin.users.edit', $user) }}"
                               class="btn btn-outline-secondary"
                               data-bs-toggle="tooltip"
                               data-bs-title="Edit user"
                               aria-label="Edit user">
                                <i class="bi bi-pencil-square"></i>
                            </a>
                            <button type="submit"
                                    form="{{ $actionKey }}-delete"
                                    class="btn btn-outline-danger"
                                    data-bs-toggle="tooltip"
                                    data-bs-title="Delete user"
                                    aria-label="Delete user"
                                    onclick="return confirm('Delete {{ addslashes($user->name) }}?')">
                                <i class="bi bi-trash"></i>
                            </button>
                        </div>
                        <form id="{{ $actionKey }}-delete" method="POST" action="{{ route('admin.users.destroy', $user) }}" class="d-none">
                            @csrf
                            @method('DELETE')
                        </form>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="8" class="text-center py-5">
                        <p class="text-muted mb-2">No users found.</p>
                        <a href="{{ route('admin.users.create') }}" class="btn btn-sm btn-primary">Create User</a>
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="card-footer bg-white">
        <x-htmx-pagination :paginator="$users" target="#users-table" />
    </div>
</div>

@once
    <script>
    (function () {
        document.body.addEventListener('htmx:afterSwap', function (event) {
            if (event.target && event.target.id === 'users-table') {
                var params = new URLSearchParams(window.location.search);
                var form = document.getElementById('user-filters');
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
