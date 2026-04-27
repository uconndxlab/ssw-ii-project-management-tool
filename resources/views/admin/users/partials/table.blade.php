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
                    <th>
                        <a class="text-decoration-none fw-semibold text-dark"
                           href="{{ $url('created') }}"
                           hx-get="{{ $url('created') }}" hx-target="#users-table" hx-push-url="true">
                            Created{!! $icon('created') !!}
                        </a>
                    </th>
                    <th class="text-end" style="width:80px;">Actions</th>
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
                    <td class="text-muted small">{{ $user->created_at->format('M d, Y') }}</td>
                    <td class="text-end">
                        <a href="{{ route('users.show', $user) }}" class="btn btn-sm btn-outline-primary">View</a>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="5" class="text-center py-5">
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
