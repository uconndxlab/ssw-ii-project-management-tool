@php
    $s    = $sort ?? 'sort_order';
    $d    = $direction ?? 'asc';
    $flip = fn($col) => ($s === $col && $d === 'asc') ? 'desc' : 'asc';
    $url  = fn($col) => route('logging-fields.index', array_merge(request()->query(), ['sort' => $col, 'direction' => $flip($col), 'page' => 1]));
    $scopeEmptyLabel = fn ($mode, string $allLabel, string $noneLabel) => ($mode?->value ?? $mode) === 'none' ? $noneLabel : $allLabel;
@endphp

<div class="card shadow-sm app-index-table-card">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
                <tr>
                    <th>
                        <x-table-sort-link column="name" label="Field Name" :sort="$s" :direction="$d" :url="$url('name')" target="#logging-fields-table" />
                    </th>
                    <th>
                        <x-table-sort-link column="field_type" label="Field Type" :sort="$s" :direction="$d" :url="$url('field_type')" target="#logging-fields-table" />
                    </th>
                    <th>
                        <x-table-sort-link column="projects" label="Projects" :sort="$s" :direction="$d" :url="$url('projects')" target="#logging-fields-table" />
                    </th>
                    <th>
                        <x-table-sort-link column="programs" label="Programs" :sort="$s" :direction="$d" :url="$url('programs')" target="#logging-fields-table" />
                    </th>
                    <th>
                        <x-table-sort-link column="availability" label="Available In" :sort="$s" :direction="$d" :url="$url('availability')" target="#logging-fields-table" />
                    </th>
                    <th>
                        <x-table-sort-link column="is_active" label="Status" :sort="$s" :direction="$d" :url="$url('is_active')" target="#logging-fields-table" />
                    </th>
                    <th class="text-end" style="width:200px;">Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse($loggingFields as $field)
                <tr>
                    <td>
                        <a href="{{ route('logging-fields.show', $field) }}" class="fw-semibold text-decoration-none text-dark">
                            {{ $field->name }}
                        </a>
                        @if($field->help_text)
                            <br><small class="text-muted">{{ Str::limit($field->help_text, 60) }}</small>
                        @endif
                    </td>
                    <td><span class="badge bg-secondary">{{ ucfirst($field->field_type) }}</span></td>
                    <td>
                        <x-table-badge-list
                            kind="project"
                            :items="$field->projects"
                            route-name="projects.show"
                            :empty-label="$scopeEmptyLabel($field->program_scope_mode, 'All projects', 'No projects')"
                        />
                    </td>
                    <td>
                        <x-table-badge-list
                            kind="program"
                            :items="$field->programs"
                            route-name="programs.show"
                            :empty-label="$scopeEmptyLabel($field->program_scope_mode, 'All programs', 'No programs')"
                        />
                    </td>
                    <td>
                        <div class="d-flex flex-wrap gap-1">
                            @if($field->available_in_agreements)
                                <span class="badge bg-primary">Agreements</span>
                            @endif
                            @if($field->available_in_contact_families)
                                <span class="badge bg-info text-dark">Activity Families</span>
                            @endif
                            @if($field->available_in_activities)
                                <span class="badge bg-warning text-dark">Activity Types</span>
                            @endif
                            @if(! $field->available_in_agreements && ! $field->available_in_contact_families && ! $field->available_in_activities)
                                <span class="badge bg-light text-dark border">Unassigned</span>
                            @endif
                        </div>
                    </td>
                    <td>
                        @if($field->is_active)
                            <span class="badge bg-success">Active</span>
                        @else
                            <span class="badge bg-secondary">Inactive</span>
                        @endif
                    </td>
                    <td class="text-end text-nowrap">
                        @php $actionKey = 'logging-field-actions-' . $field->id; @endphp
                        <div class="btn-group btn-group-sm" role="group" aria-label="Logging field actions for {{ $field->name }}">
                            <a href="{{ route('logging-fields.show', $field) }}"
                               class="btn btn-outline-primary"
                               data-bs-toggle="tooltip"
                               data-bs-title="View logging field"
                               aria-label="View logging field">
                                <i class="bi bi-eye"></i>
                            </a>
                            <a href="{{ route('logging-fields.edit', $field) }}"
                               class="btn btn-outline-secondary"
                               data-bs-toggle="tooltip"
                               data-bs-title="Edit logging field"
                               aria-label="Edit logging field">
                                <i class="bi bi-pencil-square"></i>
                            </a>
                            <button type="submit"
                                    form="{{ $actionKey }}-delete"
                                    class="btn btn-outline-danger"
                                    data-bs-toggle="tooltip"
                                    data-bs-title="Delete logging field"
                                    aria-label="Delete logging field"
                                    onclick="return confirm('Delete field {{ addslashes($field->name) }}?')">
                                <i class="bi bi-trash"></i>
                            </button>
                        </div>
                        <form id="{{ $actionKey }}-delete" method="POST" action="{{ route('logging-fields.destroy', $field) }}" class="d-none">
                            @csrf
                            @method('DELETE')
                        </form>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="7" class="text-center py-5">
                        <p class="text-muted mb-2">No logging fields found.</p>
                        <a href="{{ route('logging-fields.create') }}" class="btn btn-sm btn-primary">Create Logging Field</a>
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="card-footer bg-white">
        <x-htmx-pagination :paginator="$loggingFields" target="#logging-fields-table" />
    </div>
</div>

@once
    <script>
    (function () {
        document.body.addEventListener('htmx:afterSwap', function (event) {
            if (event.target && event.target.id === 'logging-fields-table') {
                var params = new URLSearchParams(window.location.search);
                var form = document.getElementById('logging-field-filters');
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
