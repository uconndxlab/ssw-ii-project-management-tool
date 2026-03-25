@extends('layouts.app')

@section('title', 'Programs')

@section('content')
<div class="row mb-4">
    <div class="col-12">
        <div class="d-flex justify-content-between align-items-center">
            <h1>Programs</h1>
            <a href="{{ route('programs.create') }}" class="btn btn-primary">Create Program</a>
        </div>
    </div>
</div>

<div class="row mb-3">
    <div class="col-12">
        <div class="card">
            <div class="card-body">
                @include('programs.partials.filters', [
                    'sort' => $sort,
                    'direction' => $direction,
                ])
            </div>
        </div>
    </div>
</div>

<div id="programs-table">
    @include('programs.partials.table', [
        'programs' => $programs,
        'sort' => $sort,
        'direction' => $direction,
    ])
</div>
@endsection