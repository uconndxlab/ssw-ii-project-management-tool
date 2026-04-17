@extends('layouts.app')

@section('title', 'Activity Types')

@section('content')
<div class="row mb-4">
    <div class="col-12">
        <div class="d-flex justify-content-between align-items-center">
            <h1>Activity Types</h1>
            <a href="{{ route('activity-types.create') }}" class="btn btn-primary">Add Activity Type</a>
        </div>
    </div>
</div>

@if(session('success'))
    <div class="alert alert-success alert-dismissible fade show" role="alert">
        {{ session('success') }}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
@endif

@if(session('error'))
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
        {{ session('error') }}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
@endif

<div class="row mb-3">
    <div class="col-12">
        <div class="card">
            <div class="card-body">
                <div id="activity-type-filters-container">
                    @include('admin.activity-types.partials.filters', [
                        'contactFamilies' => $contactFamilies,
                        'sort' => $sort,
                        'direction' => $direction,
                    ])
                </div>
            </div>
        </div>
    </div>
</div>

<div id="activity-types-table">
    @include('admin.activity-types.partials.table', [
        'activityTypes' => $activityTypes,
        'sort' => $sort,
        'direction' => $direction,
    ])
</div>
@endsection