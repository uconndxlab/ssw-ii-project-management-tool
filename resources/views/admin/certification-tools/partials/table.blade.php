@php
    $s = $sort ?? 'name';
    $d = $direction ?? 'asc';
    $flip = fn ($col) => ($s === $col && $d === 'asc') ? 'desc' : 'asc';
    $url = fn ($col) => route('certification-tools.index', array_merge(request()->query(), ['sort' => $col, 'direction' => $flip($col), 'page' => 1]));
    $scopeEmptyLabel = fn ($mode, string $allLabel, string $noneLabel) => ($mode?->value ?? $mode) === 'none' ? $noneLabel : $allLabel;
@endphp

<div class="card shadow-sm app-index-table-card">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
                <tr>
                    <th><x-table-sort-link column="name" label="Name" :sort="$s" :direction="$d" :url="$url('name')" target="#certification-tools-table" /></th>
                    <th>Dimensions</th>
                    <th>Score Fields</th>
                    <th>Projects</th>
                    <th>Programs</th>
                    <th><x-table-sort-link column="active" label="Status" :sort="$s" :direction="$d" :url="$url('active')" target="#certification-tools-table" /></th>
                    <th class="text-end" style="width:140px;">Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse($certificationTools as $tool)
                <tr>
                    <td class="fw-semibold">
                        {{ $tool->name }}
                        @if($tool->retired_at)
                            <span class="badge bg-secondary ms-1">Retired</span>
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
                        @if($tool->active)
                            <span class="badge bg-success">Active</span>
                        @else
                            <span class="badge bg-secondary">Inactive</span>
                        @endif
                    </td>
                    <td class="text-end text-nowrap">
                        @php $actionKey = 'certification-tool-actions-' . $tool->id; @endphp
                        <div class="btn-group btn-group-sm" role="group" aria-label="Certification tool actions for {{ $tool->name }}">
                            @can('update', $tool)
                            <a href="{{ route('certification-tools.edit', $tool) }}" class="btn btn-outline-secondary" data-bs-toggle="tooltip" data-bs-title="Edit tool" aria-label="Edit tool">
                                <i class="bi bi-pencil-square"></i>
                            </a>
                            @endcan
                            @can('delete', $tool)
                            <button type="submit" form="{{ $actionKey }}-delete" class="btn btn-outline-danger" data-bs-toggle="tooltip" data-bs-title="Delete tool" aria-label="Delete tool"
                                    onclick="return confirm('Delete {{ addslashes($tool->name) }}?')">
                                <i class="bi bi-trash"></i>
                            </button>
                            @endcan
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
    </div>
    <div class="card-footer bg-white">
        <x-htmx-pagination :paginator="$certificationTools" target="#certification-tools-table" />
    </div>
</div>
