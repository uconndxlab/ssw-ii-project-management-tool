@extends('layouts.app')

@section('title', 'States')

@section('content')

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h1 class="h2 mb-1">States</h1>
        <p class="text-muted small mb-0">{{ $states->total() }} total</p>
    </div>
    <a href="{{ route('states.create') }}" class="btn btn-primary">+ Create State</a>
</div>

<div class="card shadow-sm mb-3">
    <div class="card-body py-2">
        @include('states.partials.filters', ['sort' => $sort, 'direction' => $direction])
    </div>
</div>

<div id="states-table">
    @include('states.partials.table', ['states' => $states, 'sort' => $sort, 'direction' => $direction])
</div>

@endsection
