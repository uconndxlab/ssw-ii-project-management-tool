<div class="card shadow-sm">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
                <tr>
                    <th>Name</th>
                    <th>Description</th>
                    <th>Programs</th>
                    <th>Status</th>
                    <th class="text-end" style="width:170px;">Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse($projects as $project)
                <tr>
                    <td>
                        <a href="{{ route('projects.show', $project) }}" class="fw-semibold text-decoration-none text-dark">
                            {{ $project->name }}
                        </a>
                    </td>
                    <td class="text-muted small">{{ Str::limit($project->description, 60) }}</td>
                    <td>
                        <span class="badge bg-secondary">{{ $project->programs_count }} {{ Str::plural('program', $project->programs_count) }}</span>
                    </td>
                    <td>
                        @if($project->active)
                            <span class="badge bg-success">Active</span>
                        @else
                            <span class="badge bg-secondary">Inactive</span>
                        @endif
                    </td>
                    <td class="text-end">
                        <div class="d-flex gap-1 justify-content-end flex-nowrap">
                            <a href="{{ route('projects.show', $project) }}" class="btn btn-sm btn-outline-primary">View</a>
                            <a href="{{ route('projects.edit', $project) }}" class="btn btn-sm btn-outline-secondary">Edit</a>
                            <form method="POST" action="{{ route('projects.destroy', $project) }}" class="d-inline">
                                @csrf @method('DELETE')
                                <button type="submit" class="btn btn-sm btn-outline-danger"
                                    onclick="return confirm('Delete {{ addslashes($project->name) }}? This will also delete all {{ $project->programs_count }} associated programs.')">Delete</button>
                            </form>
                        </div>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="5" class="text-center py-5">
                        <p class="text-muted mb-2">No projects found.</p>
                        <a href="{{ route('projects.create') }}" class="btn btn-sm btn-primary">Create Project</a>
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="card-footer bg-white">
        <x-htmx-pagination :paginator="$projects" target="#projects-table" />
    </div>
</div>
