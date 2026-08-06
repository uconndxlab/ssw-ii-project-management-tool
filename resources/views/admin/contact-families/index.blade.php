@extends('layouts.app')

@section('title', 'Contact Families')

@section('content')

<x-page-header context="index" title="Contact Families" description="{{ $contactFamilies->count() }} total" :action-url="route('contact-families.create')" />

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
