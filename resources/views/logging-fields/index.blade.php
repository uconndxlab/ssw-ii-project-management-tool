@extends('layouts.app')

@section('title', 'Logging Fields')

@section('content')

<x-page-header context="index" title="Logging Fields" description="{{ $loggingFields->total() }} total" :action-url="route('logging-fields.create')" />

<div class="card shadow-sm mb-3">
    <div class="card-body py-2">
        @include('logging-fields.partials.filters', [
            'sort' => $sort,
            'direction' => $direction,
            'filterProjects' => $filterProjects,
            'filterPrograms' => $filterPrograms,
            'filterContactFamilies' => $filterContactFamilies,
        ])
    </div>
</div>

<div id="logging-fields-table">
    @include('logging-fields.partials.table', [
        'loggingFields' => $loggingFields,
        'sort' => $sort,
        'direction' => $direction,
    ])
</div>

@endsection
