@extends('layouts.app')

@section('title', 'Teams')

@section('content')

<x-page-header context="index" title="Teams" description="{{ $teams->total() }} total" :action-url="auth()->user()->can('create', App\Models\Team::class) ? route('teams.create') : null" />

<div class="card shadow-sm mb-3">
    <div class="card-body py-2">
        @include('teams.partials.filters', [
            'sort' => $sort,
            'direction' => $direction,
            'filterProjects' => $filterProjects,
            'filterPrograms' => $filterPrograms,
        ])
    </div>
</div>

<div id="teams-table">
    @include('teams.partials.table', ['teams' => $teams, 'sort' => $sort, 'direction' => $direction])
</div>

@endsection
