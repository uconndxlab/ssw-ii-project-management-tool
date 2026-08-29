@extends('layouts.app')

@section('title', ($superviseesIndex ?? false) ? 'Supervisees' : 'Users')

@section('content')

@php
    $superviseesIndex = $superviseesIndex ?? false;
    $usersIndexRoute = $superviseesIndex ? 'supervisees.index' : 'admin.users.index';
@endphp
<x-page-header
    context="index"
    :title="$superviseesIndex ? 'Supervisees' : 'Users'"
    :description="$users->total() . ' total'"
    :action-url="(! $superviseesIndex && auth()->user()->can('create', App\Models\User::class)) ? route('admin.users.create') : null"
/>

<div class="card shadow-sm mb-3">
    <div class="card-body py-2">
        @include('admin.users.partials.filters', [
            'sort' => $sort,
            'direction' => $direction,
            'filterProjects' => $filterProjects,
            'filterPrograms' => $filterPrograms,
            'superviseesIndex' => $superviseesIndex,
        ])
    </div>
</div>

<div id="users-table">
    @include('admin.users.partials.table', ['users' => $users, 'sort' => $sort, 'direction' => $direction, 'superviseesIndex' => $superviseesIndex])
</div>

@endsection
