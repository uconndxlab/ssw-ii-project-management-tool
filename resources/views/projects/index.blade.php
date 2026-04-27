@extends('layouts.app')

@section('title', 'Projects')

@section('content')

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h1 class="h2 mb-1">Projects</h1>
        <p class="text-muted small mb-0">{{ $projects->total() }} total</p>
    </div>
    <a href="{{ route('projects.create') }}" class="btn btn-primary">+ Create Project</a>
</div>

<div class="card shadow-sm mb-3">
    <div class="card-body py-2">
        @include('projects.partials.filters', ['sort' => $sort, 'direction' => $direction])
    </div>
</div>

<div id="projects-table">
    @include('projects.partials.table', ['projects' => $projects, 'sort' => $sort, 'direction' => $direction])
</div>

@endsection
