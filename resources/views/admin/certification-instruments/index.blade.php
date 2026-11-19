@extends('layouts.app')

@section('title', 'Tools & Trainings')

@section('content')

<x-page-header context="index" title="Tools & Trainings" action-entity-label="Tool or Training" description="{{ $certificationInstruments->total() }} total" :action-url="auth()->user()->can('create', App\Models\CertificationInstrument::class) ? route('certification-instruments.create') : null" />

<div class="card shadow-sm mb-3">
    <div class="card-body py-2">
        <div id="certification-instrument-filters-container">
            @include('admin.certification-instruments.partials.filters', [
                'sort'            => $sort,
                'direction'       => $direction,
                'filterProjects'  => $filterProjects,
                'filterPrograms'  => $filterPrograms,
            ])
        </div>
    </div>
</div>

<div id="certification-instruments-table" data-index-table>
    @include('admin.certification-instruments.partials.table', [
        'certificationInstruments' => $certificationInstruments,
        'sort'               => $sort,
        'direction'          => $direction,
    ])
</div>

@endsection
