@extends('layouts.app')

@section('title', 'Sub-activities')

@section('content')

<x-page-header context="index" title="Sub-activities" description="{{ $subActivities->total() }} total" :action-url="auth()->user()->can('create', App\Models\SubActivity::class) ? route('sub-activities.create') : null" />

<div class="card shadow-sm mb-3">
    <div class="card-body py-2">
        <div id="sub-activity-filters-container">
            @include('admin.sub-activities.partials.filters', [
                'activityTypes' => $activityTypes,
                'sort'            => $sort,
                'direction'       => $direction,
                'filterProjects'  => $filterProjects,
                'filterPrograms'  => $filterPrograms,
            ])
        </div>
    </div>
</div>

<div id="sub-activities-table" data-index-table>
    @include('admin.sub-activities.partials.table', [
        'subActivities' => $subActivities,
        'sort'          => $sort,
        'direction'     => $direction,
    ])
</div>

@endsection
