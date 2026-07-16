<div class="card shadow-sm">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
                <tr>
                    <th>Name</th>
                    <th>KFS</th>
                    <th>State(s)</th>
                    <th>Projects</th>
                    <th>Programs</th>
                    <th>Status</th>
                    <th>Agreements</th>
                    <th>Created</th>
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
                    <td class="text-muted small">{{ $organization->kfs_number ?: '—' }}</td>
                    <td>
                        @foreach($organization->states as $state)
                            <span class="badge bg-info text-dark me-1">{{ $state->name }}</span>
                        @endforeach
                        @if($organization->states->isEmpty())<span class="text-muted">—</span>@endif
                    </td>
                    <td>
                        @foreach($organization->projects as $project)
                            <span class="badge bg-primary-subtle text-primary-emphasis border me-1">{{ $project->name }}</span>
                        @endforeach
                        @if($organization->projects->isEmpty())<span class="text-muted">—</span>@endif
                    </td>
                    <td>
                        @foreach($organization->programs as $program)
                            <span class="badge bg-warning-subtle text-warning-emphasis border me-1">{{ $program->name }}</span>
                        @endforeach
                        @if($organization->programs->isEmpty())<span class="text-muted">—</span>@endif
                    </td>
                    <td>
                        @if($organization->active)
                            <span class="badge bg-success">Active</span>
                        @else
                            <span class="badge bg-secondary">Inactive</span>
                        @endif
                    </td>
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
                    <td colspan="9" class="text-center py-5">
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
