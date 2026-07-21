@php
    $s    = $sort ?? 'name';
    $d    = $direction ?? 'asc';
    $flip = fn($col) => ($s === $col && $d === 'asc') ? 'desc' : 'asc';
    $url  = fn($col) => route('organizations.index', array_merge(request()->query(), ['sort' => $col, 'direction' => $flip($col), 'page' => 1]));
@endphp

<div class="card shadow-sm">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
                <tr>
                    <th>
                        <x-table-sort-link column="name" label="Name" :sort="$s" :direction="$d" :url="$url('name')" target="#organizations-table" />
                    </th>
                    <th>
                        <x-table-sort-link column="kfs" label="KFS" :sort="$s" :direction="$d" :url="$url('kfs')" target="#organizations-table" />
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
                    <th class="text-end fw-normal" style="width:{{ auth()->user()->isAdmin() ? '170px' : '80px' }};">Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse($organizations as $organization)
                <tr>
                    <td>
                        <a href="{{ route('organizations.show', $organization) }}" class="fw-semibold text-decoration-none text-dark">
                            {{ $organization->name }}
                        </a>
                    </td>
                    <td class="text-muted small">{{ $organization->kfs_number ?: '—' }}</td>
                    <td>
                        @foreach($organization->states->sortBy('name') as $state)
                            <span class="badge bg-info text-dark me-1">{{ $state->name }}</span>
                        @endforeach
                        @if($organization->states->isEmpty())<span class="text-muted">—</span>@endif
                    </td>
                    <td>
                        <div class="d-flex flex-wrap gap-1">
                            @forelse($organization->projects->sortBy('name') as $project)
                                <x-entity-relation-badge kind="project" :href="route('projects.show', $project)">
                                    {{ $project->name }}
                                </x-entity-relation-badge>
                            @empty
                                <span class="text-muted small">—</span>
                            @endforelse
                        </div>
                    </td>
                    <td>
                        <div class="d-flex flex-wrap gap-1">
                            @forelse($organization->programs->sortBy('name') as $program)
                                <x-entity-relation-badge kind="program" :href="route('programs.show', $program)">
                                    {{ $program->name }}
                                </x-entity-relation-badge>
                            @empty
                                <span class="text-muted small">—</span>
                            @endforelse
                        </div>
                    </td>
                    <td>
                        @if($organization->active)
                            <span class="badge bg-success">Active</span>
                        @else
                            <span class="badge bg-secondary">Inactive</span>
                        @endif
                    </td>
                    <td>
                        <span class="badge bg-secondary">{{ $organization->agreements_count ?? $organization->agreements->count() }}</span>
                    </td>
                    <td class="text-muted small">{{ $organization->created_at->format('M d, Y') }}</td>
                    <td class="text-end">
                        <div class="d-flex gap-1 justify-content-end flex-nowrap">
                            <a href="{{ route('organizations.show', $organization) }}" class="btn btn-sm btn-outline-primary">View</a>
                            @if(auth()->user()->isAdmin())
                                <a href="{{ route('organizations.edit', $organization) }}" class="btn btn-sm btn-outline-secondary">Edit</a>
                                <form method="POST" action="{{ route('organizations.destroy', $organization) }}" class="d-inline">
                                    @csrf @method('DELETE')
                                    <button type="submit" class="btn btn-sm btn-outline-danger"
                                        onclick="return confirm('Delete {{ addslashes($organization->name) }}?')">Delete</button>
                                </form>
                            @endif
                        </div>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="9" class="text-center py-5">
                        <p class="text-muted mb-2">No organizations found.</p>
                        @if(auth()->user()->isAdmin())
                            <a href="{{ route('organizations.create') }}" class="btn btn-sm btn-primary">Create Organization</a>
                        @endif
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="card-footer bg-white">
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
