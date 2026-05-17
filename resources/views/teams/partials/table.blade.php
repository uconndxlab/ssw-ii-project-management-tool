@php
    $s    = $sort ?? 'name';
    $d    = $direction ?? 'asc';
    $flip = fn($col) => ($s === $col && $d === 'asc') ? 'desc' : 'asc';
    $icon = fn($col) => $s === $col ? ($d === 'asc' ? ' ↑' : ' ↓') : '';
    $url  = fn($col) => route('teams.index', array_merge(request()->query(), ['sort' => $col, 'direction' => $flip($col), 'page' => 1]));
@endphp

<div class="card shadow-sm">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
                <tr>
                    <th>
                        <a class="text-decoration-none fw-semibold text-dark"
                           href="{{ $url('name') }}"
                           hx-get="{{ $url('name') }}" hx-target="#teams-table" hx-push-url="true">
                            Name{!! $icon('name') !!}
                        </a>
                    </th>
                    <th>
                        <a class="text-decoration-none fw-semibold text-dark"
                           href="{{ $url('members') }}"
                           hx-get="{{ $url('members') }}" hx-target="#teams-table" hx-push-url="true">
                            Members{!! $icon('members') !!}
                        </a>
                    </th>
                    <th>
                        <a class="text-decoration-none fw-semibold text-dark"
                           href="{{ $url('active') }}"
                           hx-get="{{ $url('active') }}" hx-target="#teams-table" hx-push-url="true">
                            Status{!! $icon('active') !!}
                        </a>
                    </th>
                    <th class="text-end" style="width:170px;">Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse($teams as $team)
                <tr>
                    <td>
                        <a href="{{ route('teams.show', $team) }}" class="fw-semibold text-decoration-none text-dark">
                            {{ $team->name }}
                        </a>
                    </td>
                    <td>
                        <span class="badge bg-secondary">{{ $team->users_count }} {{ Str::plural('member', $team->users_count) }}</span>
                    </td>
                    <td>
                        @if($team->active)
                            <span class="badge bg-success">Active</span>
                        @else
                            <span class="badge bg-secondary">Inactive</span>
                        @endif
                    </td>
                    <td class="text-end">
                        <div class="d-flex gap-1 justify-content-end flex-nowrap">
                            <a href="{{ route('teams.show', $team) }}" class="btn btn-sm btn-outline-primary">View</a>
                            <a href="{{ route('teams.edit', $team) }}" class="btn btn-sm btn-outline-secondary">Edit</a>
                            <form method="POST" action="{{ route('teams.destroy', $team) }}" class="d-inline">
                                @csrf @method('DELETE')
                                <button type="submit" class="btn btn-sm btn-outline-danger"
                                    onclick="return confirm('Delete team {{ addslashes($team->name) }}?')">Delete</button>
                            </form>
                        </div>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="4" class="text-center py-5">
                        <p class="text-muted mb-2">No teams found.</p>
                        <a href="{{ route('teams.create') }}" class="btn btn-sm btn-primary">Create Team</a>
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="card-footer bg-white">
        <x-htmx-pagination :paginator="$teams" target="#teams-table" />
    </div>
</div>
