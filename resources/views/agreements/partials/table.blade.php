@php
    $s    = $sort ?? 'name';
    $d    = $direction ?? 'asc';
    $flip = fn ($col) => ($s === $col && $d === 'asc') ? 'desc' : 'asc';
    $url  = fn ($col) => route('agreements.index', array_merge(request()->query(), ['sort' => $col, 'direction' => $flip($col), 'page' => 1]));
    $scopeEmptyLabel = fn ($mode, string $allLabel, string $noneLabel) => ($mode?->value ?? $mode) === 'none' ? $noneLabel : $allLabel;
@endphp

<div class="card shadow-sm app-index-table-card">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0 app-data-table app-cards">
            <thead>
                <tr>
                    <th>
                        <x-table-sort-link column="name" label="Agreement" :sort="$s" :direction="$d" :url="$url('name')" target="#agreements-table" />
                    </th>
                    <th>
                        <x-table-sort-link column="projects" label="Project" :sort="$s" :direction="$d" :url="$url('projects')" target="#agreements-table" />
                    </th>
                    <th>
                        <x-table-sort-link column="programs" label="Program" :sort="$s" :direction="$d" :url="$url('programs')" target="#agreements-table" />
                    </th>
                    <th>
                        <x-table-sort-link column="states" label="Location" :sort="$s" :direction="$d" :url="$url('states')" target="#agreements-table" />
                    </th>
                    <th class="text-nowrap">
                        <x-table-sort-link column="start_date" label="Start" :sort="$s" :direction="$d" :url="$url('start_date')" target="#agreements-table" />
                    </th>
                    <th class="text-nowrap">
                        <x-table-sort-link column="end_date" label="End" :sort="$s" :direction="$d" :url="$url('end_date')" target="#agreements-table" />
                    </th>
                    <th>
                        <x-table-sort-link column="active" label="Status" :sort="$s" :direction="$d" :url="$url('active')" target="#agreements-table" />
                    </th>
                    <th>
                        <x-table-sort-link column="principal_investigators" label="PI" :sort="$s" :direction="$d" :url="$url('principal_investigators')" target="#agreements-table" />
                    </th>
                    <th class="app-col-actions"><span class="visually-hidden">Actions</span></th>
                </tr>
            </thead>
            <tbody>
                @forelse($agreements as $agreement)
                <tr>
                    <td class="app-col-name">
                        @if($agreement->isLinkable())
                            <a href="{{ route('agreements.show', $agreement) }}" class="app-link app-link--strong" @if($agreement->abstract) title="{{ Str::limit($agreement->abstract, 200) }}" @endif>
                                {{ $agreement->name }}
                            </a>
                        @else
                            <span class="fw-semibold">{{ $agreement->name }}</span>
                        @endif
                    </td>
                    <td data-label="Projects">
                        <x-table-badge-list
                            kind="project"
                            :items="$agreement->projects"
                            route-name="projects.show"
                            :empty-label="$scopeEmptyLabel($agreement->program_scope_mode, 'All projects', 'No projects')"
                        />
                    </td>
                    <td data-label="Programs">
                        <x-table-badge-list
                            kind="program"
                            :items="$agreement->programs"
                            route-name="programs.show"
                            :empty-label="$scopeEmptyLabel($agreement->program_scope_mode, 'All programs', 'No programs')"
                        />
                    </td>
                    <td data-label="States">
                        <x-table-badge-list
                            kind="state"
                            :items="$agreement->states"
                            route-name="states.show"
                        />
                    </td>
                    <td class="text-nowrap" data-label="Start" @if($agreement->start_date) title="{{ $agreement->start_date->format('M d, Y') }}" @endif>{{ $agreement->start_date?->format('M Y') ?? '—' }}</td>
                    <td class="text-nowrap" data-label="End" @if($agreement->end_date) title="{{ $agreement->end_date->format('M d, Y') }}" @endif>{{ $agreement->end_date?->format('M Y') ?? '—' }}</td>
                    <td class="app-card-status">
                        <x-status-badge :active="$agreement->active" />
                    </td>
                    <td data-label="PI">
                        @if($agreement->principalInvestigators->isEmpty())
                            <span class="text-muted">—</span>
                        @else
                            {{ $agreement->principalInvestigators->sortBy('name')->pluck('name')->join(', ') }}
                        @endif
                    </td>
                    <td class="app-col-actions">
                        @php $actionKey = 'agreement-actions-' . $agreement->id; @endphp
                        <div class="dropdown app-row-actions d-inline-block">
                            <button type="button" class="btn btn-sm btn-link app-row-actions-toggle" data-bs-toggle="dropdown" data-bs-popper-config='{"strategy":"fixed"}' aria-expanded="false" aria-label="Agreement actions for {{ $agreement->name }}"><i class="bi bi-three-dots" aria-hidden="true"></i></button>
                            <div class="dropdown-menu dropdown-menu-end">
                            <a href="{{ route('agreements.show', $agreement) }}" class="dropdown-item"><i class="bi bi-eye me-2" aria-hidden="true"></i>View agreement</a>
                            @can('update', $agreement)
                                <a href="{{ route('agreements.edit', $agreement) }}" class="dropdown-item"><i class="bi bi-pencil-square me-2" aria-hidden="true"></i>Edit agreement</a>
                            @endcan
                            @can('duplicate', $agreement)
                                <button type="submit"
                                        form="{{ $actionKey }}-duplicate"
                                        onclick="return confirm('Duplicate {{ addslashes($agreement->name) }}? Activities and logged progress will not be copied.')" class="dropdown-item"><i class="bi bi-files me-2" aria-hidden="true"></i>Duplicate agreement</button>
                            @endcan
                            @can('delete', $agreement)
                                <hr class="dropdown-divider">
                            <button type="submit"
                                        form="{{ $actionKey }}-delete"
                                        onclick="return confirm('Delete {{ addslashes($agreement->name) }}?')" class="dropdown-item text-danger"><i class="bi bi-trash me-2" aria-hidden="true"></i>Delete agreement</button>
                            @endcan
                            </div>
                        </div>
                        @can('duplicate', $agreement)
                            <form id="{{ $actionKey }}-duplicate" method="POST" action="{{ route('agreements.duplicate', $agreement) }}" class="d-none">
                                @csrf
                            </form>
                        @endcan
                        @can('delete', $agreement)
                            <form id="{{ $actionKey }}-delete" method="POST" action="{{ route('agreements.destroy', $agreement) }}" class="d-none">
                                @csrf
                                @method('DELETE')
                            </form>
                        @endcan
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="9" class="text-center py-5">
                        <p class="text-muted mb-2">
                            @can('create', App\Models\Agreement::class) No agreements found.
                            @else You are not assigned to any agreements.
                            @endcan
                        </p>
                        @can('create', App\Models\Agreement::class)
                            <a href="{{ route('agreements.create') }}" class="btn btn-sm btn-primary">Create Agreement</a>
                        @endcan
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="card-footer">
        <x-htmx-pagination :paginator="$agreements" target="#agreements-table" />
    </div>
</div>
