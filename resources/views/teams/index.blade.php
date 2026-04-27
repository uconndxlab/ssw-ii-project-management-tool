@extends('layouts.app')

@section('title', 'Teams')

@section('content')

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h1 class="h2 mb-1">Teams</h1>
        <p class="text-muted small mb-0">{{ $teams->total() }} total</p>
    </div>
    <a href="{{ route('teams.create') }}" class="btn btn-primary">+ Create Team</a>
</div>

<div class="card shadow-sm mb-3">
    <div class="card-body py-2">
        @include('teams.partials.filters', ['sort' => $sort, 'direction' => $direction])
    </div>
</div>

<div id="teams-table">
    @include('teams.partials.table', ['teams' => $teams, 'sort' => $sort, 'direction' => $direction])
</div>

@endsection
