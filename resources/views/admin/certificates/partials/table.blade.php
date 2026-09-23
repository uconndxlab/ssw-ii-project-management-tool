@php
    $s = $sort ?? 'name';
    $d = $direction ?? 'asc';
    $flip = fn ($col) => ($s === $col && $d === 'asc') ? 'desc' : 'asc';
    $url = fn ($col) => route('certificates.index', array_merge(request()->query(), ['sort' => $col, 'direction' => $flip($col), 'page' => 1]));
    $scopeEmptyLabel = fn ($mode, string $allLabel, string $noneLabel) => ($mode?->value ?? $mode) === 'none' ? $noneLabel : $allLabel;
@endphp

<div class="card shadow-sm app-index-table-card">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
                <tr>
                    <th><x-table-sort-link column="name" label="Name" :sort="$s" :direction="$d" :url="$url('name')" target="#certificates-table" /></th>
                    <th>Requirements</th>
                    <th>Expires</th>
                    <th>Projects</th>
                    <th>Programs</th>
                    <th><x-table-sort-link column="active" label="Status" :sort="$s" :direction="$d" :url="$url('active')" target="#certificates-table" /></th>
                    <th class="text-end" style="width:140px;">Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse($certificates as $certificate)
                <tr>
                    <td class="fw-semibold">
                        {{ $certificate->name }}
                        @if($certificate->retired_at)
                            <span class="badge bg-secondary ms-1">Retired</span>
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
                        @if($certificate->active)
                            <span class="badge bg-success">Active</span>
                        @else
                            <span class="badge bg-secondary">Inactive</span>
                        @endif
                    </td>
                    <td class="text-end text-nowrap">
                        @php $actionKey = 'certificate-actions-' . $certificate->id; @endphp
                        <div class="btn-group btn-group-sm" role="group" aria-label="Certificate actions for {{ $certificate->name }}">
                            @can('update', $certificate)
                            <a href="{{ route('certificates.edit', $certificate) }}" class="btn btn-outline-secondary" data-bs-toggle="tooltip" data-bs-title="Edit certificate" aria-label="Edit certificate">
                                <i class="bi bi-pencil-square"></i>
                            </a>
                            @endcan
                            @can('delete', $certificate)
                            <button type="submit" form="{{ $actionKey }}-delete" class="btn btn-outline-danger" data-bs-toggle="tooltip" data-bs-title="Delete certificate" aria-label="Delete certificate"
                                    onclick="return confirm('Delete {{ addslashes($certificate->name) }}?')">
                                <i class="bi bi-trash"></i>
                            </button>
                            @endcan
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
    </div>
    <div class="card-footer bg-white">
        <x-htmx-pagination :paginator="$certificates" target="#certificates-table" />
    </div>
</div>
