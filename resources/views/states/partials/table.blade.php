@php
    $s = $sort ?? 'name';
    $d = $direction ?? 'asc';
    $flip = fn ($col) => ($s === $col && $d === 'asc') ? 'desc' : 'asc';
    $url = fn ($col) => route('states.index', array_merge(request()->query(), ['sort' => $col, 'direction' => $flip($col), 'page' => 1]));
@endphp

<div class="card shadow-sm app-index-table-card">
    <x-table-scroll>
        <table class="table table-hover align-middle mb-0">
            <thead>
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
                    <th class="app-col-actions"><span class="visually-hidden">Actions</span></th>
                </tr>
            </thead>
            <tbody>
                @forelse($states as $state)
                <tr>
                    <td>
                        <a href="{{ route('states.show', $state) }}" class="app-link app-link--strong">
                            {{ $state->name }}
                        </a>
                    </td>
                    <td>
                        <span class="app-count">{{ $state->organizations_count }}</span>
                    </td>
                    <td>
                        <span class="app-count">{{ $state->agreements_count }}</span>
                    </td>
                    <td class="text-muted small">{{ $state->created_at->format('M d, Y') }}</td>
                    <td class="app-col-actions">
                        @php $actionKey = 'state-actions-' . $state->id; @endphp
                        <div class="dropdown app-row-actions d-inline-block">
                            <button type="button" class="btn btn-sm btn-link app-row-actions-toggle" data-bs-toggle="dropdown" data-bs-popper-config='{"strategy":"fixed"}' aria-expanded="false" aria-label="State actions for {{ $state->name }}"><i class="bi bi-three-dots" aria-hidden="true"></i></button>
                            <div class="dropdown-menu dropdown-menu-end">
                            <a href="{{ route('states.show', $state) }}" class="dropdown-item"><i class="bi bi-eye me-2" aria-hidden="true"></i>View state</a>
                            @can('update', $state)
                            <a href="{{ route('states.edit', $state) }}" class="dropdown-item"><i class="bi bi-pencil-square me-2" aria-hidden="true"></i>Edit state</a>
                            @endcan
                            @can('delete', $state)
                            <hr class="dropdown-divider">
                            <button type="submit"
                                    form="{{ $actionKey }}-delete"
                                    onclick="return confirm('Delete state {{ addslashes($state->name) }}?')" class="dropdown-item text-danger"><i class="bi bi-trash me-2" aria-hidden="true"></i>Delete state</button>
                            @endcan
                            </div>
                        </div>
                        @can('delete', $state)
                        <form id="{{ $actionKey }}-delete" method="POST" action="{{ route('states.destroy', $state) }}" class="d-none">
                            @csrf
                            @method('DELETE')
                        </form>
                        @endcan
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="5" class="text-center py-5">
                        <p class="text-muted mb-2">No states found.</p>
                        @can('create', App\Models\State::class)
                        <a href="{{ route('states.create') }}" class="btn btn-sm btn-primary">Create State</a>
                        @endcan
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </x-table-scroll>
    <div class="card-footer">
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
