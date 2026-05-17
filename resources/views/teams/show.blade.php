@extends('layouts.app')

@section('title', $team->name)

@section('content')
<div class="row mb-4">
    <div class="col-12 d-flex justify-content-between align-items-center">
        <h1>{{ $team->name }}</h1>
        <div class="btn-group">
            <a href="{{ route('teams.edit', $team) }}" class="btn btn-primary">Edit Team</a>
            <a href="{{ route('teams.index') }}" class="btn btn-secondary">Back to Teams</a>
        </div>
    </div>
</div>

<div class="row">
    <!-- Team Details -->
    <div class="col-md-4">
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="mb-0">Team Details</h5>
            </div>
            <div class="card-body">
                <dl class="row mb-0">
                    <dt class="col-sm-4">Status:</dt>
                    <dd class="col-sm-8">
                        @if($team->active)
                            <span class="badge bg-success">Active</span>
                        @else
                            <span class="badge bg-secondary">Inactive</span>
                        @endif
                    </dd>

                    <dt class="col-sm-4">Members:</dt>
                    <dd class="col-sm-8">{{ $team->users->count() }}</dd>

                    <dt class="col-sm-4">Agreements:</dt>
                    <dd class="col-sm-8">{{ $team->agreements->count() }}</dd>

                    <dt class="col-sm-4">Created:</dt>
                    <dd class="col-sm-8">{{ $team->created_at->format('M d, Y') }}</dd>

                    <dt class="col-sm-4">Updated:</dt>
                    <dd class="col-sm-8">{{ $team->updated_at->format('M d, Y') }}</dd>
                </dl>
            </div>
        </div>
    </div>

    <!-- Team Members -->
    <div class="col-md-8">
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="mb-0">Team Members ({{ $team->users->count() }})</h5>
            </div>
            <div class="card-body">
                @if($team->users->isNotEmpty())
                    <div class="list-group list-group-flush">
                        @foreach($team->users as $user)
                        <div class="list-group-item px-0 py-2">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <strong class="d-block">{{ $user->name }}</strong>
                                    <small class="text-muted">{{ $user->email }}</small>
                                </div>
                                <span class="badge 
                                    @if($user->role === 'admin') bg-danger
                                    @elseif($user->role === 'consultant') bg-info
                                    @else bg-secondary
                                    @endif">
                                    {{ ucfirst($user->role) }}
                                </span>
                            </div>
                        </div>
                        @endforeach
                    </div>
                @else
                    <p class="text-muted mb-0">No members in this team.</p>
                @endif
            </div>
        </div>

        <!-- Assigned Agreements -->
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">Assigned Agreements ({{ $team->agreements->count() }})</h5>
            </div>
            <div class="card-body">
                @if($team->agreements->isNotEmpty())
                    <div class="list-group list-group-flush">
                        @foreach($team->agreements as $agreement)
                        <div class="list-group-item px-0 py-2">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <a href="{{ route('agreements.show', $agreement) }}" class="text-decoration-none">
                                        <strong class="d-block">{{ $agreement->name }}</strong>
                                    </a>
                                    <small class="text-muted">{{ $agreement->organization->name ?? 'N/A' }}</small>
                                </div>
                                @if($agreement->start_date && $agreement->end_date)
                                    <small class="text-muted">
                                        {{ $agreement->start_date->format('M Y') }} - {{ $agreement->end_date->format('M Y') }}
                                    </small>
                                @endif
                            </div>
                        </div>
                        @endforeach
                    </div>
                @else
                    <p class="text-muted mb-0">This team is not assigned to any agreements.</p>
                @endif
            </div>
        </div>
    </div>
</div>
@endsection
