@extends('layouts.app')

@section('title', 'Contact Families')

@section('content')

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h1 class="h2 mb-1">Contact Families</h1>
        <p class="text-muted small mb-0">{{ $contactFamilies->count() }} total</p>
    </div>
    <a href="{{ route('contact-families.create') }}" class="btn btn-primary">+ Add Contact Family</a>
</div>

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
