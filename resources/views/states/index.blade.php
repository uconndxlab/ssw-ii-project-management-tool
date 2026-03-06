@extends('layouts.app')

@section('title', 'States')

@section('content')
<div class="row mb-4">
    <div class="col-12">
        <div class="d-flex justify-content-between align-items-center">
            <h1>States</h1>
            <a href="{{ route('states.create') }}" class="btn btn-primary">Create State</a>
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
                                <th>Organizations</th>
                                <th>Agreements</th>
                                <th>Created</th>
                                <th style="width: 50px;"></th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($states as $state)
                            <tr>
                                <td><strong>{{ $state->name }}</strong></td>
                                <td>{{ $state->organizations_count }}</td>
                                <td>{{ $state->agreements_count }}</td>
                                <td>{{ $state->created_at->format('M d, Y') }}</td>
                                <td onclick="event.stopPropagation()">
                                    <div class="dropdown">
                                        <button class="btn btn-sm btn-link text-secondary" type="button" data-bs-toggle="dropdown">
                                            ⋯
                                        </button>
                                        <ul class="dropdown-menu dropdown-menu-end">
                                            <li>
                                                <a class="dropdown-item" href="{{ route('states.edit', $state) }}">Edit</a>
                                            </li>
                                            <li>
                                                <form method="POST" action="{{ route('states.destroy', $state) }}" 
                                                      hx-confirm="Are you sure you want to delete this state?">
                                                    @csrf
                                                    @method('DELETE')
                                                    <button type="submit" class="dropdown-item text-danger">Delete</button>
                                                </form>
                                            </li>
                                        </ul>
                                    </div>
                                </td>
                            </tr>
                            @empty
                            <tr>
                                <td colspan="5" class="text-center text-muted">No states found</td>
                            </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                <div class="mt-3">
                    {{ $states->links() }}
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
