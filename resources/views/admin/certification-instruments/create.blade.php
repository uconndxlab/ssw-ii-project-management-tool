@extends('layouts.app')

@section('title', 'Create Certification Tool')

@section('content')
<x-form-shell>
    <x-form-errors />

    <form method="POST" action="{{ route('certification-instruments.store') }}" id="certification-instruments-create-form">
        @csrf
        <x-page-header form-id="certification-instruments-create-form" save-label="Create Tool" context="form" entity-type="Certification Tool" mode="create" />
        @include('admin.certification-instruments.partials.form-fields')
    </form>
</x-form-shell>
@endsection
