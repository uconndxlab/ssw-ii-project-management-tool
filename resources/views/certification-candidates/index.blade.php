@extends('layouts.app')

@section('title', 'Candidates')

@section('content')

<x-page-header context="index" title="Candidates" description="{{ $candidates->total() }} total" />

<div class="card shadow-sm mb-3">
    <div class="card-body py-2">
        @include('certification-candidates.partials.filters')
    </div>
</div>

<div id="candidates-table" data-index-table>
    @include('certification-candidates.partials.table')
</div>

@endsection
