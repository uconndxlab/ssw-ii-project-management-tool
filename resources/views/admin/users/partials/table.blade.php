@php
    $s    = $sort ?? 'name';
    $d    = $direction ?? 'asc';
    $flip = fn($col) => ($s === $col && $d === 'asc') ? 'desc' : 'asc';
    $icon = fn($col) => $s === $col ? ($d === 'asc' ? ' ↑' : ' ↓') : '';
    $url  = fn($col) => route('admin.users.index', array_merge(request()->query(), ['sort' => $col, 'direction' => $flip($col), 'page' => 1]));
@endphp

<div class="card shadow-sm">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
                <tr>
                    <th>
                        <a class="text-decoration-none fw-semibold text-dark"
                           href="{{ $url('name') }}"
                           hx-get="{{ $url('name') }}" hx-target="#users-table" hx-push-url="true">
                            Name{!! $icon('name') !!}
                        </a>
                    </th>
                    <th>
                        <a class="text-decoration-none fw-semibold text-dark"
                           href="{{ $url('email') }}"
                           hx-get="{{ $url('email') }}" hx-target="#users-table" hx-push-url="true">
                            Email{!! $icon('email') !!}
                        </a>
                    </th>
                    <th>
                        <a class="text-decoration-none fw-semibold text-dark"
                           href="{{ $url('role') }}"
                           hx-get="{{ $url('role') }}" hx-target="#users-table" hx-push-url="true">
                            Role{!! $icon('role') !!}
                        </a>
                    </th>
                    <th>Projects</th>
                    <th>Programs</th>
                    <th>
                        <a class="text-decoration-none fw-semibold text-dark"
                           href="{{ $url('created') }}"
                           hx-get="{{ $url('created') }}" hx-target="#users-table" hx-push-url="true">
                            Created{!! $icon('created') !!}
                        </a>
                    </th>
                    <th class="text-end" style="width:220px;">Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse($users as $user)
                <tr>
                    <td>
                        <a href="{{ route('users.show', $user) }}" class="fw-semibold text-decoration-none text-dark">
                            {{ $user->name }}
                        </a>
                    </td>
                    <td class="text-muted small">{{ $user->email }}</td>
                    <td>
                        <span class="badge
                            @if($user->role === 'admin') bg-danger
                            @elseif($user->role === 'consultant') bg-info text-dark
                            @else bg-secondary
                            @endif">
                            {{ ucfirst($user->role) }}
                        </span>
                    </td>
                    <td>
                        <div class="d-flex flex-wrap gap-1">
                            @forelse($user->projects->sortBy('name') as $project)
                                <span class="badge bg-primary">{{ $project->name }}</span>
                            @empty
                                <span class="text-muted small">—</span>
                            @endforelse
                        </div>
                    </td>
                    <td>
                        <div class="d-flex flex-wrap gap-1">
                            @forelse($user->programs->sortBy('name') as $program)
                                <span class="badge bg-warning text-dark">{{ $program->name }}</span>
                            @empty
                                <span class="text-muted small">—</span>
                            @endforelse
                        </div>
                    </td>
                    <td class="text-muted small">{{ $user->created_at->format('M d, Y') }}</td>
                    <td class="text-end">
                        <div class="d-flex gap-1 justify-content-end flex-nowrap">
                            <a href="{{ route('users.show', $user) }}" class="btn btn-sm btn-outline-primary">View</a>
                            <a href="{{ route('admin.users.edit', $user) }}" class="btn btn-sm btn-outline-secondary">Edit</a>
                            <form method="POST" action="{{ route('admin.users.destroy', $user) }}" class="d-inline">
                                @csrf @method('DELETE')
                                <button type="submit" class="btn btn-sm btn-outline-danger"
                                    onclick="return confirm('Delete {{ addslashes($user->name) }}?')">Delete</button>
                            </form>
                        </div>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="7" class="text-center py-5">
                        <p class="text-muted mb-2">No users found.</p>
                        <a href="{{ route('admin.users.create') }}" class="btn btn-sm btn-primary">Create User</a>
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="card-footer bg-white">
        <x-htmx-pagination :paginator="$users" target="#users-table" />
    </div>
</div>
