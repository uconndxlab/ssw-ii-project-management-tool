@php
    $currentSort = $sort ?? 'name';
    $currentDirection = $direction ?? 'asc';

    function agreement_sort_direction($column, $currentSort, $currentDirection) {
        if ($currentSort === $column) {
            return $currentDirection === 'asc' ? 'desc' : 'asc';
        }

        return 'asc';
    }

    function agreement_sort_icon($column, $currentSort, $currentDirection) {
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
                                        hx-get="{{ route('agreements.index', array_merge(request()->query(), [
                                            'sort' => 'name',
                                            'direction' => agreement_sort_direction('name', $currentSort, $currentDirection),
                                            'page' => 1,
                                        ])) }}"
                                        hx-target="#agreements-table"
                                        hx-swap="innerHTML"
                                        hx-push-url="true"
                                    >
                                        Agreement Name {{ agreement_sort_icon('name', $currentSort, $currentDirection) }}
                                    </button>
                                </th>

                                <th>
                                    <button
                                        class="btn btn-link p-0 text-decoration-none fw-semibold"
                                        hx-get="{{ route('agreements.index', array_merge(request()->query(), [
                                            'sort' => 'organization',
                                            'direction' => agreement_sort_direction('organization', $currentSort, $currentDirection),
                                            'page' => 1,
                                        ])) }}"
                                        hx-target="#agreements-table"
                                        hx-swap="innerHTML"
                                        hx-push-url="true"
                                    >
                                        Organization {{ agreement_sort_icon('organization', $currentSort, $currentDirection) }}
                                    </button>
                                </th>

                                <th>
                                    <button
                                        class="btn btn-link p-0 text-decoration-none fw-semibold"
                                        hx-get="{{ route('agreements.index', array_merge(request()->query(), [
                                            'sort' => 'state',
                                            'direction' => agreement_sort_direction('state', $currentSort, $currentDirection),
                                            'page' => 1,
                                        ])) }}"
                                        hx-target="#agreements-table"
                                        hx-swap="innerHTML"
                                        hx-push-url="true"
                                    >
                                        State {{ agreement_sort_icon('state', $currentSort, $currentDirection) }}
                                    </button>
                                </th>

                                <th>
                                    <button
                                        class="btn btn-link p-0 text-decoration-none fw-semibold"
                                        hx-get="{{ route('agreements.index', array_merge(request()->query(), [
                                            'sort' => 'start_date',
                                            'direction' => agreement_sort_direction('start_date', $currentSort, $currentDirection),
                                            'page' => 1,
                                        ])) }}"
                                        hx-target="#agreements-table"
                                        hx-swap="innerHTML"
                                        hx-push-url="true"
                                    >
                                        Start Date {{ agreement_sort_icon('start_date', $currentSort, $currentDirection) }}
                                    </button>
                                </th>

                                <th>
                                    <button
                                        class="btn btn-link p-0 text-decoration-none fw-semibold"
                                        hx-get="{{ route('agreements.index', array_merge(request()->query(), [
                                            'sort' => 'team_members',
                                            'direction' => agreement_sort_direction('team_members', $currentSort, $currentDirection),
                                            'page' => 1,
                                        ])) }}"
                                        hx-target="#agreements-table"
                                        hx-swap="innerHTML"
                                        hx-push-url="true"
                                    >
                                        Team Members {{ agreement_sort_icon('team_members', $currentSort, $currentDirection) }}
                                    </button>
                                </th>

                                @if(auth()->user()->isAdmin())
                                    <th style="width: 50px;"></th>
                                @endif
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($agreements as $agreement)
                                <tr>
                                    <td>
                                        <a href="{{ route('agreements.show', $agreement) }}" class="text-decoration-none text-dark d-block">
                                            <strong>{{ $agreement->name }}</strong>
                                        </a>
                                    </td>
                                    <td>{{ $agreement->organization->name }}</td>
                                    <td>{{ $agreement->state->name }}</td>
                                    <td>{{ $agreement->start_date?->format('M d, Y') ?? 'N/A' }}</td>
                                    <td>{{ $agreement->users->count() }}</td>

                                    @if(auth()->user()->isAdmin())
                                        <td onclick="event.stopPropagation();">
                                            <div class="dropdown">
                                                <button class="btn btn-sm btn-link text-muted" type="button" data-bs-toggle="dropdown">
                                                    ⋯
                                                </button>
                                                <ul class="dropdown-menu dropdown-menu-end">
                                                    <li>
                                                        <a class="dropdown-item" href="{{ route('agreements.edit', $agreement) }}">Edit</a>
                                                    </li>
                                                    <li><hr class="dropdown-divider"></li>
                                                    <li>
                                                        <form method="POST" action="{{ route('agreements.destroy', $agreement) }}"
                                                              hx-confirm="Are you sure you want to delete this project?">
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
                                    <td colspan="{{ auth()->user()->isAdmin() ? '6' : '5' }}" class="text-center text-muted">
                                        @if(auth()->user()->isAdmin())
                                            No agreements found
                                        @else
                                            You are not assigned to any agreements
                                        @endif
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                @if ($agreements->hasPages())
                    <div class="mt-3 d-flex justify-content-center">
                        <nav>
                            <ul class="pagination mb-0">
                                {{-- Previous Page Link --}}
                                @if ($agreements->onFirstPage())
                                    <li class="page-item disabled"><span class="page-link">‹</span></li>
                                @else
                                    <li class="page-item">
                                        <a
                                            class="page-link"
                                            hx-get="{{ $agreements->previousPageUrl() }}"
                                            hx-target="#agreements-table"
                                            hx-swap="innerHTML"
                                            hx-push-url="true"
                                        >‹</a>
                                    </li>
                                @endif

                                {{-- Pagination Elements --}}
                                @foreach ($agreements->linkCollection()->elements as $element)
                                    @if (is_string($element))
                                        <li class="page-item disabled"><span class="page-link">{{ $element }}</span></li>
                                    @endif

                                    @if (is_array($element))
                                        @foreach ($element as $page => $url)
                                            @if ($page == $agreements->currentPage())
                                                <li class="page-item active"><span class="page-link">{{ $page }}</span></li>
                                            @else
                                                <li class="page-item">
                                                    <a
                                                        class="page-link"
                                                        hx-get="{{ $url }}"
                                                        hx-target="#agreements-table"
                                                        hx-swap="innerHTML"
                                                        hx-push-url="true"
                                                    >{{ $page }}</a>
                                                </li>
                                            @endif
                                        @endforeach
                                    @endif
                                @endforeach

                                {{-- Next Page Link --}}
                                @if ($agreements->hasMorePages())
                                    <li class="page-item">
                                        <a
                                            class="page-link"
                                            hx-get="{{ $agreements->nextPageUrl() }}"
                                            hx-target="#agreements-table"
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