@extends('layouts.app')

@section('title', 'States')

@section('content')
<div class="row mb-4">
    <div class="col-12">
        <div class="d-flex justify-content-between align-items-center">
            <h1>States</h1>
            <a href="{{ route('states.create') }}" class="btn btn-primary">Create State</a>
        </div>
    </div>
</div>

<div class="row mb-3">
    <div class="col-12">
        <div class="card">
            <div class="card-body">
                @include('states.partials.filters', [
                    'sort' => $sort,
                    'direction' => $direction,
                ])
            </div>
        </div>
    </div>
</div>

<div id="states-table">
    @include('states.partials.table', [
        'states' => $states,
        'sort' => $sort,
        'direction' => $direction,
    ])
</div>
@endsection