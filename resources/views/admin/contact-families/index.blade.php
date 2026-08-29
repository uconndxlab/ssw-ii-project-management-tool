@extends('layouts.app')

@section('title', 'Activity Families')

@section('content')

<x-page-header context="index" title="Activity Families" description="{{ $contactFamilies->count() }} total" :action-url="auth()->user()->can('create', App\Models\ContactFamily::class) ? route('contact-families.create') : null" />

<div class="card shadow-sm mb-3">
    <div class="card-body py-2">
        @include('admin.contact-families.partials.filters', [
            'sort' => $sort,
            'direction' => $direction,
            'filterProjects' => $filterProjects,
            'filterPrograms' => $filterPrograms,
        ])
    </div>
</div>

<div id="cf-table">
    @include('admin.contact-families.partials.table', [
        'contactFamilies' => $contactFamilies,
        'sort' => $sort,
        'direction' => $direction,
    ])
</div>

@endsection
