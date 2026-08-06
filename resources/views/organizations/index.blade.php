@extends('layouts.app')

@section('title', 'Organizations')

@section('content')

<x-page-header context="index" title="Organizations" description="{{ $organizations->total() }} total" :action-url="auth()->user()->isAdmin() ? route('organizations.create') : null" />

<div class="card shadow-sm mb-3">
    <div class="card-body py-2">
        @include('organizations.partials.filters', [
            'states' => $states,
            'filterProjects' => $filterProjects,
            'filterPrograms' => $filterPrograms,
            'sort' => $sort,
            'direction' => $direction,
        ])
    </div>
</div>

<div id="organizations-table">
    @include('organizations.partials.table', ['organizations' => $organizations])
</div>

@endsection
