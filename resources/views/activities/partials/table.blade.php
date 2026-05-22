@php
    $currentSort = $sort ?? 'date';
    $currentDirection = $direction ?? 'desc';

    function activity_sort_direction($column, $currentSort, $currentDirection) {
        if ($currentSort === $column) {
            return $currentDirection === 'asc' ? 'desc' : 'asc';
        }

        return $column === 'date' ? 'desc' : 'asc';
    }

    function activity_sort_icon($column, $currentSort, $currentDirection) {
        if ($currentSort !== $column) {
            return '↕';
        }

        return $currentDirection === 'asc' ? '↑' : '↓';
    }
@endphp

<div class="row">
    <div class="col-12">
        <div class="card">
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover align-middle">
                        <thead>
                            <tr>
                                <th>
                                    <button
                                        class="btn btn-link p-0 text-decoration-none fw-semibold"
                                        hx-get="{{ route('activities.index', array_merge(request()->query(), [
                                            'sort' => 'date',
                                            'direction' => activity_sort_direction('date', $currentSort, $currentDirection),
                                            'page' => 1,
                                        ])) }}"
                                        hx-target="#activities-table"
                                        hx-swap="innerHTML"
                                        hx-push-url="true"
                                    >
                                        Date {{ activity_sort_icon('date', $currentSort, $currentDirection) }}
                                    </button>
                                </th>

                                <th>
                                    <button
                                        class="btn btn-link p-0 text-decoration-none fw-semibold"
                                        hx-get="{{ route('activities.index', array_merge(request()->query(), [
                                            'sort' => 'agreement',
                                            'direction' => activity_sort_direction('agreement', $currentSort, $currentDirection),
                                            'page' => 1,
                                        ])) }}"
                                        hx-target="#activities-table"
                                        hx-swap="innerHTML"
                                        hx-push-url="true"
                                    >
                                        Agreement {{ activity_sort_icon('agreement', $currentSort, $currentDirection) }}
                                    </button>
                                </th>

                                <th>
                                    <button
                                        class="btn btn-link p-0 text-decoration-none fw-semibold"
                                        hx-get="{{ route('activities.index', array_merge(request()->query(), [
                                            'sort' => 'activity_type',
                                            'direction' => activity_sort_direction('activity_type', $currentSort, $currentDirection),
                                            'page' => 1,
                                        ])) }}"
                                        hx-target="#activities-table"
                                        hx-swap="innerHTML"
                                        hx-push-url="true"
                                    >
                                        Activity Type {{ activity_sort_icon('activity_type', $currentSort, $currentDirection) }}
                                    </button>
                                </th>

                                <th>
                                    <button
                                        class="btn btn-link p-0 text-decoration-none fw-semibold"
                                        hx-get="{{ route('activities.index', array_merge(request()->query(), [
                                            'sort' => 'logged_by',
                                            'direction' => activity_sort_direction('logged_by', $currentSort, $currentDirection),
                                            'page' => 1,
                                        ])) }}"
                                        hx-target="#activities-table"
                                        hx-swap="innerHTML"
                                        hx-push-url="true"
                                    >
                                        Logged By {{ activity_sort_icon('logged_by', $currentSort, $currentDirection) }}
                                    </button>
                                </th>

                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($activities as $activity)
                                <tr>
                                    <td>{{ $activity->engagement_date->format('M d, Y') }}</td>
                                    <td>
                                        @forelse($activity->agreements as $agreement)
                                            <span class="badge bg-secondary me-1 mb-1">{{ $agreement->name }}</span>
                                        @empty
                                            <span class="text-muted small">None</span>
                                        @endforelse
                                    </td>
                                    <td>
                                        <span class="badge bg-info">
                                            {{ $activity->activityType->name }}
                                        </span>
                                    </td>
                                    <td>{{ $activity->user->name }}</td>
                                    <td>
                                        <div class="btn-group btn-group-sm">
                                            <a href="{{ route('activities.show', $activity) }}" class="btn btn-outline-secondary">View</a>
                                            @if(auth()->user()->isAdmin() || $activity->user_id === auth()->id())
                                                <a href="{{ route('activities.edit', $activity) }}" class="btn btn-outline-primary">Edit</a>
                                                <form method="POST" action="{{ route('activities.destroy', $activity) }}" class="d-inline"
                                                      hx-confirm="Are you sure you want to delete this activity?">
                                                    @csrf
                                                    @method('DELETE')
                                                    <button type="submit" class="btn btn-outline-danger">Delete</button>
                                                </form>
                                            @endif
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="6" class="text-center text-muted">
                                        @if(auth()->user()->isAdmin())
                                            No activities logged yet
                                        @else
                                            No activities found for your assigned agreements
                                        @endif
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                @if ($activities->hasPages())
                    <div class="mt-3 d-flex justify-content-center">
                        <nav>
                            <ul class="pagination mb-0">
                                @if ($activities->onFirstPage())
                                    <li class="page-item disabled"><span class="page-link">‹</span></li>
                                @else
                                    <li class="page-item">
                                        <a
                                            class="page-link"
                                            hx-get="{{ $activities->previousPageUrl() }}"
                                            hx-target="#activities-table"
                                            hx-swap="innerHTML"
                                            hx-push-url="true"
                                        >‹</a>
                                    </li>
                                @endif

                                @for ($i = 1; $i <= $activities->lastPage(); $i++)
                                    @if ($i == $activities->currentPage())
                                        <li class="page-item active">
                                            <span class="page-link">{{ $i }}</span>
                                        </li>
                                    @else
                                        <li class="page-item">
                                            <a
                                                class="page-link"
                                                hx-get="{{ $activities->url($i) }}"
                                                hx-target="#activities-table"
                                                hx-swap="innerHTML"
                                                hx-push-url="true"
                                            >{{ $i }}</a>
                                        </li>
                                    @endif
                                @endfor

                                @if ($activities->hasMorePages())
                                    <li class="page-item">
                                        <a
                                            class="page-link"
                                            hx-get="{{ $activities->nextPageUrl() }}"
                                            hx-target="#activities-table"
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
    </div>
</div>