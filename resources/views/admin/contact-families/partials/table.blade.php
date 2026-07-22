@php
    $s    = $sort ?? 'sort_order';
    $d    = $direction ?? 'asc';
    $flip = fn($col) => ($s === $col && $d === 'asc') ? 'desc' : 'asc';
    $url  = fn($col) => route('contact-families.index', array_merge(request()->query(), ['sort' => $col, 'direction' => $flip($col)]));
@endphp

<div class="card shadow-sm">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
                <tr>
                    <th>
                        <x-table-sort-link column="name" label="Name" :sort="$s" :direction="$d" :url="$url('name')" target="#cf-table" />
                    </th>
                    <th>
                        <x-table-sort-link column="projects" label="Projects" :sort="$s" :direction="$d" :url="$url('projects')" target="#cf-table" />
                    </th>
                    <th>
                        <x-table-sort-link column="programs" label="Programs" :sort="$s" :direction="$d" :url="$url('programs')" target="#cf-table" />
                    </th>
                    <th>
                        <x-table-sort-link column="activity_types" label="Activity Types" :sort="$s" :direction="$d" :url="$url('activity_types')" target="#cf-table" />
                    </th>
                    <th>
                        <x-table-sort-link column="active" label="Status" :sort="$s" :direction="$d" :url="$url('active')" target="#cf-table" />
                    </th>
                    <th class="text-end fw-normal" style="width:140px;">Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse($contactFamilies as $family)
                <tr>
                    <td class="fw-semibold">{{ $family->name }}</td>
                    <td>
                        @forelse($family->projects->sortBy('name') as $project)
                            <x-entity-relation-badge kind="project" :href="route('projects.show', $project)" class="me-1 mb-1">{{ $project->name }}</x-entity-relation-badge>
                        @empty
                            <span class="text-muted small">All projects</span>
                        @endforelse
                    </td>
                    <td>
                        @forelse($family->programs->sortBy('name') as $program)
                            <x-entity-relation-badge kind="program" :href="route('programs.show', $program)" class="me-1 mb-1">{{ $program->name }}</x-entity-relation-badge>
                        @empty
                            <span class="text-muted small">All programs</span>
                        @endforelse
                    </td>
                    <td><span class="badge bg-secondary">{{ $family->activity_types_count }}</span></td>
                    <td>
                        @if($family->active)
                            <span class="badge bg-success">Active</span>
                        @else
                            <span class="badge bg-secondary">Inactive</span>
                        @endif
                    </td>
                    <td class="text-end text-nowrap">
                        @php $actionKey = 'contact-family-actions-' . $family->id; @endphp
                        <div class="btn-group btn-group-sm" role="group" aria-label="Contact family actions for {{ $family->name }}">
                            <a href="{{ route('contact-families.edit', $family) }}"
                               class="btn btn-outline-secondary"
                               data-bs-toggle="tooltip"
                               data-bs-title="Edit contact family"
                               aria-label="Edit contact family">
                                <i class="bi bi-pencil-square"></i>
                            </a>
                            <button type="submit"
                                    form="{{ $actionKey }}-delete"
                                    class="btn btn-outline-danger"
                                    data-bs-toggle="tooltip"
                                    data-bs-title="Delete contact family"
                                    aria-label="Delete contact family"
                                    onclick="return confirm('Delete {{ addslashes($family->name) }}?')">
                                <i class="bi bi-trash"></i>
                            </button>
                        </div>
                        <form id="{{ $actionKey }}-delete" method="POST" action="{{ route('contact-families.destroy', $family) }}" class="d-none">
                            @csrf
                            @method('DELETE')
                        </form>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="7" class="text-center py-5">
                        <p class="text-muted mb-2">No contact families found.</p>
                        <a href="{{ route('contact-families.create') }}" class="btn btn-sm btn-primary">Add Contact Family</a>
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

@once
    <script>
    (function () {
        document.body.addEventListener('htmx:afterSwap', function (event) {
            if (event.target && event.target.id === 'cf-table') {
                var params = new URLSearchParams(window.location.search);
                var form = document.getElementById('cf-filters');
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
