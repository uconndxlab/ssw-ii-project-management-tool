@extends('layouts.app')

@section('title', 'Agreements')

@section('content')

<x-page-header context="index" title="Agreements" description="{{ $agreements->total() }} total" :action-url="auth()->user()->can('create', App\Models\Agreement::class) ? route('agreements.create') : null" />

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
