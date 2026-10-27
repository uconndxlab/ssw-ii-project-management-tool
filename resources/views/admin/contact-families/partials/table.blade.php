@php
    $s    = $sort ?? 'sort_order';
    $d    = $direction ?? 'asc';
    $flip = fn($col) => ($s === $col && $d === 'asc') ? 'desc' : 'asc';
    $url  = fn($col) => route('contact-families.index', array_merge(request()->query(), ['sort' => $col, 'direction' => $flip($col), 'page' => 1]));
    $scopeEmptyLabel = fn ($mode, string $allLabel, string $noneLabel) => ($mode?->value ?? $mode) === 'none' ? $noneLabel : $allLabel;
@endphp

<div class="card shadow-sm app-index-table-card">
    <x-table-scroll>
        <table class="table table-hover align-middle mb-0 app-cards">
            <thead>
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
                    <th class="app-col-actions"><span class="visually-hidden">Actions</span></th>
                </tr>
            </thead>
            <tbody>
                @forelse($contactFamilies as $family)
                <tr>
                    <td class="app-card-lead fw-semibold">{{ $family->name }}</td>
                    <td data-label="Projects">
                        <x-table-badge-list
                            kind="project"
                            :items="$family->projects"
                            route-name="projects.show"
                            :empty-label="$scopeEmptyLabel($family->program_scope_mode, 'All projects', 'No projects')"
                        />
                    </td>
                    <td data-label="Programs">
                        <x-table-badge-list
                            kind="program"
                            :items="$family->programs"
                            route-name="programs.show"
                            :empty-label="$scopeEmptyLabel($family->program_scope_mode, 'All programs', 'No programs')"
                        />
                    </td>
                    <td data-label="Activity types"><span class="app-count">{{ $family->activity_types_count }}</span></td>
                    <td class="app-card-status">
                        <x-status-badge :active="$family->active" />
                    </td>
                    <td class="app-col-actions">
                        @php $actionKey = 'contact-family-actions-' . $family->id; @endphp
                        <div class="dropdown app-row-actions d-inline-block">
                            <button type="button" class="btn btn-sm btn-link app-row-actions-toggle" data-bs-toggle="dropdown" data-bs-popper-config='{"strategy":"fixed"}' aria-expanded="false" aria-label="Activity family actions for {{ $family->name }}"><i class="bi bi-three-dots" aria-hidden="true"></i></button>
                            <div class="dropdown-menu dropdown-menu-end">
                            @can('update', $family)
                            <a href="{{ route('contact-families.edit', $family) }}" class="dropdown-item"><i class="bi bi-pencil-square me-2" aria-hidden="true"></i>Edit activity family</a>
                            @endcan
                            @can('delete', $family)
                            <hr class="dropdown-divider">
                            <button type="submit"
                                    form="{{ $actionKey }}-delete"
                                    data-confirm
                                    data-confirm-title="Delete activity family?"
                                    data-confirm-record="{{ $family->name }}"
                                    data-confirm-message="This action cannot be undone."
                                    data-confirm-action="Delete activity family" class="dropdown-item text-danger"><i class="bi bi-trash me-2" aria-hidden="true"></i>Delete activity family</button>
                            @endcan
                            </div>
                        </div>
                        @can('delete', $family)
                        <form id="{{ $actionKey }}-delete" method="POST" action="{{ route('contact-families.destroy', $family) }}" class="d-none">
                            @csrf
                            @method('DELETE')
                        </form>
                        @endcan
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="7" class="text-center py-5">
                        <p class="text-muted mb-2">No activity families found.</p>
                        @can('create', App\Models\ContactFamily::class)
                        <a href="{{ route('contact-families.create') }}" class="btn btn-sm btn-primary">Add Activity Family</a>
                        @endcan
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </x-table-scroll>
    <div class="card-footer">
        <x-htmx-pagination :paginator="$contactFamilies" target="#cf-table" />
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
