@extends('layouts.app')

@section('title', 'Projects')

@section('content')
<div class="row mb-4">
    <div class="col-12 d-flex justify-content-between align-items-center">
        <h1>Projects</h1>
        <a href="{{ route('projects.create') }}" class="btn btn-primary">Create Project</a>
    </div>
</div>

@if(session('success'))
    <div class="alert alert-success alert-dismissible fade show" role="alert">
        {{ session('success') }}
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
@endif

<div class="card mb-4">
    <div class="card-body">
        <form method="GET" action="{{ route('projects.index') }}" class="row g-3">
            <div class="col-md-4">
                <input type="text" 
                       name="search" 
                       class="form-control" 
                       placeholder="Search projects..." 
                       value="{{ request('search') }}">
            </div>
            
            <div class="col-md-3">
                <select name="active" class="form-select">
                    <option value="">All Projects</option>
                    <option value="1" {{ request('active') === '1' ? 'selected' : '' }}>Active Only</option>
                    <option value="0" {{ request('active') === '0' ? 'selected' : '' }}>Inactive Only</option>
                </select>
            </div>
            
            <div class="col-md-5 d-flex gap-2">
                <button type="submit" class="btn btn-secondary">Filter</button>
                @if(request()->hasAny(['search', 'active']))
                    <a href="{{ route('projects.index') }}" class="btn btn-outline-secondary">Clear</a>
                @endif
            </div>
        </form>
    </div>
</div>

<div class="card">
    <div class="table-responsive">
        <table class="table table-hover mb-0">
            <thead>
                <tr>
                    <th>
                        <a href="{{ route('projects.index', array_merge(request()->query(), ['sort' => 'name', 'direction' => request('sort') === 'name' && request('direction') === 'asc' ? 'desc' : 'asc'])) }}"
                           class="text-decoration-none text-dark">
                            Name
                            @if(request('sort') === 'name')
                                <i class="bi bi-arrow-{{ request('direction') === 'desc' ? 'down' : 'up' }}"></i>
                            @endif
                        </a>
                    </th>
                    <th>Description</th>
                    <th>
                        <a href="{{ route('projects.index', array_merge(request()->query(), ['sort' => 'programs', 'direction' => request('sort') === 'programs' && request('direction') === 'asc' ? 'desc' : 'asc'])) }}"
                           class="text-decoration-none text-dark">
                            Programs
                            @if(request('sort') === 'programs')
                                <i class="bi bi-arrow-{{ request('direction') === 'desc' ? 'down' : 'up' }}"></i>
                            @endif
                        </a>
                    </th>
                    <th>
                        <a href="{{ route('projects.index', array_merge(request()->query(), ['sort' => 'active', 'direction' => request('sort') === 'active' && request('direction') === 'asc' ? 'desc' : 'asc'])) }}"
                           class="text-decoration-none text-dark">
                            Status
                            @if(request('sort') === 'active')
                                <i class="bi bi-arrow-{{ request('direction') === 'desc' ? 'down' : 'up' }}"></i>
                            @endif
                        </a>
                    </th>
                    <th class="text-end">Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse($projects as $project)
                <tr>
                    <td>
                        <a href="{{ route('projects.show', $project) }}" class="text-decoration-none">
                            <strong>{{ $project->name }}</strong>
                        </a>
                    </td>
                    <td>{{ Str::limit($project->description, 50) }}</td>
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
                        <div class="btn-group btn-group-sm">
                            <a href="{{ route('projects.show', $project) }}" class="btn btn-outline-primary">View</a>
                            <a href="{{ route('projects.edit', $project) }}" class="btn btn-outline-secondary">Edit</a>
                            <button type="button" 
                                    class="btn btn-outline-danger"
                                    data-bs-toggle="modal" 
                                    data-bs-target="#deleteModal{{ $project->id }}">
                                Delete
                            </button>
                        </div>

                        <!-- Delete Confirmation Modal -->
                        <div class="modal fade" id="deleteModal{{ $project->id }}" tabindex="-1">
                            <div class="modal-dialog">
                                <div class="modal-content">
                                    <div class="modal-header">
                                        <h5 class="modal-title">Confirm Delete</h5>
                                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                    </div>
                                    <div class="modal-body">
                                        Are you sure you want to delete "{{ $project->name }}"? This will also delete all {{ $project->programs_count }} associated programs.
                                    </div>
                                    <div class="modal-footer">
                                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                        <form method="POST" action="{{ route('projects.destroy', $project) }}" class="d-inline">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="btn btn-danger">Delete</button>
                                        </form>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="5" class="text-center text-muted py-4">
                        No projects found.
                        @if(request()->hasAny(['search', 'active']))
                            <a href="{{ route('projects.index') }}">Clear filters</a>
                        @endif
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

<div class="d-flex justify-content-center mt-4">
    {{ $projects->links() }}
</div>
@endsection
