@extends('layouts.app')

@section('title', 'Certificates')

@section('content')

<x-page-header context="index" title="Certificates" description="{{ $certificates->total() }} total" :action-url="auth()->user()->can('create', App\Models\Certificate::class) ? route('certificates.create') : null" />

<div class="card shadow-sm mb-3">
    <div class="card-body py-2">
        <div id="certificate-filters-container">
            @include('admin.certificates.partials.filters', [
                'sort'            => $sort,
                'direction'       => $direction,
                'filterProjects'  => $filterProjects,
                'filterPrograms'  => $filterPrograms,
            ])
        </div>
    </div>
</div>

<div id="certificates-table">
    @include('admin.certificates.partials.table', [
        'certificates' => $certificates,
        'sort'         => $sort,
        'direction'    => $direction,
    ])
</div>

@endsection
