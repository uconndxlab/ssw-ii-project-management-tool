@extends('layouts.app')

@section('title', 'Users')

@section('content')

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h1 class="h2 mb-1">Users</h1>
        <p class="text-muted small mb-0">{{ $users->total() }} total</p>
    </div>
    <a href="{{ route('admin.users.create') }}" class="btn btn-primary">+ Create User</a>
</div>

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
