@php
    $s    = request('sort_by', 'sort_order');
    $d    = request('sort_dir', 'asc');
    $flip = fn($col) => ($s === $col && $d === 'asc') ? 'desc' : 'asc';
    $icon = fn($col) => $s === $col ? ($d === 'asc' ? ' ↑' : ' ↓') : '';
    $url  = fn($col) => route('logging-fields.index', array_merge(request()->query(), ['sort_by' => $col, 'sort_dir' => $flip($col), 'page' => 1]));
@endphp

<div class="card shadow-sm">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
                <tr>
                    <th>
                        <a class="text-decoration-none fw-semibold text-dark"
                           href="{{ $url('name') }}"
                           hx-get="{{ $url('name') }}" hx-target="#logging-fields-table" hx-swap="innerHTML" hx-push-url="true">
                            Field Name{!! $icon('name') !!}
                        </a>
                    </th>
                    <th>
                        <a class="text-decoration-none fw-semibold text-dark"
                           href="{{ $url('field_type') }}"
                           hx-get="{{ $url('field_type') }}" hx-target="#logging-fields-table" hx-swap="innerHTML" hx-push-url="true">
                            Field Type{!! $icon('field_type') !!}
                        </a>
                    </th>
                    <th>Available In</th>
                    <th>
                        <a class="text-decoration-none fw-semibold text-dark"
                           href="{{ $url('is_active') }}"
                           hx-get="{{ $url('is_active') }}" hx-target="#logging-fields-table" hx-swap="innerHTML" hx-push-url="true">
                            Status{!! $icon('is_active') !!}
                        </a>
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
                        <div class="d-flex flex-wrap gap-1">
                            @if($field->available_in_agreements)
                                <span class="badge bg-primary">Agreements</span>
                            @endif
                            @if($field->available_in_contact_families)
                                <span class="badge bg-info text-dark">Contact Families</span>
                            @endif
                            @if($field->available_in_activities)
                                <span class="badge bg-warning text-dark">Activities</span>
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
                    <td class="text-end">
                        <div class="d-flex gap-1 justify-content-end flex-nowrap">
                            <a href="{{ route('logging-fields.show', $field) }}" class="btn btn-sm btn-outline-primary">View</a>
                            <a href="{{ route('logging-fields.edit', $field) }}" class="btn btn-sm btn-outline-secondary">Edit</a>
                            <form method="POST" action="{{ route('logging-fields.destroy', $field) }}" class="d-inline">
                                @csrf @method('DELETE')
                                <button type="submit" class="btn btn-sm btn-outline-danger"
                                    onclick="return confirm('Delete field {{ addslashes($field->name) }}?')">Delete</button>
                            </form>
                        </div>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="5" class="text-center py-5">
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
