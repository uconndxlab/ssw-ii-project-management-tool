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
                                <th>Project Name</th>
                                <th>Organization</th>
                                <th>State</th>
                                <th>Start Date</th>
                                <th>Team Members</th>
                                @if(auth()->user()->isAdmin())
                                <th style="width: 50px;"></th>
                                @endif
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($projects as $project)
                            <tr style="cursor: pointer;" onclick="window.location='{{ route('projects.show', $project) }}'">
                                <td><strong>{{ $project->name }}</strong></td>
                                <td>{{ $project->organization->name }}</td>
                                <td>{{ $project->state->name }}</td>
                                <td>{{ $project->start_date?->format('M d, Y') ?? 'N/A' }}</td>
                                <td>{{ $project->users->count() }}</td>
                                @if(auth()->user()->isAdmin())
                                <td onclick="event.stopPropagation();">
                                    <div class="dropdown">
                                        <button class="btn btn-sm btn-link text-muted" type="button" data-bs-toggle="dropdown">
                                            ⋯
                                        </button>
                                        <ul class="dropdown-menu dropdown-menu-end">
                                            <li><a class="dropdown-item" href="{{ route('projects.edit', $project) }}">Edit</a></li>
                                            <li><hr class="dropdown-divider"></li>
                                            <li>
                                                <form method="POST" action="{{ route('projects.destroy', $project) }}" 
                                                      onsubmit="return confirm('Are you sure?');">
                                                    @csrf
                                                    @method('DELETE')
                                                    <button type="submit" class="dropdown-item text-danger">Delete</button>
                                                </form>
                                            </li>
                                        </ul>
                                    </div>
                                </td>
                                @endif
                            </tr>
                            @empty
                            <tr>
                                <td colspan="{{ auth()->user()->isAdmin() ? '6' : '5' }}" class="text-center text-muted">
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
