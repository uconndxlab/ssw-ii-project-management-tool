@php
    $s    = $sort ?? 'name';
    $d    = $direction ?? 'asc';
    $flip = fn($col) => ($s === $col && $d === 'asc') ? 'desc' : 'asc';
    $icon = fn($col) => $s === $col ? ($d === 'asc' ? ' ↑' : ' ↓') : '';
    $url  = fn($col) => route('admin.users.index', array_merge(request()->query(), ['sort' => $col, 'direction' => $flip($col), 'page' => 1]));
@endphp

<div class="card shadow-sm">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
                <tr>
                    <th>
                        <a class="text-decoration-none fw-semibold text-dark"
                           href="{{ $url('name') }}"
                           hx-get="{{ $url('name') }}" hx-target="#users-table" hx-push-url="true">
                            Name{!! $icon('name') !!}
                        </a>
                    </th>
                    <th>
                        <a class="text-decoration-none fw-semibold text-dark"
                           href="{{ $url('email') }}"
                           hx-get="{{ $url('email') }}" hx-target="#users-table" hx-push-url="true">
                            Email{!! $icon('email') !!}
                        </a>
                    </th>
                    <th>
                        <a class="text-decoration-none fw-semibold text-dark"
                           href="{{ $url('role') }}"
                           hx-get="{{ $url('role') }}" hx-target="#users-table" hx-push-url="true">
                            Role{!! $icon('role') !!}
                        </a>
                    </th>
                    <th>Projects</th>
                    <th>Programs</th>
                    <th>
                        <a class="text-decoration-none fw-semibold text-dark"
                           href="{{ $url('created') }}"
                           hx-get="{{ $url('created') }}" hx-target="#users-table" hx-push-url="true">
                            Created{!! $icon('created') !!}
                        </a>
                    </th>
                    <th class="text-end" style="width:220px;">Actions</th>
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
                    <td class="text-muted small">{{ $user->created_at->format('M d, Y') }}</td>
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
                    <td colspan="7" class="text-center py-5">
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
        function initUsersTableTooltips(scope) {
            if (!window.bootstrap || !bootstrap.Tooltip) {
                return;
            }

            (scope || document).querySelectorAll('#users-table [data-bs-toggle="tooltip"]').forEach(function (element) {
                bootstrap.Tooltip.getOrCreateInstance(element);
            });
        }

        document.addEventListener('DOMContentLoaded', function () {
            initUsersTableTooltips();
        });

        document.body.addEventListener('htmx:afterSwap', function (event) {
            if (event.target && event.target.id === 'users-table') {
                initUsersTableTooltips(event.target);
            }
        });
    })();
    </script>
@endonce
