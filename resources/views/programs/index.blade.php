@extends('layouts.app')

@section('title', 'Programs')

@section('content')

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h1 class="h2 mb-1">Programs</h1>
        <p class="text-muted small mb-0">{{ $programs->total() }} total</p>
    </div>
    <a href="{{ route('programs.create') }}" class="btn btn-primary">+ Create Program</a>
</div>

<div class="card shadow-sm mb-3">
    <div class="card-body py-2">
        @include('programs.partials.filters', ['sort' => $sort, 'direction' => $direction, 'projects' => $projects ?? collect()])
    </div>
</div>

<div id="programs-table">
    @include('programs.partials.table', ['programs' => $programs, 'sort' => $sort, 'direction' => $direction])
</div>

@endsection
