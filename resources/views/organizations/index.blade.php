@extends('layouts.app')

@section('title', 'Organizations')

@section('content')
<div class="row mb-4">
    <div class="col-12">
        <div class="d-flex justify-content-between align-items-center">
            <h1>Organizations</h1>
            <a href="{{ route('organizations.create') }}" class="btn btn-primary">Create Organization</a>
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
                                <th>State</th>
                                <th>Created</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($organizations as $organization)
                            <tr>
                                <td>{{ $organization->id }}</td>
                                <td>{{ $organization->name }}</td>
                                <td>{{ $organization->state->name }}</td>
                                <td>{{ $organization->created_at->format('M d, Y') }}</td>
                                <td>
                                    <div class="btn-group btn-group-sm">
                                        <a href="{{ route('organizations.edit', $organization) }}" class="btn btn-outline-primary">Edit</a>
                                        <form method="POST" action="{{ route('organizations.destroy', $organization) }}" class="d-inline" 
                                              onsubmit="return confirm('Are you sure you want to delete this organization?');">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="btn btn-outline-danger">Delete</button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                            @empty
                            <tr>
                                <td colspan="5" class="text-center text-muted">No organizations found</td>
                            </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                <div class="mt-3">
                    {{ $organizations->links() }}
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
