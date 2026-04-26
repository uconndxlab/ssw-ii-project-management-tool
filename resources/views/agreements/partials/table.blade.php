@php
    $s    = $sort ?? 'name';
    $d    = $direction ?? 'asc';
    $flip = fn($col) => ($s === $col && $d === 'asc') ? 'desc' : 'asc';
    $icon = fn($col) => $s === $col ? ($d === 'asc' ? ' ↑' : ' ↓') : '';
    $url  = fn($col) => route('agreements.index', array_merge(request()->query(), ['sort' => $col, 'direction' => $flip($col), 'page' => 1]));
@endphp

<div class="card shadow-sm">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
                <tr>
                    <th>
                        <a class="text-decoration-none fw-semibold text-dark"
                           href="{{ $url('name') }}"
                           hx-get="{{ $url('name') }}" hx-target="#agreements-table" hx-push-url="true">
                            Name{!! $icon('name') !!}
                        </a>
                    </th>
                    <th>
                        <a class="text-decoration-none fw-semibold text-dark"
                           href="{{ $url('organization') }}"
                           hx-get="{{ $url('organization') }}" hx-target="#agreements-table" hx-push-url="true">
                            Organization{!! $icon('organization') !!}
                        </a>
                    </th>
                    <th>
                        <a class="text-decoration-none fw-semibold text-dark"
                           href="{{ $url('state') }}"
                           hx-get="{{ $url('state') }}" hx-target="#agreements-table" hx-push-url="true">
                            State{!! $icon('state') !!}
                        </a>
                    </th>
                    <th>
                        <a class="text-decoration-none fw-semibold text-dark"
                           href="{{ $url('start_date') }}"
                           hx-get="{{ $url('start_date') }}" hx-target="#agreements-table" hx-push-url="true">
                            Start Date{!! $icon('start_date') !!}
                        </a>
                    </th>
                    <th>Team Members</th>
                    <th class="text-end" style="width:{{ auth()->user()->isAdmin() ? '170px' : '80px' }};">Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse($agreements as $agreement)
                <tr>
                    <td>
                        <a href="{{ route('agreements.show', $agreement) }}" class="fw-semibold text-decoration-none text-dark">
                            {{ $agreement->name }}
                        </a>
                    </td>
                    <td>
                        @forelse($agreement->organizations as $org)
                            <span class="badge bg-secondary me-1">{{ $org->name }}</span>
                        @empty
                            <span class="text-muted small">—</span>
                        @endforelse
                    </td>
                    <td>
                        @forelse($agreement->states as $state)
                            <span class="badge bg-info text-dark me-1">{{ $state->name }}</span>
                        @empty
                            <span class="text-muted small">—</span>
                        @endforelse
                    </td>
                    <td class="text-muted small">{{ $agreement->start_date?->format('M d, Y') ?? '—' }}</td>
                    <td>
                        @php
                            $members = $agreement->users->sortBy('name')->pluck('name');
                            $visible = $members->take(2);
                            $extra   = $members->count() - $visible->count();
                        @endphp
                        @if($members->isNotEmpty())
                            <span class="small" title="{{ $members->join(', ') }}">
                                {{ $visible->join(', ') }}
                                @if($extra > 0)<span class="text-muted">+{{ $extra }} more</span>@endif
                            </span>
                        @else
                            <span class="text-muted small">—</span>
                        @endif
                    </td>
                    <td class="text-end">
                        <div class="d-flex gap-1 justify-content-end flex-nowrap">
                            <a href="{{ route('agreements.show', $agreement) }}" class="btn btn-sm btn-outline-primary">View</a>
                            @if(auth()->user()->isAdmin())
                                <a href="{{ route('agreements.edit', $agreement) }}" class="btn btn-sm btn-outline-secondary">Edit</a>
                                <form method="POST" action="{{ route('agreements.destroy', $agreement) }}" class="d-inline">
                                    @csrf @method('DELETE')
                                    <button type="submit" class="btn btn-sm btn-outline-danger"
                                        onclick="return confirm('Delete {{ addslashes($agreement->name) }}?')">Delete</button>
                                </form>
                            @endif
                        </div>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="6" class="text-center py-5">
                        <p class="text-muted mb-2">
                            @if(auth()->user()->isAdmin()) No agreements found.
                            @else You are not assigned to any agreements.
                            @endif
                        </p>
                        @if(auth()->user()->isAdmin())
                            <a href="{{ route('agreements.create') }}" class="btn btn-sm btn-primary">Create Agreement</a>
                        @endif
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="card-footer bg-white">
        <x-htmx-pagination :paginator="$agreements" target="#agreements-table" />
    </div>
</div>
