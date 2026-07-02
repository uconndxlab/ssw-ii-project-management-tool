@extends('layouts.app')

@section('title', 'Logging Fields')

@section('content')

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h1 class="h2 mb-1">Logging Fields</h1>
        <p class="text-muted small mb-0">{{ $loggingFields->total() }} total</p>
    </div>
    <a href="{{ route('logging-fields.create') }}" class="btn btn-primary">+ Create Logging Field</a>
</div>

<div class="card shadow-sm mb-3">
    <div class="card-body py-2">
        @include('logging-fields.partials.filters')
    </div>
</div>

<div id="logging-fields-table">
    @include('logging-fields.partials.table', ['loggingFields' => $loggingFields])
</div>

@endsection
