@extends('layouts.app')

@section('title', 'Programs')

@section('content')

<x-page-header context="index" title="Programs" description="{{ $programs->total() }} total" :action-url="auth()->user()->can('create', App\Models\Program::class) ? route('programs.create') : null" />

<div class="card shadow-sm mb-3">
    <div class="card-body py-2">
        @include('programs.partials.filters', ['sort' => $sort, 'direction' => $direction, 'projects' => $projects ?? collect()])
    </div>
</div>

<div id="programs-table">
    @include('programs.partials.table', ['programs' => $programs, 'sort' => $sort, 'direction' => $direction])
</div>

@endsection
