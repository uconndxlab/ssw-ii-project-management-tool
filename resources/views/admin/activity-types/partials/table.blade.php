@php
    $s    = $sort ?? 'name';
    $d    = $direction ?? 'asc';
    $flip = fn($col) => ($s === $col && $d === 'asc') ? 'desc' : 'asc';
    $icon = fn($col) => $s === $col ? ($d === 'asc' ? ' ↑' : ' ↓') : '';
    $url  = fn($col) => route('activity-types.index', array_merge(request()->query(), ['sort' => $col, 'direction' => $flip($col), 'page' => 1]));
@endphp

<div class="card shadow-sm">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
                <tr>
                    <th>
                        <a class="text-decoration-none fw-semibold text-dark"
                           href="{{ $url('name') }}"
                           hx-get="{{ $url('name') }}" hx-target="#activity-types-table" hx-push-url="true">
                            Name{!! $icon('name') !!}
                        </a>
                    </th>
                    <th>
                        <a class="text-decoration-none fw-semibold text-dark"
                           href="{{ $url('contact_family') }}"
                           hx-get="{{ $url('contact_family') }}" hx-target="#activity-types-table" hx-push-url="true">
                            Contact Family{!! $icon('contact_family') !!}
                        </a>
                    </th>
                    <th>
                        <a class="text-decoration-none fw-semibold text-dark"
                           href="{{ $url('duration_days') }}"
                           hx-get="{{ $url('duration_days') }}" hx-target="#activity-types-table" hx-push-url="true">
                            Duration (Days){!! $icon('duration_days') !!}
                        </a>
                    </th>
                    <th>
                        <a class="text-decoration-none fw-semibold text-dark"
                           href="{{ $url('duration_hours') }}"
                           hx-get="{{ $url('duration_hours') }}" hx-target="#activity-types-table" hx-push-url="true">
                            Duration (Hours){!! $icon('duration_hours') !!}
                        </a>
                    </th>
                    <th>
                        <a class="text-decoration-none fw-semibold text-dark"
                           href="{{ $url('active') }}"
                           hx-get="{{ $url('active') }}" hx-target="#activity-types-table" hx-push-url="true">
                            Status{!! $icon('active') !!}
                        </a>
                    </th>
                    <th class="text-end" style="width:140px;">Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse($activityTypes as $type)
                <tr>
                    <td class="fw-semibold">{{ $type->name }}</td>
                    <td class="text-muted small">{{ $type->contactFamily->name }}</td>
                    <td>{{ $type->duration_days }}</td>
                    <td>{{ $type->duration_hours }}</td>
                    <td>
                        @if($type->active)
                            <span class="badge bg-success">Active</span>
                        @else
                            <span class="badge bg-secondary">Inactive</span>
                        @endif
                    </td>
                    <td class="text-end">
                        <div class="d-flex gap-1 justify-content-end flex-nowrap">
                            <a href="{{ route('activity-types.edit', $type) }}" class="btn btn-sm btn-outline-secondary">Edit</a>
                            <form method="POST" action="{{ route('activity-types.destroy', $type) }}" class="d-inline">
                                @csrf @method('DELETE')
                                <button type="submit" class="btn btn-sm btn-outline-danger"
                                    onclick="return confirm('Delete {{ addslashes($type->name) }}?')">Delete</button>
                            </form>
                        </div>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="5" class="text-center py-5">
                        <p class="text-muted mb-2">No activity types found.</p>
                        <a href="{{ route('activity-types.create') }}" class="btn btn-sm btn-primary">Add Activity Type</a>
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="card-footer bg-white">
        <x-htmx-pagination :paginator="$activityTypes" target="#activity-types-table" />
    </div>
</div>
