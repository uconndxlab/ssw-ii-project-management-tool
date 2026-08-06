@extends('layouts.app')

@section('title', 'Users')

@section('content')

<x-page-header context="index" title="Users" description="{{ $users->total() }} total" :action-url="route('admin.users.create')" />

<div class="card shadow-sm mb-3">
    <div class="card-body py-2">
        @include('admin.users.partials.filters', [
            'sort' => $sort,
            'direction' => $direction,
            'filterProjects' => $filterProjects,
            'filterPrograms' => $filterPrograms,
        ])
    </div>
</div>

<div id="users-table">
    @include('admin.users.partials.table', ['users' => $users, 'sort' => $sort, 'direction' => $direction])
</div>

@endsection
