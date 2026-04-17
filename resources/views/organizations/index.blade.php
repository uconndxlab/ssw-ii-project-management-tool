@extends('layouts.app')

@section('title', 'Organizations')

@section('content')
<div class="row mb-4">
    <div class="col-12">
        <div class="d-flex justify-content-between align-items-center">
            <h1>Organizations</h1>
            <a href="{{ route('organizations.create') }}" class="btn btn-primary">Create Organization</a>
        </div>
    </div>
</div>

<div class="row mb-3">
    <div class="col-12">
        <div class="card">
            <div class="card-body">
                <div id="organization-filters-container">
                    @include('organizations.partials.filters', [
                        'states' => $states,
                        'sort' => $sort,
                        'direction' => $direction,
                    ])
                </div>
            </div>
        </div>
    </div>
</div>

<div id="organizations-table">
    @include('organizations.partials.table', [
        'organizations' => $organizations,
        'sort' => $sort,
        'direction' => $direction,
    ])
</div>
@endsection