@extends('layouts.app')

@section('title', 'Create Certification Tool')

@section('content')
<x-form-shell>
    <x-form-errors />

    <form method="POST" action="{{ route('certification-tools.store') }}" id="certification-tools-create-form">
        @csrf
        <x-page-header context="form" entity-type="Certification Tool" mode="create" />
        @include('admin.certification-tools.partials.form-fields')
    </form>
</x-form-shell>
<x-save-bar form-id="certification-tools-create-form" save-label="Create Tool" />
@endsection
