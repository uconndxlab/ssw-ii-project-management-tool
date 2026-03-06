@extends('layouts.app')

@section('title', 'Agreements')

@section('content')
<div class="row mb-4">
    <div class="col-12">
        <div class="d-flex justify-content-between align-items-center">
            <h1>Agreements</h1>
            @if(auth()->user()->isAdmin())
            <a href="{{ route('agreements.create') }}" class="btn btn-primary">Create Agreement</a>
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
                                <th>Agreement Name</th>
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
                            @forelse($agreements as $agreement)
                            <tr>
                                <td><a href="{{ route('agreements.show', $agreement) }}" class="text-decoration-none text-dark d-block"><strong>{{ $agreement->name }}</strong></a></td>
                                <td>{{ $agreement->organization->name }}</td>
                                <td>{{ $agreement->state->name }}</td>
                                <td>{{ $agreement->start_date?->format('M d, Y') ?? 'N/A' }}</td>
                                <td>{{ $agreement->users->count() }}</td>
                                @if(auth()->user()->isAdmin())
                                <td onclick="event.stopPropagation();">
                                    <div class="dropdown">
                                        <button class="btn btn-sm btn-link text-muted" type="button" data-bs-toggle="dropdown">
                                            ⋯
                                        </button>
                                        <ul class="dropdown-menu dropdown-menu-end">
                                            <li><a class="dropdown-item" href="{{ route('agreements.edit', $agreement) }}">Edit</a></li>
                                            <li><hr class="dropdown-divider"></li>
                                            <li>
                                                <form method="POST" action="{{ route('agreements.destroy', $agreement) }}" 
                                                      hx-confirm="Are you sure you want to delete this project?">
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
                                        No agreements found
                                    @else
                                        You are not assigned to any agreements
                                    @endif
                                </td>
                            </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                <div class="mt-3">
                    {{ $agreements->links() }}
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
