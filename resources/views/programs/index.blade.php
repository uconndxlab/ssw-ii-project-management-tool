@extends('layouts.app')

@section('title', 'Programs')

@section('content')
<div class="row mb-4">
    <div class="col-12">
        <div class="d-flex justify-content-between align-items-center">
            <h1>Programs</h1>
            <a href="{{ route('programs.create') }}" class="btn btn-primary">Create Program</a>
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
                                <th>Status</th>
                                <th>Created</th>
                                <th style="width: 50px;"></th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($programs as $program)
                            <tr>
                                <td><strong>{{ $program->name }}</strong></td>
                                <td>
                                    @if($program->active)
                                        <span class="badge bg-success">Active</span>
                                    @else
                                        <span class="badge bg-secondary">Inactive</span>
                                    @endif
                                </td>
                                <td>{{ $program->created_at->format('M d, Y') }}</td>
                                <td onclick="event.stopPropagation()">
                                    <div class="dropdown">
                                        <button class="btn btn-sm btn-link text-secondary" type="button" data-bs-toggle="dropdown">
                                            ⋯
                                        </button>
                                        <ul class="dropdown-menu dropdown-menu-end">
                                            <li>
                                                <a class="dropdown-item" href="{{ route('programs.edit', $program) }}">Edit</a>
                                            </li>
                                            <li>
                                                <form method="POST" action="{{ route('programs.destroy', $program) }}" 
                                                      hx-confirm="Are you sure you want to delete this program?">
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
                                <td colspan="4" class="text-center text-muted">No programs found</td>
                            </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                <div class="mt-3">
                    {{ $programs->links() }}
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
