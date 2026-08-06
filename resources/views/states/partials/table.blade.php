@php
    $s = $sort ?? 'name';
    $d = $direction ?? 'asc';
    $flip = fn ($col) => ($s === $col && $d === 'asc') ? 'desc' : 'asc';
    $url = fn ($col) => route('states.index', array_merge(request()->query(), ['sort' => $col, 'direction' => $flip($col), 'page' => 1]));
@endphp

<div class="card shadow-sm app-index-table-card">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
                <tr>
                    <th>
                        <x-table-sort-link column="name" label="Name" :sort="$s" :direction="$d" :url="$url('name')" target="#states-table" />
                    </th>
                    <th>
                        <x-table-sort-link column="organizations" label="Organizations" :sort="$s" :direction="$d" :url="$url('organizations')" target="#states-table" />
                    </th>
                    <th>
                        <x-table-sort-link column="agreements" label="Agreements" :sort="$s" :direction="$d" :url="$url('agreements')" target="#states-table" />
                    </th>
                    <th class="text-nowrap">
                        <x-table-sort-link column="created" label="Created" :sort="$s" :direction="$d" :url="$url('created')" target="#states-table" />
                    </th>
                    <th class="text-end" style="width:130px;">Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse($states as $state)
                <tr>
                    <td>
                        <a href="{{ route('states.show', $state) }}" class="fw-semibold text-decoration-none text-dark">
                            {{ $state->name }}
                        </a>
                    </td>
                    <td>
                        <span class="badge bg-secondary">{{ $state->organizations_count }}</span>
                    </td>
                    <td>
                        <span class="badge bg-secondary">{{ $state->agreements_count }}</span>
                    </td>
                    <td class="text-muted small">{{ $state->created_at->format('M d, Y') }}</td>
                    <td class="text-end text-nowrap">
                        @php $actionKey = 'state-actions-' . $state->id; @endphp
                        <div class="btn-group btn-group-sm" role="group" aria-label="State actions for {{ $state->name }}">
                            <a href="{{ route('states.show', $state) }}"
                               class="btn btn-outline-primary"
                               data-bs-toggle="tooltip"
                               data-bs-title="View state"
                               aria-label="View state">
                                <i class="bi bi-eye"></i>
                            </a>
                            <a href="{{ route('states.edit', $state) }}"
                               class="btn btn-outline-secondary"
                               data-bs-toggle="tooltip"
                               data-bs-title="Edit state"
                               aria-label="Edit state">
                                <i class="bi bi-pencil-square"></i>
                            </a>
                            <button type="submit"
                                    form="{{ $actionKey }}-delete"
                                    class="btn btn-outline-danger"
                                    data-bs-toggle="tooltip"
                                    data-bs-title="Delete state"
                                    aria-label="Delete state"
                                    onclick="return confirm('Delete state {{ addslashes($state->name) }}?')">
                                <i class="bi bi-trash"></i>
                            </button>
                        </div>
                        <form id="{{ $actionKey }}-delete" method="POST" action="{{ route('states.destroy', $state) }}" class="d-none">
                            @csrf
                            @method('DELETE')
                        </form>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="5" class="text-center py-5">
                        <p class="text-muted mb-2">No states found.</p>
                        <a href="{{ route('states.create') }}" class="btn btn-sm btn-primary">Create State</a>
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="card-footer bg-white">
        <x-htmx-pagination :paginator="$states" target="#states-table" />
    </div>
</div>

@once
    <script>
    (function () {
        document.body.addEventListener('htmx:afterSwap', function (event) {
            if (event.target && event.target.id === 'states-table') {
                var params = new URLSearchParams(window.location.search);
                var form = document.getElementById('state-filters');
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
