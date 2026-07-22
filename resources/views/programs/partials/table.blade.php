@php
    $s    = $sort ?? 'name';
    $d    = $direction ?? 'asc';
    $flip = fn($col) => ($s === $col && $d === 'asc') ? 'desc' : 'asc';
    $icon = fn($col) => $s === $col ? ($d === 'asc' ? ' ↑' : ' ↓') : '';
    $url  = fn($col) => route('programs.index', array_merge(request()->query(), ['sort' => $col, 'direction' => $flip($col), 'page' => 1]));
@endphp

<div class="card shadow-sm">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
                <tr>
                    <th>
                        <a class="text-decoration-none fw-semibold text-dark"
                           href="{{ $url('name') }}"
                           hx-get="{{ $url('name') }}" hx-target="#programs-table" hx-push-url="true">
                            Name{!! $icon('name') !!}
                        </a>
                    </th>
                    <th>Projects</th>
                    <th>
                        <a class="text-decoration-none fw-semibold text-dark"
                           href="{{ $url('status') }}"
                           hx-get="{{ $url('status') }}" hx-target="#programs-table" hx-push-url="true">
                            Status{!! $icon('status') !!}
                        </a>
                    </th>
                    <th>
                        <a class="text-decoration-none fw-semibold text-dark"
                           href="{{ $url('created') }}"
                           hx-get="{{ $url('created') }}" hx-target="#programs-table" hx-push-url="true">
                            Created{!! $icon('created') !!}
                        </a>
                    </th>
                    <th class="text-end" style="width:170px;">Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse($programs as $program)
                <tr>
                    <td>
                        <a href="{{ route('programs.show', $program) }}" class="fw-semibold text-decoration-none text-dark">
                            {{ $program->name }}
                        </a>
                    </td>
                    <td>
                        @if($program->projects->isNotEmpty())
                            <div class="d-flex flex-wrap gap-1">
                                @foreach($program->projects->sortBy('name') as $project)
                                    <a href="{{ route('projects.show', $project) }}" class="text-decoration-none text-muted small">{{ $project->name }}</a>@if(!$loop->last)<span class="text-muted small">,</span>@endif
                                @endforeach
                            </div>
                        @else
                            <span class="text-muted small">—</span>
                        @endif
                    </td>
                    <td>
                        @if($program->active)
                            <span class="badge bg-success">Active</span>
                        @else
                            <span class="badge bg-secondary">Inactive</span>
                        @endif
                    </td>
                    <td class="text-muted small">{{ $program->created_at->format('M d, Y') }}</td>
                    <td class="text-end">
                        <div class="d-flex gap-1 justify-content-end flex-nowrap">
                            <a href="{{ route('programs.show', $program) }}" class="btn btn-sm btn-outline-primary">View</a>
                            <a href="{{ route('programs.edit', $program) }}" class="btn btn-sm btn-outline-secondary">Edit</a>
                            <form method="POST" action="{{ route('programs.destroy', $program) }}" class="d-inline">
                                @csrf @method('DELETE')
                                <button type="submit" class="btn btn-sm btn-outline-danger"
                                    onclick="return confirm('Delete {{ addslashes($program->name) }}?')">Delete</button>
                            </form>
                        </div>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="5" class="text-center py-5">
                        <p class="text-muted mb-2">No programs found.</p>
                        <a href="{{ route('programs.create') }}" class="btn btn-sm btn-primary">Create Program</a>
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="card-footer bg-white">
        <x-htmx-pagination :paginator="$programs" target="#programs-table" />
    </div>
</div>
