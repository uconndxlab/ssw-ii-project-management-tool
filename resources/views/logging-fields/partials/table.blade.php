@php
    $s    = $sort ?? 'sort_order';
    $d    = $direction ?? 'asc';
    $flip = fn($col) => ($s === $col && $d === 'asc') ? 'desc' : 'asc';
    $url  = fn($col) => route('logging-fields.index', array_merge(request()->query(), ['sort' => $col, 'direction' => $flip($col), 'page' => 1]));
    $scopeEmptyLabel = fn ($mode, string $allLabel, string $noneLabel) => ($mode?->value ?? $mode) === 'none' ? $noneLabel : $allLabel;
@endphp

<div class="card shadow-sm app-index-table-card">
    <x-table-scroll>
        <table class="table table-hover align-middle mb-0">
            <thead>
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
                    <th class="app-col-actions"><span class="visually-hidden">Actions</span></th>
                </tr>
            </thead>
            <tbody>
                @forelse($loggingFields as $field)
                <tr>
                    <td>
                        <a href="{{ route('logging-fields.show', $field) }}" class="app-link app-link--strong app-row-link">
                            {{ $field->name }}
                        </a>
                        @if($field->help_text)
                            <br><small class="text-muted">{{ Str::limit($field->help_text, 60) }}</small>
                        @endif
                    </td>
                    <td><span class="badge app-tag">{{ $field->fieldTypeLabel() }}</span></td>
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
                                <span class="badge app-tag">Agreements</span>
                            @endif
                            @if($field->available_in_contact_families)
                                <span class="badge app-tag">Activity Families</span>
                            @endif
                            @if($field->available_in_activities)
                                <span class="badge app-tag">Activity Types</span>
                            @endif
                            @if(! $field->available_in_agreements && ! $field->available_in_contact_families && ! $field->available_in_activities)
                                <span class="badge app-tag">Unassigned</span>
                            @endif
                        </div>
                    </td>
                    <td>
                        <x-status-badge :active="$field->is_active" />
                    </td>
                    <td class="app-col-actions">
                        @php $actionKey = 'logging-field-actions-' . $field->id; @endphp
                        <div class="dropdown app-row-actions d-inline-block">
                            <button type="button" class="btn btn-sm btn-link app-row-actions-toggle" data-bs-toggle="dropdown" data-bs-popper-config='{"strategy":"fixed"}' aria-expanded="false" aria-label="Logging field actions for {{ $field->name }}"><i class="bi bi-three-dots" aria-hidden="true"></i></button>
                            <div class="dropdown-menu dropdown-menu-end">
                            <a href="{{ route('logging-fields.show', $field) }}" class="dropdown-item"><i class="bi bi-eye me-2" aria-hidden="true"></i>View logging field</a>
                            @can('update', $field)
                            <a href="{{ route('logging-fields.edit', $field) }}" class="dropdown-item"><i class="bi bi-pencil-square me-2" aria-hidden="true"></i>Edit logging field</a>
                            @endcan
                            @can('delete', $field)
                            <hr class="dropdown-divider">
                            <button type="submit"
                                    form="{{ $actionKey }}-delete"
                                    data-confirm
                                    data-confirm-title="Delete logging field?"
                                    data-confirm-record="{{ $field->name }}"
                                    data-confirm-message="This action cannot be undone."
                                    data-confirm-action="Delete field" class="dropdown-item text-danger"><i class="bi bi-trash me-2" aria-hidden="true"></i>Delete logging field</button>
                            @endcan
                            </div>
                        </div>
                        @can('delete', $field)
                        <form id="{{ $actionKey }}-delete" method="POST" action="{{ route('logging-fields.destroy', $field) }}" class="d-none">
                            @csrf
                            @method('DELETE')
                        </form>
                        @endcan
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="7" class="text-center py-5">
                        <p class="text-muted mb-2">No logging fields found.</p>
                        @can('create', App\Models\LoggingField::class)
                        <a href="{{ route('logging-fields.create') }}" class="btn btn-sm btn-primary">Create Logging Field</a>
                        @endcan
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </x-table-scroll>
    <div class="card-footer">
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
