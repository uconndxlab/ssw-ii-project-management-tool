@extends('layouts.app')

@section('title', 'Agreements')

@section('content')

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h1 class="h2 mb-1">Agreements</h1>
        <p class="text-muted small mb-0">{{ $agreements->total() }} total</p>
    </div>
    @if(auth()->user()->isAdmin())
        <a href="{{ route('agreements.create') }}" class="btn btn-primary">+ Create Agreement</a>
    @endif
</div>

<div class="card shadow-sm mb-3">
    <div class="card-body py-2">
        <div id="agreement-filters-container">
            @include('agreements.partials.filters', [
                'states'          => $states,
                'filterProjects'  => $filterProjects,
                'filterPrograms'  => $filterPrograms,
                'sort'            => $sort,
                'direction'       => $direction,
            ])
        </div>
    </div>
</div>

<div id="agreements-table">
    @include('agreements.partials.table', [
        'agreements' => $agreements,
        'sort'       => $sort,
        'direction'  => $direction,
    ])
</div>

@endsection
