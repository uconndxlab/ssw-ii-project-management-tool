@php
    $s = $sort ?? 'name';
    $d = $direction ?? 'asc';
    $flip = fn ($col) => ($s === $col && $d === 'asc') ? 'desc' : 'asc';
    $url = fn ($col) => route('certification-tools.index', array_merge(request()->query(), ['sort' => $col, 'direction' => $flip($col), 'page' => 1]));
    $scopeEmptyLabel = fn ($mode, string $allLabel, string $noneLabel) => ($mode?->value ?? $mode) === 'none' ? $noneLabel : $allLabel;
@endphp

<div class="card shadow-sm app-index-table-card">
    <x-table-scroll>
        <table class="table table-hover align-middle mb-0">
            <thead>
                <tr>
                    <th><x-table-sort-link column="name" label="Name" :sort="$s" :direction="$d" :url="$url('name')" target="#certification-tools-table" /></th>
                    <th>Dimensions</th>
                    <th>Score Fields</th>
                    <th>Projects</th>
                    <th>Programs</th>
                    <th><x-table-sort-link column="active" label="Status" :sort="$s" :direction="$d" :url="$url('active')" target="#certification-tools-table" /></th>
                    <th class="app-col-actions"><span class="visually-hidden">Actions</span></th>
                </tr>
            </thead>
            <tbody>
                @forelse($certificationTools as $tool)
                <tr>
                    <td class="fw-semibold">
                        {{ $tool->name }}
                        @if($tool->retired_at)
                            <span class="app-tag ms-1">Retired</span>
                        @endif
                    </td>
                    <td>{{ $tool->dimensions_count }}</td>
                    <td>{{ $tool->score_fields_count }}</td>
                    <td>
                        <x-table-badge-list
                            kind="project"
                            :items="$tool->projects"
                            route-name="projects.show"
                            :empty-label="$scopeEmptyLabel($tool->program_scope_mode, 'All projects', 'No projects')"
                        />
                    </td>
                    <td>
                        <x-table-badge-list
                            kind="program"
                            :items="$tool->programs"
                            route-name="programs.show"
                            :empty-label="$scopeEmptyLabel($tool->program_scope_mode, 'All programs', 'No programs')"
                        />
                    </td>
                    <td>
                        <x-status-badge :active="$tool->active" />
                    </td>
                    <td class="app-col-actions">
                        @php $actionKey = 'certification-tool-actions-' . $tool->id; @endphp
                        <div class="dropdown app-row-actions d-inline-block">
                            <button type="button" class="btn btn-sm btn-link app-row-actions-toggle" data-bs-toggle="dropdown" data-bs-popper-config='{"strategy":"fixed"}' aria-expanded="false" aria-label="Certification tool actions for {{ $tool->name }}"><i class="bi bi-three-dots" aria-hidden="true"></i></button>
                            <div class="dropdown-menu dropdown-menu-end">
                            @can('update', $tool)
                            <a href="{{ route('certification-tools.edit', $tool) }}" class="dropdown-item"><i class="bi bi-pencil-square me-2" aria-hidden="true"></i>Edit tool</a>
                            @endcan
                            @can('delete', $tool)
                            <hr class="dropdown-divider">
                            <button type="submit" form="{{ $actionKey }}-delete"
                                    data-confirm
                                    data-confirm-title="Delete certification tool?"
                                    data-confirm-record="{{ $tool->name }}"
                                    data-confirm-message="This action cannot be undone."
                                    data-confirm-action="Delete tool" class="dropdown-item text-danger"><i class="bi bi-trash me-2" aria-hidden="true"></i>Delete tool</button>
                            @endcan
                            </div>
                        </div>
                        @can('delete', $tool)
                        <form id="{{ $actionKey }}-delete" method="POST" action="{{ route('certification-tools.destroy', $tool) }}" class="d-none">
                            @csrf
                            @method('DELETE')
                        </form>
                        @endcan
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="7" class="text-center py-5">
                        <p class="text-muted mb-2">No certification tools found.</p>
                        @can('create', App\Models\CertificationTool::class)
                        <a href="{{ route('certification-tools.create') }}" class="btn btn-sm btn-primary">Add Certification Tool</a>
                        @endcan
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </x-table-scroll>
    <div class="card-footer">
        <x-htmx-pagination :paginator="$certificationTools" target="#certification-tools-table" />
    </div>
</div>
