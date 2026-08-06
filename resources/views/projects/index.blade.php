@extends('layouts.app')

@section('title', 'Projects')

@section('content')

<x-page-header context="index" title="Projects" description="{{ $projects->total() }} total" :action-url="route('projects.create')" />

<div class="card shadow-sm mb-3">
    <div class="card-body py-2">
        @include('projects.partials.filters', ['sort' => $sort, 'direction' => $direction])
    </div>
</div>

<div id="projects-table">
    @include('projects.partials.table', ['projects' => $projects, 'sort' => $sort, 'direction' => $direction])
</div>

@endsection
