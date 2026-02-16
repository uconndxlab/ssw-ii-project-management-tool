@extends('layouts.app')

@section('title', 'Engagements')

@section('content')
<div class="row mb-4">
    <div class="col-12">
        <div class="d-flex justify-content-between align-items-center">
            <h1>Engagements</h1>
            <a href="{{ route('engagements.create') }}" class="btn btn-primary">Log Engagement</a>
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
                                <th>Date</th>
                                <th>Project</th>
                                <th>Type</th>
                                <th>Hours</th>
                                <th>Logged By</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($engagements as $engagement)
                            <tr>
                                <td>{{ $engagement->engagement_date->format('M d, Y') }}</td>
                                <td>{{ $engagement->project->name }}</td>
                                <td>
                                    <span class="badge bg-info">
                                        {{ str_replace('_', ' ', ucwords($engagement->engagement_type, '_')) }}
                                    </span>
                                </td>
                                <td>{{ number_format($engagement->hours, 2) }}</td>
                                <td>{{ $engagement->user->name }}</td>
                                <td>
                                    @if(auth()->user()->isAdmin() || $engagement->user_id === auth()->id())
                                    <form method="POST" action="{{ route('engagements.destroy', $engagement) }}" class="d-inline" 
                                          onsubmit="return confirm('Are you sure you want to delete this engagement?');">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="btn btn-sm btn-outline-danger">Delete</button>
                                    </form>
                                    @endif
                                </td>
                            </tr>
                            @empty
                            <tr>
                                <td colspan="6" class="text-center text-muted">
                                    @if(auth()->user()->isAdmin())
                                        No engagements logged yet
                                    @else
                                        No engagements found for your assigned projects
                                    @endif
                                </td>
                            </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                <div class="mt-3">
                    {{ $engagements->links() }}
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
