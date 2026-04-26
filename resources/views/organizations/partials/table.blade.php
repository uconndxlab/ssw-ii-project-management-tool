@php
    $s    = $sort ?? 'name';
    $d    = $direction ?? 'asc';
    $flip = fn($col) => ($s === $col && $d === 'asc') ? 'desc' : 'asc';
    $icon = fn($col) => $s === $col ? ($d === 'asc' ? ' ↑' : ' ↓') : '';
    $url  = fn($col) => route('organizations.index', array_merge(request()->query(), ['sort' => $col, 'direction' => $flip($col), 'page' => 1]));
@endphp

<div class="card shadow-sm">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
                <tr>
                    <th>
                        <a class="text-decoration-none fw-semibold text-dark"
                           href="{{ $url('name') }}"
                           hx-get="{{ $url('name') }}" hx-target="#organizations-table" hx-push-url="true">
                            Name{!! $icon('name') !!}
                        </a>
                    </th>
                    <th>
                        <a class="text-decoration-none fw-semibold text-dark"
                           href="{{ $url('state') }}"
                           hx-get="{{ $url('state') }}" hx-target="#organizations-table" hx-push-url="true">
                            State{!! $icon('state') !!}
                        </a>
                    </th>
                    <th>
                        <a class="text-decoration-none fw-semibold text-dark"
                           href="{{ $url('agreements') }}"
                           hx-get="{{ $url('agreements') }}" hx-target="#organizations-table" hx-push-url="true">
                            Agreements{!! $icon('agreements') !!}
                        </a>
                    </th>
                    <th>
                        <a class="text-decoration-none fw-semibold text-dark"
                           href="{{ $url('created') }}"
                           hx-get="{{ $url('created') }}" hx-target="#organizations-table" hx-push-url="true">
                            Created{!! $icon('created') !!}
                        </a>
                    </th>
                    <th class="text-end" style="width:{{ auth()->user()->isAdmin() ? '170px' : '80px' }};">Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse($organizations as $organization)
                <tr>
                    <td>
                        <a href="{{ route('organizations.show', $organization) }}" class="fw-semibold text-decoration-none text-dark">
                            {{ $organization->name }}
                        </a>
                    </td>
                    <td class="text-muted small">{{ $organization->state->name ?? '—' }}</td>
                    <td>
                        <span class="badge bg-secondary">{{ $organization->agreements_count ?? $organization->agreements->count() }}</span>
                    </td>
                    <td class="text-muted small">{{ $organization->created_at->format('M d, Y') }}</td>
                    <td class="text-end">
                        <div class="d-flex gap-1 justify-content-end flex-nowrap">
                            <a href="{{ route('organizations.show', $organization) }}" class="btn btn-sm btn-outline-primary">View</a>
                            @if(auth()->user()->isAdmin())
                                <a href="{{ route('organizations.edit', $organization) }}" class="btn btn-sm btn-outline-secondary">Edit</a>
                                <form method="POST" action="{{ route('organizations.destroy', $organization) }}" class="d-inline">
                                    @csrf @method('DELETE')
                                    <button type="submit" class="btn btn-sm btn-outline-danger"
                                        onclick="return confirm('Delete {{ addslashes($organization->name) }}?')">Delete</button>
                                </form>
                            @endif
                        </div>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="5" class="text-center py-5">
                        <p class="text-muted mb-2">No organizations found.</p>
                        @if(auth()->user()->isAdmin())
                            <a href="{{ route('organizations.create') }}" class="btn btn-sm btn-primary">Create Organization</a>
                        @endif
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="card-footer bg-white">
        <x-htmx-pagination :paginator="$organizations" target="#organizations-table" />
    </div>
</div>
