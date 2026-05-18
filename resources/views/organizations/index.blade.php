@extends('layouts.app')

@section('title', 'Organizations')

@section('content')

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h1 class="h2 mb-1">Organizations</h1>
        <p class="text-muted small mb-0">{{ $organizations->total() }} total</p>
    </div>
    @if(auth()->user()->isAdmin())
        <a href="{{ route('organizations.create') }}" class="btn btn-primary">+ Create Organization</a>
    @endif
</div>

<div class="card shadow-sm mb-3">
    <div class="card-body py-2">
        @include('organizations.partials.filters', ['states' => $states])
    </div>
</div>

<div id="organizations-table">
    @include('organizations.partials.table', ['organizations' => $organizations])
</div>

@endsection
