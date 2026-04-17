@php
    $currentSort = $sort ?? 'name';
    $currentDirection = $direction ?? 'asc';

    function activity_type_sort_direction($column, $currentSort, $currentDirection) {
        if ($currentSort === $column) {
            return $currentDirection === 'asc' ? 'desc' : 'asc';
        }

        return 'asc';
    }

    function activity_type_sort_icon($column, $currentSort, $currentDirection) {
        if ($currentSort !== $column) {
            return '↕';
        }

        return $currentDirection === 'asc' ? '↑' : '↓';
    }
@endphp

<div class="card">
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-hover align-middle">
                <thead>
                    <tr>
                        <th>
                            <button
                                class="btn btn-link p-0 text-decoration-none fw-semibold"
                                hx-get="{{ route('activity-types.index', array_merge(request()->query(), [
                                    'sort' => 'name',
                                    'direction' => activity_type_sort_direction('name', $currentSort, $currentDirection),
                                    'page' => 1,
                                ])) }}"
                                hx-target="#activity-types-table"
                                hx-swap="innerHTML"
                                hx-push-url="true"
                            >
                                Name {{ activity_type_sort_icon('name', $currentSort, $currentDirection) }}
                            </button>
                        </th>

                        <th>
                            <button
                                class="btn btn-link p-0 text-decoration-none fw-semibold"
                                hx-get="{{ route('activity-types.index', array_merge(request()->query(), [
                                    'sort' => 'contact_family',
                                    'direction' => activity_type_sort_direction('contact_family', $currentSort, $currentDirection),
                                    'page' => 1,
                                ])) }}"
                                hx-target="#activity-types-table"
                                hx-swap="innerHTML"
                                hx-push-url="true"
                            >
                                Contact Family {{ activity_type_sort_icon('contact_family', $currentSort, $currentDirection) }}
                            </button>
                        </th>

                        <th>
                            <button
                                class="btn btn-link p-0 text-decoration-none fw-semibold"
                                hx-get="{{ route('activity-types.index', array_merge(request()->query(), [
                                    'sort' => 'duration_days',
                                    'direction' => activity_type_sort_direction('duration_days', $currentSort, $currentDirection),
                                    'page' => 1,
                                ])) }}"
                                hx-target="#activity-types-table"
                                hx-swap="innerHTML"
                                hx-push-url="true"
                            >
                                Duration (Days) {{ activity_type_sort_icon('duration_days', $currentSort, $currentDirection) }}
                            </button>
                        </th>

                        <th>
                            <button
                                class="btn btn-link p-0 text-decoration-none fw-semibold"
                                hx-get="{{ route('activity-types.index', array_merge(request()->query(), [
                                    'sort' => 'active',
                                    'direction' => activity_type_sort_direction('active', $currentSort, $currentDirection),
                                    'page' => 1,
                                ])) }}"
                                hx-target="#activity-types-table"
                                hx-swap="innerHTML"
                                hx-push-url="true"
                            >
                                Status {{ activity_type_sort_icon('active', $currentSort, $currentDirection) }}
                            </button>
                        </th>

                        <th style="width: 150px;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($activityTypes as $type)
                        <tr>
                            <td>{{ $type->name }}</td>
                            <td>{{ $type->contactFamily->name }}</td>
                            <td>{{ $type->duration_days }}</td>
                            <td>
                                @if($type->active)
                                    <span class="badge bg-success">Active</span>
                                @else
                                    <span class="badge bg-secondary">Inactive</span>
                                @endif
                            </td>
                            <td>
                                <a href="{{ route('activity-types.edit', $type) }}" class="btn btn-sm btn-outline-primary">Edit</a>
                                <form method="POST"
                                      action="{{ route('activity-types.destroy', $type) }}"
                                      class="d-inline"
                                      hx-confirm="Are you sure you want to delete this activity type?">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="btn btn-sm btn-outline-danger">
                                        Delete
                                    </button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="text-center text-muted">No activity types found.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if ($activityTypes->hasPages())
            <div class="mt-3 d-flex justify-content-center">
                <nav>
                    <ul class="pagination mb-0">
                        @if ($activityTypes->onFirstPage())
                            <li class="page-item disabled"><span class="page-link">‹</span></li>
                        @else
                            <li class="page-item">
                                <a
                                    class="page-link"
                                    hx-get="{{ $activityTypes->previousPageUrl() }}"
                                    hx-target="#activity-types-table"
                                    hx-swap="innerHTML"
                                    hx-push-url="true"
                                >‹</a>
                            </li>
                        @endif

                        @for ($i = 1; $i <= $activityTypes->lastPage(); $i++)
                            @if ($i == $activityTypes->currentPage())
                                <li class="page-item active">
                                    <span class="page-link">{{ $i }}</span>
                                </li>
                            @else
                                <li class="page-item">
                                    <a
                                        class="page-link"
                                        hx-get="{{ $activityTypes->url($i) }}"
                                        hx-target="#activity-types-table"
                                        hx-swap="innerHTML"
                                        hx-push-url="true"
                                    >{{ $i }}</a>
                                </li>
                            @endif
                        @endfor

                        @if ($activityTypes->hasMorePages())
                            <li class="page-item">
                                <a
                                    class="page-link"
                                    hx-get="{{ $activityTypes->nextPageUrl() }}"
                                    hx-target="#activity-types-table"
                                    hx-swap="innerHTML"
                                    hx-push-url="true"
                                >›</a>
                            </li>
                        @else
                            <li class="page-item disabled"><span class="page-link">›</span></li>
                        @endif
                    </ul>
                </nav>
            </div>
        @endif
    </div>
</div>