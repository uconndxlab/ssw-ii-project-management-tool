@extends('layouts.app')

@section('title', 'Projects')

@section('content')
<div class="row mb-4">
    <div class="col-12">
        <div class="d-flex justify-content-between align-items-center">
            <h1>Projects</h1>
            @if(auth()->user()->isAdmin())
            <a href="{{ route('projects.create') }}" class="btn btn-primary">Create Project</a>
            @endif
        </div>
    </div>
</div>

<div class="row">
    <div class="col-12">
        <div class="card">
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Name</th>
                                <th>Organization</th>
                                <th>State</th>
                                <th>Start Date</th>
                                <th>Team Members</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($projects as $project)
                            <tr>
                                <td>{{ $project->id }}</td>
                                <td>{{ $project->name }}</td>
                                <td>{{ $project->organization->name }}</td>
                                <td>{{ $project->state->name }}</td>
                                <td>{{ $project->start_date?->format('M d, Y') ?? 'N/A' }}</td>
                                <td>{{ $project->users->count() }}</td>
                                <td>
                                    <div class="btn-group btn-group-sm">
                                        <a href="{{ route('projects.show', $project) }}" class="btn btn-outline-secondary">View</a>
                                        @if(auth()->user()->isAdmin())
                                        <a href="{{ route('projects.edit', $project) }}" class="btn btn-outline-primary">Edit</a>
                                        <form method="POST" action="{{ route('projects.destroy', $project) }}" class="d-inline" 
                                              onsubmit="return confirm('Are you sure you want to delete this project?');">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="btn btn-outline-danger">Delete</button>
                                        </form>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                            @empty
                            <tr>
                                <td colspan="7" class="text-center text-muted">
                                    @if(auth()->user()->isAdmin())
                                        No projects found
                                    @else
                                        You are not assigned to any projects
                                    @endif
                                </td>
                            </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                <div class="mt-3">
                    {{ $projects->links() }}
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
