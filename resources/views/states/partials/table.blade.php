<div class="card shadow-sm">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
                <tr>
                    <th>Name</th>
                    <th>Organizations</th>
                    <th>Agreements</th>
                    <th>Created</th>
                    <th class="text-end" style="width:160px;">Actions</th>
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
                    <td>{{ $state->organizations_count }}</td>
                    <td>{{ $state->agreements_count }}</td>
                    <td class="text-muted small">{{ $state->created_at->format('M d, Y') }}</td>
                    <td class="text-end">
                        <div class="d-flex gap-1 justify-content-end flex-nowrap">
                            <a href="{{ route('states.show', $state) }}" class="btn btn-sm btn-outline-primary">View</a>
                            <a href="{{ route('states.edit', $state) }}" class="btn btn-sm btn-outline-secondary">Edit</a>
                            <form method="POST" action="{{ route('states.destroy', $state) }}" class="d-inline">
                                @csrf @method('DELETE')
                                <button type="submit" class="btn btn-sm btn-outline-danger"
                                    onclick="return confirm('Delete state {{ addslashes($state->name) }}?')">Delete</button>
                            </form>
                        </div>
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
