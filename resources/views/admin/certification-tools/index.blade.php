@extends('layouts.app')

@section('title', 'Certification Tools')

@section('content')

<x-page-header context="index" title="Certification Tools" description="{{ $certificationTools->total() }} total" :action-url="auth()->user()->can('create', App\Models\CertificationTool::class) ? route('certification-tools.create') : null" />

<div class="card shadow-sm mb-3">
    <div class="card-body py-2">
        <div id="certification-tool-filters-container">
            @include('admin.certification-tools.partials.filters', [
                'sort'            => $sort,
                'direction'       => $direction,
                'filterProjects'  => $filterProjects,
                'filterPrograms'  => $filterPrograms,
            ])
        </div>
    </div>
</div>

<div id="certification-tools-table">
    @include('admin.certification-tools.partials.table', [
        'certificationTools' => $certificationTools,
        'sort'               => $sort,
        'direction'          => $direction,
    ])
</div>

@endsection
