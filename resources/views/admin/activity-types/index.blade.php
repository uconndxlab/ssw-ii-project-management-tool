@extends('layouts.app')

@section('title', 'Activity Types')

@section('content')

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h1 class="h2 mb-1">Activity Types</h1>
        <p class="text-muted small mb-0">{{ $activityTypes->total() }} total</p>
    </div>
    <a href="{{ route('activity-types.create') }}" class="btn btn-primary">+ Add Activity Type</a>
</div>

<div class="card shadow-sm mb-3">
    <div class="card-body py-2">
        <div id="activity-type-filters-container">
            @include('admin.activity-types.partials.filters', [
                'contactFamilies' => $contactFamilies,
                'sort'            => $sort,
                'direction'       => $direction,
                'filterProjects'  => $filterProjects,
                'filterPrograms'  => $filterPrograms,
            ])
        </div>
    </div>
</div>

<div id="activity-types-table">
    @include('admin.activity-types.partials.table', [
        'activityTypes' => $activityTypes,
        'sort'          => $sort,
        'direction'     => $direction,
    ])
</div>

@endsection
