@extends('layouts.app')

@section('title', $project->name)

@section('content')
<div class="row mb-4">
    <div class="col-12">
        <div class="d-flex justify-content-between align-items-center">
            <h1>{{ $project->name }}</h1>
            @if(auth()->user()->isAdmin())
            <a href="{{ route('projects.edit', $project) }}" class="btn btn-primary">Edit Project</a>
            @endif
        </div>
    </div>
</div>

<div class="row">
    <div class="col-md-6">
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="mb-0">Project Details</h5>
            </div>
            <div class="card-body">
                <dl class="row mb-0">
                    <dt class="col-sm-4">Organization:</dt>
                    <dd class="col-sm-8">{{ $project->organization->name }}</dd>

                    <dt class="col-sm-4">State:</dt>
                    <dd class="col-sm-8">{{ $project->state->name }}</dd>

                    <dt class="col-sm-4">Start Date:</dt>
                    <dd class="col-sm-8">{{ $project->start_date?->format('M d, Y') ?? 'Not set' }}</dd>

                    <dt class="col-sm-4">End Date:</dt>
                    <dd class="col-sm-8">{{ $project->end_date?->format('M d, Y') ?? 'Not set' }}</dd>

                    <dt class="col-sm-4">Created:</dt>
                    <dd class="col-sm-8">{{ $project->created_at->format('M d, Y') }}</dd>
                </dl>
            </div>
        </div>
    </div>

    <div class="col-md-6">
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="mb-0">Team Members ({{ $project->users->count() }})</h5>
            </div>
            <div class="card-body">
                @if($project->users->count() > 0)
                <div class="list-group list-group-flush">
                    @foreach($project->users as $user)
                    <div class="list-group-item px-0">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <strong>{{ $user->name }}</strong>
                                <br>
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
                <p class="text-muted mb-0">No team members assigned to this project.</p>
                @endif
            </div>
        </div>
    </div>
</div>

<div class="row">
    <div class="col-12">
        <a href="{{ route('projects.index') }}" class="btn btn-secondary">Back to Projects</a>
    </div>
</div>
@endsection
