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
                                <th>Name</th>
                                <th>State</th>
                                <th>Agreements</th>
                                <th>Created</th>
                                @if(auth()->user()->isAdmin())
                                <th style="width: 50px;"></th>
                                @endif
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($organizations as $organization)
                            <tr>
                                <td><a href="{{ route('organizations.show', $organization) }}" class="text-decoration-none text-dark d-block"><strong>{{ $organization->name }}</strong></a></td>
                                <td>{{ $organization->state->name }}</td>
                                <td>{{ $organization->agreements->count() }}</td>
                                <td>{{ $organization->created_at->format('M d, Y') }}</td>
                                @if(auth()->user()->isAdmin())
                                <td onclick="event.stopPropagation()">
                                    <div class="dropdown">
                                        <button class="btn btn-sm btn-link text-secondary" type="button" data-bs-toggle="dropdown">
                                            ⋯
                                        </button>
                                        <ul class="dropdown-menu dropdown-menu-end">
                                            <li>
                                                <a class="dropdown-item" href="{{ route('organizations.edit', $organization) }}">Edit</a>
                                            </li>
                                            <li>
                                                <form method="POST" action="{{ route('organizations.destroy', $organization) }}" 
                                                      hx-confirm="Are you sure you want to delete this organization?">
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
                                <td colspan="{{ auth()->user()->isAdmin() ? 5 : 4 }}" class="text-center text-muted">No organizations found</td>
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
