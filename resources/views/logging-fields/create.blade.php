@extends('layouts.app')

@section('title', 'Create Logging Field')

@section('content')
<x-form-shell>
    <x-form-errors />

    <form method="POST" action="{{ route('logging-fields.store') }}" id="logging-field-create-form">
        @csrf
        <x-page-header context="form" entity-type="Logging Field" />
        @include('logging-fields.partials.form-fields')
    </form>
</x-form-shell>

<x-save-bar form-id="logging-field-create-form" save-label="Create Logging Field" />
@endsection
