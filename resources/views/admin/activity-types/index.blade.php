@extends('layouts.app')

@section('title', 'Activity Types')

@section('content')

<x-page-header context="index" title="Activity Types" description="{{ $activityTypes->total() }} total" :action-url="route('activity-types.create')" />

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
