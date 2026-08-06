@extends('layouts.app')

@section('title', 'States')

@section('content')

<x-page-header context="index" title="States" description="{{ $states->total() }} total" :action-url="route('states.create')" />

<div class="card shadow-sm mb-3">
    <div class="card-body py-2">
        @include('states.partials.filters', ['sort' => $sort, 'direction' => $direction])
    </div>
</div>

<div id="states-table">
    @include('states.partials.table', ['states' => $states, 'sort' => $sort, 'direction' => $direction])
</div>

@endsection
