@extends('layouts.app')

@section('title', 'Teams')

@section('content')
<div class="row mb-4">
    <div class="col-12 d-flex justify-content-between align-items-center">
        <h1>Teams</h1>
        <a href="{{ route('teams.create') }}" class="btn btn-primary">Create Team</a>
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
        <form method="GET" action="{{ route('teams.index') }}" class="row g-3">
            <div class="col-md-4">
                <input type="text" 
                       name="search" 
                       class="form-control" 
                       placeholder="Search teams..." 
                       value="{{ request('search') }}">
            </div>
            
            <div class="col-md-3">
                <select name="active" class="form-select">
                    <option value="">All Teams</option>
                    <option value="1" {{ request('active') === '1' ? 'selected' : '' }}>Active Only</option>
                    <option value="0" {{ request('active') === '0' ? 'selected' : '' }}>Inactive Only</option>
                </select>
            </div>
            
            <div class="col-md-5 d-flex gap-2">
                <button type="submit" class="btn btn-secondary">Filter</button>
                @if(request()->hasAny(['search', 'active']))
                    <a href="{{ route('teams.index') }}" class="btn btn-outline-secondary">Clear</a>
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
                        <a href="{{ route('teams.index', array_merge(request()->query(), ['sort' => 'name', 'direction' => request('sort') === 'name' && request('direction') === 'asc' ? 'desc' : 'asc'])) }}"
                           class="text-decoration-none text-dark">
                            Name
                            @if(request('sort') === 'name')
                                <i class="bi bi-arrow-{{ request('direction') === 'desc' ? 'down' : 'up' }}"></i>
                            @endif
                        </a>
                    </th>
                    <th>
                        <a href="{{ route('teams.index', array_merge(request()->query(), ['sort' => 'members', 'direction' => request('sort') === 'members' && request('direction') === 'asc' ? 'desc' : 'asc'])) }}"
                           class="text-decoration-none text-dark">
                            Members
                            @if(request('sort') === 'members')
                                <i class="bi bi-arrow-{{ request('direction') === 'desc' ? 'down' : 'up' }}"></i>
                            @endif
                        </a>
                    </th>
                    <th>
                        <a href="{{ route('teams.index', array_merge(request()->query(), ['sort' => 'active', 'direction' => request('sort') === 'active' && request('direction') === 'asc' ? 'desc' : 'asc'])) }}"
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
                @forelse($teams as $team)
                <tr>
                    <td>
                        <a href="{{ route('teams.show', $team) }}" class="text-decoration-none">
                            {{ $team->name }}
                        </a>
                    </td>
                    <td>
                        <span class="badge bg-secondary">{{ $team->users_count }} {{ Str::plural('member', $team->users_count) }}</span>
                    </td>
                    <td>
                        @if($team->active)
                            <span class="badge bg-success">Active</span>
                        @else
                            <span class="badge bg-secondary">Inactive</span>
                        @endif
                    </td>
                    <td class="text-end">
                        <div class="btn-group btn-group-sm">
                            <a href="{{ route('teams.show', $team) }}" class="btn btn-outline-primary">View</a>
                            <a href="{{ route('teams.edit', $team) }}" class="btn btn-outline-secondary">Edit</a>
                            <button type="button" 
                                    class="btn btn-outline-danger"
                                    data-bs-toggle="modal" 
                                    data-bs-target="#deleteModal{{ $team->id }}">
                                Delete
                            </button>
                        </div>

                        <!-- Delete Confirmation Modal -->
                        <div class="modal fade" id="deleteModal{{ $team->id }}" tabindex="-1">
                            <div class="modal-dialog">
                                <div class="modal-content">
                                    <div class="modal-header">
                                        <h5 class="modal-title">Confirm Delete</h5>
                                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                    </div>
                                    <div class="modal-body">
                                        Are you sure you want to delete the team "{{ $team->name }}"?
                                    </div>
                                    <div class="modal-footer">
                                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                        <form method="POST" action="{{ route('teams.destroy', $team) }}" class="d-inline">
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
                    <td colspan="4" class="text-center text-muted py-4">
                        No teams found.
                        @if(request()->hasAny(['search', 'active']))
                            <a href="{{ route('teams.index') }}">Clear filters</a>
                        @endif
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

<div class="d-flex justify-content-center mt-4">
    {{ $teams->links() }}
</div>
@endsection
