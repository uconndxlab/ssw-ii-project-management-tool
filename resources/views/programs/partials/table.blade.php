@php
    $currentSort = $sort ?? 'name';
    $currentDirection = $direction ?? 'asc';

    function program_sort_direction($column, $currentSort, $currentDirection) {
        if ($currentSort === $column) {
            return $currentDirection === 'asc' ? 'desc' : 'asc';
        }

        return 'asc';
    }

    function program_sort_icon($column, $currentSort, $currentDirection) {
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
                                        hx-get="{{ route('programs.index', array_merge(request()->query(), [
                                            'sort' => 'name',
                                            'direction' => program_sort_direction('name', $currentSort, $currentDirection),
                                            'page' => 1,
                                        ])) }}"
                                        hx-target="#programs-table"
                                        hx-swap="innerHTML"
                                        hx-push-url="true"
                                    >
                                        Name {{ program_sort_icon('name', $currentSort, $currentDirection) }}
                                    </button>
                                </th>

                                <th>
                                    <button
                                        class="btn btn-link p-0 text-decoration-none fw-semibold"
                                        hx-get="{{ route('programs.index', array_merge(request()->query(), [
                                            'sort' => 'status',
                                            'direction' => program_sort_direction('status', $currentSort, $currentDirection),
                                            'page' => 1,
                                        ])) }}"
                                        hx-target="#programs-table"
                                        hx-swap="innerHTML"
                                        hx-push-url="true"
                                    >
                                        Status {{ program_sort_icon('status', $currentSort, $currentDirection) }}
                                    </button>
                                </th>

                                <th>
                                    <button
                                        class="btn btn-link p-0 text-decoration-none fw-semibold"
                                        hx-get="{{ route('programs.index', array_merge(request()->query(), [
                                            'sort' => 'created',
                                            'direction' => program_sort_direction('created', $currentSort, $currentDirection),
                                            'page' => 1,
                                        ])) }}"
                                        hx-target="#programs-table"
                                        hx-swap="innerHTML"
                                        hx-push-url="true"
                                    >
                                        Created {{ program_sort_icon('created', $currentSort, $currentDirection) }}
                                    </button>
                                </th>

                                <th style="width: 50px;"></th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($programs as $program)
                                <tr>
                                    <td><strong>{{ $program->name }}</strong></td>
                                    <td>
                                        @if($program->active)
                                            <span class="badge bg-success">Active</span>
                                        @else
                                            <span class="badge bg-secondary">Inactive</span>
                                        @endif
                                    </td>
                                    <td>{{ $program->created_at->format('M d, Y') }}</td>
                                    <td onclick="event.stopPropagation()">
                                        <div class="dropdown">
                                            <button class="btn btn-sm btn-link text-secondary" type="button" data-bs-toggle="dropdown">
                                                ⋯
                                            </button>
                                            <ul class="dropdown-menu dropdown-menu-end">
                                                <li>
                                                    <a class="dropdown-item" href="{{ route('programs.edit', $program) }}">Edit</a>
                                                </li>
                                                <li>
                                                    <form method="POST" action="{{ route('programs.destroy', $program) }}"
                                                          hx-confirm="Are you sure you want to delete this program?">
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
                                    <td colspan="4" class="text-center text-muted">No programs found</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                @if ($programs->hasPages())
                    <div class="mt-3 d-flex justify-content-center">
                        <nav>
                            <ul class="pagination mb-0">
                                @if ($programs->onFirstPage())
                                    <li class="page-item disabled"><span class="page-link">‹</span></li>
                                @else
                                    <li class="page-item">
                                        <a
                                            class="page-link"
                                            hx-get="{{ $programs->previousPageUrl() }}"
                                            hx-target="#programs-table"
                                            hx-swap="innerHTML"
                                            hx-push-url="true"
                                        >‹</a>
                                    </li>
                                @endif

                                @for ($i = 1; $i <= $programs->lastPage(); $i++)
                                    @if ($i == $programs->currentPage())
                                        <li class="page-item active">
                                            <span class="page-link">{{ $i }}</span>
                                        </li>
                                    @else
                                        <li class="page-item">
                                            <a
                                                class="page-link"
                                                hx-get="{{ $programs->url($i) }}"
                                                hx-target="#programs-table"
                                                hx-swap="innerHTML"
                                                hx-push-url="true"
                                            >{{ $i }}</a>
                                        </li>
                                    @endif
                                @endfor

                                @if ($programs->hasMorePages())
                                    <li class="page-item">
                                        <a
                                            class="page-link"
                                            hx-get="{{ $programs->nextPageUrl() }}"
                                            hx-target="#programs-table"
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