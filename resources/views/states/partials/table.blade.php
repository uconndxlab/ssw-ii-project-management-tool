@php
    $currentSort = $sort ?? 'name';
    $currentDirection = $direction ?? 'asc';

    function state_sort_direction($column, $currentSort, $currentDirection) {
        if ($currentSort === $column) {
            return $currentDirection === 'asc' ? 'desc' : 'asc';
        }

        return 'asc';
    }

    function state_sort_icon($column, $currentSort, $currentDirection) {
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
                                        hx-get="{{ route('states.index', array_merge(request()->query(), [
                                            'sort' => 'name',
                                            'direction' => state_sort_direction('name', $currentSort, $currentDirection),
                                            'page' => 1,
                                        ])) }}"
                                        hx-target="#states-table"
                                        hx-swap="innerHTML"
                                        hx-push-url="true"
                                    >
                                        Name {{ state_sort_icon('name', $currentSort, $currentDirection) }}
                                    </button>
                                </th>

                                <th>
                                    <button
                                        class="btn btn-link p-0 text-decoration-none fw-semibold"
                                        hx-get="{{ route('states.index', array_merge(request()->query(), [
                                            'sort' => 'organizations',
                                            'direction' => state_sort_direction('organizations', $currentSort, $currentDirection),
                                            'page' => 1,
                                        ])) }}"
                                        hx-target="#states-table"
                                        hx-swap="innerHTML"
                                        hx-push-url="true"
                                    >
                                        Organizations {{ state_sort_icon('organizations', $currentSort, $currentDirection) }}
                                    </button>
                                </th>

                                <th>
                                    <button
                                        class="btn btn-link p-0 text-decoration-none fw-semibold"
                                        hx-get="{{ route('states.index', array_merge(request()->query(), [
                                            'sort' => 'agreements',
                                            'direction' => state_sort_direction('agreements', $currentSort, $currentDirection),
                                            'page' => 1,
                                        ])) }}"
                                        hx-target="#states-table"
                                        hx-swap="innerHTML"
                                        hx-push-url="true"
                                    >
                                        Agreements {{ state_sort_icon('agreements', $currentSort, $currentDirection) }}
                                    </button>
                                </th>

                                <th>
                                    <button
                                        class="btn btn-link p-0 text-decoration-none fw-semibold"
                                        hx-get="{{ route('states.index', array_merge(request()->query(), [
                                            'sort' => 'created',
                                            'direction' => state_sort_direction('created', $currentSort, $currentDirection),
                                            'page' => 1,
                                        ])) }}"
                                        hx-target="#states-table"
                                        hx-swap="innerHTML"
                                        hx-push-url="true"
                                    >
                                        Created {{ state_sort_icon('created', $currentSort, $currentDirection) }}
                                    </button>
                                </th>

                                <th style="width: 50px;"></th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($states as $state)
                                <tr>
                                    <td><strong>{{ $state->name }}</strong></td>
                                    <td>{{ $state->organizations_count }}</td>
                                    <td>{{ $state->agreements_count }}</td>
                                    <td>{{ $state->created_at->format('M d, Y') }}</td>
                                    <td onclick="event.stopPropagation()">
                                        <div class="dropdown">
                                            <button class="btn btn-sm btn-link text-secondary" type="button" data-bs-toggle="dropdown">
                                                ⋯
                                            </button>
                                            <ul class="dropdown-menu dropdown-menu-end">
                                                <li>
                                                    <a class="dropdown-item" href="{{ route('states.edit', $state) }}">Edit</a>
                                                </li>
                                                <li>
                                                    <form method="POST" action="{{ route('states.destroy', $state) }}"
                                                          hx-confirm="Are you sure you want to delete this state?">
                                                        @csrf
                                                        @method('DELETE')
                                                        <button type="submit" class="dropdown-item text-danger">Delete</button>
                                                    </form>
                                                </li>
                                            </ul>
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="5" class="text-center text-muted">No states found</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                @if ($states->hasPages())
                    <div class="mt-3 d-flex justify-content-center">
                        <nav>
                            <ul class="pagination mb-0">
                                @if ($states->onFirstPage())
                                    <li class="page-item disabled"><span class="page-link">‹</span></li>
                                @else
                                    <li class="page-item">
                                        <a
                                            class="page-link"
                                            hx-get="{{ $states->previousPageUrl() }}"
                                            hx-target="#states-table"
                                            hx-swap="innerHTML"
                                            hx-push-url="true"
                                        >‹</a>
                                    </li>
                                @endif

                                @for ($i = 1; $i <= $states->lastPage(); $i++)
                                    @if ($i == $states->currentPage())
                                        <li class="page-item active">
                                            <span class="page-link">{{ $i }}</span>
                                        </li>
                                    @else
                                        <li class="page-item">
                                            <a
                                                class="page-link"
                                                hx-get="{{ $states->url($i) }}"
                                                hx-target="#states-table"
                                                hx-swap="innerHTML"
                                                hx-push-url="true"
                                            >{{ $i }}</a>
                                        </li>
                                    @endif
                                @endfor

                                @if ($states->hasMorePages())
                                    <li class="page-item">
                                        <a
                                            class="page-link"
                                            hx-get="{{ $states->nextPageUrl() }}"
                                            hx-target="#states-table"
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