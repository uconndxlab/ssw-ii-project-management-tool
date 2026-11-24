@php
    $s    = $sort ?? 'name';
    $d    = $direction ?? 'asc';
    $superviseesIndex = $superviseesIndex ?? false;
    $usersIndexRoute = $superviseesIndex ? 'supervisees.index' : 'admin.users.index';
    $flip = fn($col) => ($s === $col && $d === 'asc') ? 'desc' : 'asc';
    $url  = fn($col) => route($usersIndexRoute, array_merge(request()->query(), ['sort' => $col, 'direction' => $flip($col), 'page' => 1]));
    $scopeEmptyLabel = fn ($mode, string $allLabel, string $noneLabel) => ($mode?->value ?? $mode) === 'none' ? $noneLabel : $allLabel;
@endphp

<div class="card shadow-sm app-index-table-card" data-table-sort-sync>
    <x-table-scroll>
        <table class="table table-hover align-middle mb-0 app-cards">
            <thead>
                <tr>
                    <th>
                        <x-table-sort-link column="name" label="Name" :sort="$s" :direction="$d" :url="$url('name')" target="#users-table" />
                    </th>
                    <th>
                        <x-table-sort-link column="email" label="Email" :sort="$s" :direction="$d" :url="$url('email')" target="#users-table" />
                    </th>
                    <th>
                        <x-table-sort-link column="po" label="PO" :sort="$s" :direction="$d" :url="$url('po')" target="#users-table" />
                    </th>
                    <th>
                        <x-table-sort-link column="access_profile" label="Access" :sort="$s" :direction="$d" :url="$url('access_profile')" target="#users-table" />
                    </th>
                    <th>
                        <x-table-sort-link column="supervisor" label="Supervisor" :sort="$s" :direction="$d" :url="$url('supervisor')" target="#users-table" />
                    </th>
                    <th>
                        <x-table-sort-link column="active" label="Status" :sort="$s" :direction="$d" :url="$url('active')" target="#users-table" />
                    </th>
                    <th>
                        <x-table-sort-link column="projects" label="Projects" :sort="$s" :direction="$d" :url="$url('projects')" target="#users-table" />
                    </th>
                    <th>
                        <x-table-sort-link column="programs" label="Programs" :sort="$s" :direction="$d" :url="$url('programs')" target="#users-table" />
                    </th>
                    <th class="app-col-actions"><span class="visually-hidden">Actions</span></th>
                </tr>
            </thead>
            <tbody>
                @forelse($users as $user)
                @php $scope = $user->getScopeBySource(); @endphp
                <tr>
                    <td class="app-card-lead">
                        <x-user-link :user="$user" class="app-link app-link--strong app-row-link d-block" />
                    </td>
                    <td data-label="Email" class="text-muted small">{{ $user->email }}</td>
                    <td data-label="PO" class="text-muted small">{{ $user->po_number ?: '—' }}</td>
                    <td data-label="Access">
                        <x-category-badge kind="role">{{ $user->accessLabel() }}</x-category-badge>
                    </td>
                    <td data-label="Supervisor" class="small">
                        @if($user->supervisor)
                            <x-user-link :user="$user->supervisor" class="text-decoration-none" />
                        @else
                            <span class="text-muted">—</span>
                        @endif
                    </td>
                    <td class="app-card-status">
                        <x-status-badge :active="$user->active" />
                    </td>
                    <td data-label="Projects">
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
                    <td data-label="Programs">
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
                    <td class="app-col-actions">
                        @php $actionKey = 'user-actions-' . $user->id; @endphp
                        <div class="dropdown app-row-actions d-inline-block">
                            <button type="button" class="btn btn-sm btn-link app-row-actions-toggle" data-bs-toggle="dropdown" data-bs-popper-config='{"strategy":"fixed"}' aria-expanded="false" aria-label="User actions for {{ $user->name }}"><i class="bi bi-three-dots" aria-hidden="true"></i></button>
                            <div class="dropdown-menu dropdown-menu-end">
                            @php $userHref = \App\Support\UserProfileLink::route($user); @endphp
                            @if($userHref)
                            <a href="{{ $userHref }}" class="dropdown-item"><i class="bi bi-eye me-2" aria-hidden="true"></i>View user</a>
                            @endif
                            @can('update', $user)
                            <a href="{{ route('admin.users.edit', $user) }}" class="dropdown-item"><i class="bi bi-pencil-square me-2" aria-hidden="true"></i>Edit user</a>
                            @endcan
                            @can('delete', $user)
                            <hr class="dropdown-divider">
                            <button type="submit"
                                    form="{{ $actionKey }}-delete"
                                    data-confirm
                                    data-confirm-title="Delete user?"
                                    data-confirm-record="{{ $user->name }}"
                                    data-confirm-message="This action cannot be undone."
                                    data-confirm-action="Delete user" class="dropdown-item text-danger"><i class="bi bi-trash me-2" aria-hidden="true"></i>Delete user</button>
                            @endcan
                            </div>
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
                <x-table-empty colspan="9" :plural="$superviseesIndex ? 'supervisees' : 'users'" :index-route="$usersIndexRoute">
                    <p class="text-muted mb-2">No users found.</p>
                    @can('create', App\Models\User::class)
                        @unless($superviseesIndex)
                            <a href="{{ route('admin.users.create') }}" class="btn btn-sm btn-primary">Create User</a>
                        @endunless
                    @endcan
                </x-table-empty>
                @endforelse
            </tbody>
        </table>
    </x-table-scroll>
    <div class="card-footer">
        <x-htmx-pagination :paginator="$users" target="#users-table" />
    </div>
</div>
