@php
    $s    = $sort ?? 'name';
    $d    = $direction ?? 'asc';
    $superviseesIndex = $superviseesIndex ?? false;
    $usersIndexRoute = $superviseesIndex ? 'supervisees.index' : 'admin.users.index';
    $flip = fn($col) => ($s === $col && $d === 'asc') ? 'desc' : 'asc';
    $url  = fn($col) => route($usersIndexRoute, array_merge(request()->query(), ['sort' => $col, 'direction' => $flip($col), 'page' => 1]));
    $scopeEmptyLabel = fn ($mode, string $allLabel, string $noneLabel) => ($mode?->value ?? $mode) === 'none' ? $noneLabel : $allLabel;
@endphp

<div class="card shadow-sm app-index-table-card">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
                <tr>
                    <th style="min-width: 160px;">
                        <x-table-sort-link column="name" label="Name" :sort="$s" :direction="$d" :url="$url('name')" target="#users-table" />
                    </th>
                    <th style="min-width: 180px;">
                        <x-table-sort-link column="email" label="Email" :sort="$s" :direction="$d" :url="$url('email')" target="#users-table" />
                    </th>
                    <th>
                        <x-table-sort-link column="po" label="PO" :sort="$s" :direction="$d" :url="$url('po')" target="#users-table" />
                    </th>
                    <th>
                        <x-table-sort-link column="access_profile" label="Access" :sort="$s" :direction="$d" :url="$url('access_profile')" target="#users-table" />
                    </th>
                    <th style="min-width: 140px;">
                        <x-table-sort-link column="supervisor" label="Supervisor" :sort="$s" :direction="$d" :url="$url('supervisor')" target="#users-table" />
                    </th>
                    <th>
                        <x-table-sort-link column="active" label="Status" :sort="$s" :direction="$d" :url="$url('active')" target="#users-table" />
                    </th>
                    <th style="min-width: 140px;">
                        <x-table-sort-link column="projects" label="Projects" :sort="$s" :direction="$d" :url="$url('projects')" target="#users-table" />
                    </th>
                    <th style="min-width: 140px;">
                        <x-table-sort-link column="programs" label="Programs" :sort="$s" :direction="$d" :url="$url('programs')" target="#users-table" />
                    </th>
                    <th class="text-end text-nowrap" style="width:220px;">Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse($users as $user)
                @php $scope = $user->getScopeBySource(); @endphp
                <tr>
                    <td>
                        <x-user-link :user="$user" class="fw-semibold text-decoration-none text-dark d-block" />
                    </td>
                    <td class="text-muted small">{{ $user->email }}</td>
                    <td class="text-muted small">{{ $user->po_number ?: '—' }}</td>
                    <td>
                        <x-category-badge kind="role">{{ $user->accessLabel() }}</x-category-badge>
                    </td>
                    <td class="small">
                        @if($user->supervisor)
                            <x-user-link :user="$user->supervisor" class="text-decoration-none" />
                        @else
                            <span class="text-muted">—</span>
                        @endif
                    </td>
                    <td>
                        <x-status-badge :active="$user->active" />
                    </td>
                    <td>
                        <x-table-badge-list
                            kind="project"
                            :items="($user->program_scope_mode?->value ?? null) === 'all'
                                ? collect()
                                : collect($scope['index']['projects'])->map(fn ($entry) => [
                                'name' => $entry['model']->name,
                                'href' => $entry['model']->isLinkable() ? route('projects.show', $entry['model']) : null,
                                'title' => $entry['viaTeam'] && $entry['teamNames'] ? 'Via team: ' . $entry['teamNames'] : null,
                            ])"
                            href-key="href"
                            title-key="title"
                            :empty-label="$scopeEmptyLabel($user->program_scope_mode, 'All projects', 'No projects')"
                        />
                    </td>
                    <td>
                        <x-table-badge-list
                            kind="program"
                            :items="($user->program_scope_mode?->value ?? null) === 'all'
                                ? collect()
                                : collect($scope['index']['programs'])->map(fn ($entry) => [
                                'name' => $entry['model']->name,
                                'href' => $entry['model']->isLinkable() ? route('programs.show', $entry['model']) : null,
                                'title' => $entry['viaTeam'] && $entry['teamNames'] ? 'Via team: ' . $entry['teamNames'] : null,
                            ])"
                            href-key="href"
                            title-key="title"
                            :empty-label="$scopeEmptyLabel($user->program_scope_mode, 'All programs', 'No programs')"
                        />
                    </td>
                    <td class="text-end text-nowrap">
                        @php $actionKey = 'user-actions-' . $user->id; @endphp
                        <div class="btn-group btn-group-sm" role="group" aria-label="User actions for {{ $user->name }}">
                            @php $userHref = \App\Support\UserProfileLink::route($user); @endphp
                            @if($userHref)
                            <a href="{{ $userHref }}"
                               class="btn btn-outline-primary"
                               data-bs-toggle="tooltip"
                               data-bs-title="View user"
                               aria-label="View user">
                                <i class="bi bi-eye"></i>
                            </a>
                            @endif
                            @can('update', $user)
                            <a href="{{ route('admin.users.edit', $user) }}"
                               class="btn btn-outline-secondary"
                               data-bs-toggle="tooltip"
                               data-bs-title="Edit user"
                               aria-label="Edit user">
                                <i class="bi bi-pencil-square"></i>
                            </a>
                            @endcan
                            @can('delete', $user)
                            <button type="submit"
                                    form="{{ $actionKey }}-delete"
                                    class="btn btn-outline-danger"
                                    data-bs-toggle="tooltip"
                                    data-bs-title="Delete user"
                                    aria-label="Delete user"
                                    onclick="return confirm('Delete {{ addslashes($user->name) }}?')">
                                <i class="bi bi-trash"></i>
                            </button>
                            @endcan
                        </div>
                        @can('delete', $user)
                        <form id="{{ $actionKey }}-delete" method="POST" action="{{ route('admin.users.destroy', $user) }}" class="d-none">
                            @csrf
                            @method('DELETE')
                        </form>
                        @endcan
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="9" class="text-center py-5">
                        <p class="text-muted mb-2">No users found.</p>
                        @can('create', App\Models\User::class)
                            @unless($superviseesIndex)
                        <a href="{{ route('admin.users.create') }}" class="btn btn-sm btn-primary">Create User</a>
                            @endunless
                        @endcan
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
