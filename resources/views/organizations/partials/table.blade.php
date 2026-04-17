@php
    $currentSort = $sort ?? 'name';
    $currentDirection = $direction ?? 'asc';

    function organization_sort_direction($column, $currentSort, $currentDirection) {
        if ($currentSort === $column) {
            return $currentDirection === 'asc' ? 'desc' : 'asc';
        }

        return 'asc';
    }

    function organization_sort_icon($column, $currentSort, $currentDirection) {
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
                                        hx-get="{{ route('organizations.index', array_merge(request()->query(), [
                                            'sort' => 'name',
                                            'direction' => organization_sort_direction('name', $currentSort, $currentDirection),
                                            'page' => 1,
                                        ])) }}"
                                        hx-target="#organizations-table"
                                        hx-swap="innerHTML"
                                        hx-push-url="true"
                                    >
                                        Name {{ organization_sort_icon('name', $currentSort, $currentDirection) }}
                                    </button>
                                </th>

                                <th>
                                    <button
                                        class="btn btn-link p-0 text-decoration-none fw-semibold"
                                        hx-get="{{ route('organizations.index', array_merge(request()->query(), [
                                            'sort' => 'state',
                                            'direction' => organization_sort_direction('state', $currentSort, $currentDirection),
                                            'page' => 1,
                                        ])) }}"
                                        hx-target="#organizations-table"
                                        hx-swap="innerHTML"
                                        hx-push-url="true"
                                    >
                                        State {{ organization_sort_icon('state', $currentSort, $currentDirection) }}
                                    </button>
                                </th>

                                <th>
                                    <button
                                        class="btn btn-link p-0 text-decoration-none fw-semibold"
                                        hx-get="{{ route('organizations.index', array_merge(request()->query(), [
                                            'sort' => 'agreements',
                                            'direction' => organization_sort_direction('agreements', $currentSort, $currentDirection),
                                            'page' => 1,
                                        ])) }}"
                                        hx-target="#organizations-table"
                                        hx-swap="innerHTML"
                                        hx-push-url="true"
                                    >
                                        Agreements {{ organization_sort_icon('agreements', $currentSort, $currentDirection) }}
                                    </button>
                                </th>

                                <th>
                                    <button
                                        class="btn btn-link p-0 text-decoration-none fw-semibold"
                                        hx-get="{{ route('organizations.index', array_merge(request()->query(), [
                                            'sort' => 'created',
                                            'direction' => organization_sort_direction('created', $currentSort, $currentDirection),
                                            'page' => 1,
                                        ])) }}"
                                        hx-target="#organizations-table"
                                        hx-swap="innerHTML"
                                        hx-push-url="true"
                                    >
                                        Created {{ organization_sort_icon('created', $currentSort, $currentDirection) }}
                                    </button>
                                </th>

                                @if(auth()->user()->isAdmin())
                                    <th style="width: 50px;"></th>
                                @endif
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($organizations as $organization)
                                <tr>
                                    <td>
                                        <a href="{{ route('organizations.show', $organization) }}" class="text-decoration-none text-dark d-block">
                                            <strong>{{ $organization->name }}</strong>
                                        </a>
                                    </td>
                                    <td>{{ $organization->state->name }}</td>
                                    <td>{{ $organization->agreements_count ?? $organization->agreements->count() }}</td>
                                    <td>{{ $organization->created_at->format('M d, Y') }}</td>

                                    @if(auth()->user()->isAdmin())
                                        <td onclick="event.stopPropagation()">
                                            <div class="dropdown">
                                                <button class="btn btn-sm btn-link text-secondary" type="button" data-bs-toggle="dropdown">
                                                    ⋯
                                                </button>
                                                <ul class="dropdown-menu dropdown-menu-end">
                                                    <li>
                                                        <a class="dropdown-item" href="{{ route('organizations.edit', $organization) }}">Edit</a>
                                                    </li>
                                                    <li>
                                                        <form method="POST" action="{{ route('organizations.destroy', $organization) }}"
                                                              hx-confirm="Are you sure you want to delete this organization?">
                                                            @csrf
                                                            @method('DELETE')
                                                            <button type="submit" class="dropdown-item text-danger">Delete</button>
                                                        </form>
                                                    </li>
                                                </ul>
                                            </div>
                                        </td>
                                    @endif
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="{{ auth()->user()->isAdmin() ? 5 : 4 }}" class="text-center text-muted">
                                        No organizations found
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                @if ($organizations->hasPages())
                    <div class="mt-3 d-flex justify-content-center">
                        <nav>
                            <ul class="pagination mb-0">
                                @if ($organizations->onFirstPage())
                                    <li class="page-item disabled"><span class="page-link">‹</span></li>
                                @else
                                    <li class="page-item">
                                        <a
                                            class="page-link"
                                            hx-get="{{ $organizations->previousPageUrl() }}"
                                            hx-target="#organizations-table"
                                            hx-swap="innerHTML"
                                            hx-push-url="true"
                                        >‹</a>
                                    </li>
                                @endif

                                @for ($i = 1; $i <= $organizations->lastPage(); $i++)
                                    @if ($i == $organizations->currentPage())
                                        <li class="page-item active">
                                            <span class="page-link">{{ $i }}</span>
                                        </li>
                                    @else
                                        <li class="page-item">
                                            <a
                                                class="page-link"
                                                hx-get="{{ $organizations->url($i) }}"
                                                hx-target="#organizations-table"
                                                hx-swap="innerHTML"
                                                hx-push-url="true"
                                            >{{ $i }}</a>
                                        </li>
                                    @endif
                                @endfor

                                @if ($organizations->hasMorePages())
                                    <li class="page-item">
                                        <a
                                            class="page-link"
                                            hx-get="{{ $organizations->nextPageUrl() }}"
                                            hx-target="#organizations-table"
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