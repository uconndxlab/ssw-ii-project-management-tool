@extends('layouts.app')

@section('title', 'Agreements')

@section('content')
<div class="row mb-4">
    <div class="col-12">
        <div class="d-flex justify-content-between align-items-center">
            <h1>Agreements</h1>
            @if(auth()->user()->isAdmin())
                <a href="{{ route('agreements.create') }}" class="btn btn-primary">Create Agreement</a>
            @endif
        </div>
    </div>
</div>

<div class="row mb-3">
    <div class="col-12">
        <div class="card">
            <div class="card-body">
                <div id="agreement-filters-container">
                    @include('agreements.partials.filters', [
                        'organizations' => $organizations,
                        'states' => $states,
                        'sort' => $sort,
                        'direction' => $direction,
                    ])
                </div>
            </div>
        </div>
    </div>
</div>

<div id="agreements-table">
    @include('agreements.partials.table', [
        'agreements' => $agreements,
        'sort' => $sort,
        'direction' => $direction,
    ])
</div>
@endsection