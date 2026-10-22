@php
    $s = $sort ?? 'name';
    $d = $direction ?? 'asc';
    $flip = fn ($col) => ($s === $col && $d === 'asc') ? 'desc' : 'asc';
    $url = fn ($col) => route('certificates.index', array_merge(request()->query(), ['sort' => $col, 'direction' => $flip($col), 'page' => 1]));
    $scopeEmptyLabel = fn ($mode, string $allLabel, string $noneLabel) => ($mode?->value ?? $mode) === 'none' ? $noneLabel : $allLabel;
@endphp

<div class="card shadow-sm app-index-table-card">
    <x-table-scroll>
        <table class="table table-hover align-middle mb-0">
            <thead>
                <tr>
                    <th><x-table-sort-link column="name" label="Name" :sort="$s" :direction="$d" :url="$url('name')" target="#certificates-table" /></th>
                    <th>Requirements</th>
                    <th>Expires</th>
                    <th>Projects</th>
                    <th>Programs</th>
                    <th><x-table-sort-link column="active" label="Status" :sort="$s" :direction="$d" :url="$url('active')" target="#certificates-table" /></th>
                    <th class="app-col-actions"><span class="visually-hidden">Actions</span></th>
                </tr>
            </thead>
            <tbody>
                @forelse($certificates as $certificate)
                <tr>
                    <td class="fw-semibold">
                        {{ $certificate->name }}
                        @if($certificate->retired_at)
                            <span class="app-tag ms-1">Retired</span>
                        @endif
                    </td>
                    <td>{{ $certificate->requirements_count }}</td>
                    <td>{{ $certificate->validity_months ? $certificate->validity_months . ' mo' : 'Never' }}</td>
                    <td>
                        <x-table-badge-list
                            kind="project"
                            :items="$certificate->projects"
                            route-name="projects.show"
                            :empty-label="$scopeEmptyLabel($certificate->program_scope_mode, 'All projects', 'No projects')"
                        />
                    </td>
                    <td>
                        <x-table-badge-list
                            kind="program"
                            :items="$certificate->programs"
                            route-name="programs.show"
                            :empty-label="$scopeEmptyLabel($certificate->program_scope_mode, 'All programs', 'No programs')"
                        />
                    </td>
                    <td>
                        <x-status-badge :active="$certificate->active" />
                    </td>
                    <td class="app-col-actions">
                        @php $actionKey = 'certificate-actions-' . $certificate->id; @endphp
                        <div class="dropdown app-row-actions d-inline-block">
                            <button type="button" class="btn btn-sm btn-link app-row-actions-toggle" data-bs-toggle="dropdown" data-bs-popper-config='{"strategy":"fixed"}' aria-expanded="false" aria-label="Certificate actions for {{ $certificate->name }}"><i class="bi bi-three-dots" aria-hidden="true"></i></button>
                            <div class="dropdown-menu dropdown-menu-end">
                            @can('update', $certificate)
                            <a href="{{ route('certificates.edit', $certificate) }}" class="dropdown-item"><i class="bi bi-pencil-square me-2" aria-hidden="true"></i>Edit certificate</a>
                            @endcan
                            @can('delete', $certificate)
                            <hr class="dropdown-divider">
                            <button type="submit" form="{{ $actionKey }}-delete"
                                    onclick="return confirm('Delete {{ addslashes($certificate->name) }}?')" class="dropdown-item text-danger"><i class="bi bi-trash me-2" aria-hidden="true"></i>Delete certificate</button>
                            @endcan
                            </div>
                        </div>
                        @can('delete', $certificate)
                        <form id="{{ $actionKey }}-delete" method="POST" action="{{ route('certificates.destroy', $certificate) }}" class="d-none">
                            @csrf
                            @method('DELETE')
                        </form>
                        @endcan
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="7" class="text-center py-5">
                        <p class="text-muted mb-2">No certificates found.</p>
                        @can('create', App\Models\Certificate::class)
                        <a href="{{ route('certificates.create') }}" class="btn btn-sm btn-primary">Add Certificate</a>
                        @endcan
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </x-table-scroll>
    <div class="card-footer">
        <x-htmx-pagination :paginator="$certificates" target="#certificates-table" />
    </div>
</div>
