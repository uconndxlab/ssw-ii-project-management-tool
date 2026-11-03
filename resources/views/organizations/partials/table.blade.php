@php
    $s    = $sort ?? 'name';
    $d    = $direction ?? 'asc';
    $flip = fn($col) => ($s === $col && $d === 'asc') ? 'desc' : 'asc';
    $url  = fn($col) => route('organizations.index', array_merge(request()->query(), ['sort' => $col, 'direction' => $flip($col), 'page' => 1]));
    $scopeEmptyLabel = fn ($mode, string $allLabel, string $noneLabel) => ($mode?->value ?? $mode) === 'none' ? $noneLabel : $allLabel;
@endphp

<div class="card shadow-sm app-index-table-card">
    <x-table-scroll>
        <table class="table table-hover align-middle mb-0 app-cards">
            <thead>
                <tr>
                    <th>
                        <x-table-sort-link column="name" label="Name" :sort="$s" :direction="$d" :url="$url('name')" target="#organizations-table" />
                    </th>
                    <th>
                        <x-table-sort-link column="po" label="PO" :sort="$s" :direction="$d" :url="$url('po')" target="#organizations-table" />
                    </th>
                    <th>
                        <x-table-sort-link column="states" label="State(s)" :sort="$s" :direction="$d" :url="$url('states')" target="#organizations-table" />
                    </th>
                    <th>
                        <x-table-sort-link column="projects" label="Projects" :sort="$s" :direction="$d" :url="$url('projects')" target="#organizations-table" />
                    </th>
                    <th>
                        <x-table-sort-link column="programs" label="Programs" :sort="$s" :direction="$d" :url="$url('programs')" target="#organizations-table" />
                    </th>
                    <th>
                        <x-table-sort-link column="status" label="Status" :sort="$s" :direction="$d" :url="$url('status')" target="#organizations-table" />
                    </th>
                    <th>
                        <x-table-sort-link column="agreements" label="Agreements" :sort="$s" :direction="$d" :url="$url('agreements')" target="#organizations-table" />
                    </th>
                    <th>
                        <x-table-sort-link column="created" label="Created" :sort="$s" :direction="$d" :url="$url('created')" target="#organizations-table" />
                    </th>
                    <th class="app-col-actions"><span class="visually-hidden">Actions</span></th>
                </tr>
            </thead>
            <tbody>
                @forelse($organizations as $organization)
                <tr>
                    <td class="app-card-lead">
                        <a href="{{ route('organizations.show', $organization) }}" class="app-link app-link--strong app-row-link">
                            {{ $organization->name }}
                        </a>
                    </td>
                    <td data-label="PO" class="text-muted small">{{ $organization->po_number ?: '—' }}</td>
                    <td data-label="States">
                        <x-table-badge-list
                            kind="state"
                            :items="$organization->states"
                            route-name="states.show"
                        />
                    </td>
                    <td data-label="Projects">
                        <x-table-badge-list
                            kind="project"
                            :items="$organization->projects"
                            route-name="projects.show"
                            :empty-label="$scopeEmptyLabel($organization->program_scope_mode, 'All projects', 'No projects')"
                        />
                    </td>
                    <td data-label="Programs">
                        <x-table-badge-list
                            kind="program"
                            :items="$organization->programs"
                            route-name="programs.show"
                            :empty-label="$scopeEmptyLabel($organization->program_scope_mode, 'All programs', 'No programs')"
                        />
                    </td>
                    <td class="app-card-status">
                        <x-status-badge :active="$organization->active" />
                    </td>
                    <td data-label="Agreements">
                        <span class="app-count">{{ $organization->agreements_count ?? $organization->agreements->count() }}</span>
                    </td>
                    <td data-label="Created" class="text-muted small">{{ $organization->created_at->format('M d, Y') }}</td>
                    <td class="app-col-actions">
                        @php $actionKey = 'organization-actions-' . $organization->id; @endphp
                        <div class="dropdown app-row-actions d-inline-block">
                            <button type="button" class="btn btn-sm btn-link app-row-actions-toggle" data-bs-toggle="dropdown" data-bs-popper-config='{"strategy":"fixed"}' aria-expanded="false" aria-label="Organization actions for {{ $organization->name }}"><i class="bi bi-three-dots" aria-hidden="true"></i></button>
                            <div class="dropdown-menu dropdown-menu-end">
                            <a href="{{ route('organizations.show', $organization) }}" class="dropdown-item"><i class="bi bi-eye me-2" aria-hidden="true"></i>View organization</a>
                            @can('update', $organization)
                                <a href="{{ route('organizations.edit', $organization) }}" class="dropdown-item"><i class="bi bi-pencil-square me-2" aria-hidden="true"></i>Edit organization</a>
                            @endcan
                            @can('delete', $organization)
                                <hr class="dropdown-divider">
                            <button type="submit"
                                        form="{{ $actionKey }}-delete"
                                        data-confirm
                                        data-confirm-title="Delete organization?"
                                        data-confirm-record="{{ $organization->name }}"
                                        data-confirm-message="This action cannot be undone."
                                        data-confirm-action="Delete organization" class="dropdown-item text-danger"><i class="bi bi-trash me-2" aria-hidden="true"></i>Delete organization</button>
                            @endcan
                            </div>
                        </div>
                        @can('delete', $organization)
                            <form id="{{ $actionKey }}-delete" method="POST" action="{{ route('organizations.destroy', $organization) }}" class="d-none">
                                @csrf
                                @method('DELETE')
                            </form>
                        @endcan
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="9" class="text-center py-5">
                        <p class="text-muted mb-2">No organizations found.</p>
                        @can('create', App\Models\Organization::class)
                            <a href="{{ route('organizations.create') }}" class="btn btn-sm btn-primary">Create Organization</a>
                        @endcan
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </x-table-scroll>
    <div class="card-footer">
        <x-htmx-pagination :paginator="$organizations" target="#organizations-table" />
    </div>
</div>

@once
    <script>
    (function () {
        document.body.addEventListener('htmx:afterSwap', function (event) {
            if (event.target && event.target.id === 'organizations-table') {
                var params = new URLSearchParams(window.location.search);
                var form = document.getElementById('organization-filters');
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
