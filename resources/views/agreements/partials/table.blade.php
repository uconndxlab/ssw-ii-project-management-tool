@php
    $s    = $sort ?? 'name';
    $d    = $direction ?? 'asc';
    $flip = fn ($col) => ($s === $col && $d === 'asc') ? 'desc' : 'asc';
    $url  = fn ($col) => route('agreements.index', array_merge(request()->query(), ['sort' => $col, 'direction' => $flip($col), 'page' => 1]));
@endphp

<div class="card shadow-sm">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
                <tr>
                    <th style="min-width: 200px;">
                        <x-table-sort-link column="name" label="Agreement" :sort="$s" :direction="$d" :url="$url('name')" target="#agreements-table" />
                    </th>
                    <th style="min-width: 140px;">
                        <x-table-sort-link column="projects" label="Project" :sort="$s" :direction="$d" :url="$url('projects')" target="#agreements-table" />
                    </th>
                    <th style="min-width: 140px;">
                        <x-table-sort-link column="programs" label="Program" :sort="$s" :direction="$d" :url="$url('programs')" target="#agreements-table" />
                    </th>
                    <th style="min-width: 120px;">
                        <x-table-sort-link column="states" label="Location" :sort="$s" :direction="$d" :url="$url('states')" target="#agreements-table" />
                    </th>
                    <th class="text-nowrap">
                        <x-table-sort-link column="start_date" label="Start Date" :sort="$s" :direction="$d" :url="$url('start_date')" target="#agreements-table" />
                    </th>
                    <th class="text-nowrap">
                        <x-table-sort-link column="end_date" label="End Date" :sort="$s" :direction="$d" :url="$url('end_date')" target="#agreements-table" />
                    </th>
                    <th>
                        <x-table-sort-link column="active" label="Status" :sort="$s" :direction="$d" :url="$url('active')" target="#agreements-table" />
                    </th>
                    <th style="min-width: 120px;">
                        <x-table-sort-link column="principal_investigators" label="PI" :sort="$s" :direction="$d" :url="$url('principal_investigators')" target="#agreements-table" />
                    </th>
                    <th class="text-end text-nowrap" style="width:{{ auth()->user()->isAdmin() ? '130px' : '52px' }};">Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse($agreements as $agreement)
                <tr>
                    <td>
                        @if($agreement->isLinkable())
                            <a href="{{ route('agreements.show', $agreement) }}" class="fw-semibold text-decoration-none text-dark d-block">
                                {{ $agreement->name }}
                            </a>
                        @else
                            <span class="fw-semibold text-dark d-block">{{ $agreement->name }}</span>
                        @endif
                        @if($agreement->abstract)
                            <div class="text-muted small text-truncate" style="max-width: 320px;" title="{{ $agreement->abstract }}">
                                {{ $agreement->abstract }}
                            </div>
                        @endif
                    </td>
                    <td>
                        <div class="d-flex flex-wrap gap-1">
                            @forelse($agreement->projects->sortBy('name') as $project)
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
                            @forelse($agreement->programs->sortBy('name') as $program)
                                <x-entity-relation-badge kind="program" :href="route('programs.show', $program)">
                                    {{ $program->name }}
                                </x-entity-relation-badge>
                            @empty
                                <span class="text-muted small">—</span>
                            @endforelse
                        </div>
                    </td>
                    <td>
                        <div class="d-flex flex-wrap gap-1">
                            @forelse($agreement->states as $state)
                                <span class="badge bg-info text-dark">{{ $state->name }}</span>
                            @empty
                                <span class="text-muted small">—</span>
                            @endforelse
                        </div>
                    </td>
                    <td class="small text-nowrap">{{ $agreement->start_date?->format('M d, Y') ?? '—' }}</td>
                    <td class="small text-nowrap">{{ $agreement->end_date?->format('M d, Y') ?? '—' }}</td>
                    <td>
                        @if($agreement->active)
                            <span class="badge bg-success">Active</span>
                        @else
                            <span class="badge bg-secondary">Inactive</span>
                        @endif
                    </td>
                    <td class="small">
                        @if($agreement->principalInvestigators->isEmpty())
                            <span class="text-muted">—</span>
                        @else
                            {{ $agreement->principalInvestigators->sortBy('name')->pluck('name')->join(', ') }}
                        @endif
                    </td>
                    <td class="text-end text-nowrap">
                        @php $actionKey = 'agreement-actions-' . $agreement->id; @endphp
                        <div class="btn-group btn-group-sm" role="group" aria-label="Agreement actions for {{ $agreement->name }}">
                            <a href="{{ route('agreements.show', $agreement) }}"
                               class="btn btn-outline-primary"
                               data-bs-toggle="tooltip"
                               data-bs-title="View agreement"
                               aria-label="View agreement">
                                <i class="bi bi-eye"></i>
                            </a>
                            @if(auth()->user()->isAdmin())
                                <a href="{{ route('agreements.edit', $agreement) }}"
                                   class="btn btn-outline-secondary"
                                   data-bs-toggle="tooltip"
                                   data-bs-title="Edit agreement"
                                   aria-label="Edit agreement">
                                    <i class="bi bi-pencil-square"></i>
                                </a>
                                <button type="submit"
                                        form="{{ $actionKey }}-duplicate"
                                        class="btn btn-outline-secondary"
                                        data-bs-toggle="tooltip"
                                        data-bs-title="Duplicate agreement"
                                        aria-label="Duplicate agreement"
                                        onclick="return confirm('Duplicate {{ addslashes($agreement->name) }}? Activities and logged progress will not be copied.')">
                                    <i class="bi bi-files"></i>
                                </button>
                                <button type="submit"
                                        form="{{ $actionKey }}-delete"
                                        class="btn btn-outline-danger"
                                        data-bs-toggle="tooltip"
                                        data-bs-title="Delete agreement"
                                        aria-label="Delete agreement"
                                        onclick="return confirm('Delete {{ addslashes($agreement->name) }}?')">
                                    <i class="bi bi-trash"></i>
                                </button>
                            @endif
                        </div>
                        @if(auth()->user()->isAdmin())
                            <form id="{{ $actionKey }}-duplicate" method="POST" action="{{ route('agreements.duplicate', $agreement) }}" class="d-none">
                                @csrf
                            </form>
                            <form id="{{ $actionKey }}-delete" method="POST" action="{{ route('agreements.destroy', $agreement) }}" class="d-none">
                                @csrf
                                @method('DELETE')
                            </form>
                        @endif
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="9" class="text-center py-5">
                        <p class="text-muted mb-2">
                            @if(auth()->user()->isAdmin()) No agreements found.
                            @else You are not assigned to any agreements.
                            @endif
                        </p>
                        @if(auth()->user()->isAdmin())
                            <a href="{{ route('agreements.create') }}" class="btn btn-sm btn-primary">Create Agreement</a>
                        @endif
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="card-footer bg-white">
        <x-htmx-pagination :paginator="$agreements" target="#agreements-table" />
    </div>
</div>
