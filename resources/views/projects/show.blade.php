@extends('layouts.app')

@section('title', $project->name)

@section('content')
<div class="row mb-4">
    <div class="col-12 d-flex justify-content-between align-items-center">
        <h1>{{ $project->name }}</h1>
        <div class="btn-group">
            <a href="{{ route('projects.edit', $project) }}" class="btn btn-primary">Edit Project</a>
            <a href="{{ route('projects.index') }}" class="btn btn-secondary">Back to Projects</a>
        </div>
    </div>
</div>

<div class="row">
    <!-- Project Details -->
    <div class="col-md-4">
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="mb-0">Project Details</h5>
            </div>
            <div class="card-body">
                <dl class="row mb-0">
                    <dt class="col-sm-5">Status:</dt>
                    <dd class="col-sm-7">
                        @if($project->active)
                            <span class="badge bg-success">Active</span>
                        @else
                            <span class="badge bg-secondary">Inactive</span>
                        @endif
                    </dd>

                    <dt class="col-sm-5">Programs:</dt>
                    <dd class="col-sm-7">{{ $project->programs->count() }}</dd>

                    <dt class="col-sm-5">Created:</dt>
                    <dd class="col-sm-7">{{ $project->created_at->format('M d, Y') }}</dd>

                    <dt class="col-sm-5">Updated:</dt>
                    <dd class="col-sm-7">{{ $project->updated_at->format('M d, Y') }}</dd>
                </dl>

                @if($project->description)
                    <hr>
                    <h6>Description</h6>
                    <p class="mb-0">{{ $project->description }}</p>
                @endif
            </div>
        </div>
    </div>

    <!-- Programs -->
    <div class="col-md-8">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0">Programs ({{ $project->programs->count() }})</h5>
                <a href="{{ route('programs.create') }}" class="btn btn-sm btn-primary">Add Program</a>
            </div>
            <div class="card-body">
                @if($project->programs->isNotEmpty())
                    <div class="list-group list-group-flush">
                        @foreach($project->programs as $program)
                        <div class="list-group-item px-0 py-2">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <a href="{{ route('programs.edit', $program) }}" class="text-decoration-none">
                                        <strong class="d-block">{{ $program->name }}</strong>
                                    </a>
                                </div>
                                <div class="d-flex gap-2 align-items-center">
                                    @if($program->active)
                                        <span class="badge bg-success">Active</span>
                                    @else
                                        <span class="badge bg-secondary">Inactive</span>
                                    @endif
                                    <a href="{{ route('programs.edit', $program) }}" class="btn btn-sm btn-outline-secondary">Edit</a>
                                </div>
                            </div>
                        </div>
                        @endforeach
                    </div>
                @else
                    <p class="text-muted mb-0">No programs in this project yet.</p>
                @endif
            </div>
        </div>
    </div>
</div>
@endsection
