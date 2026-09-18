@extends('layouts.app')

@section('title', 'Edit Certification Tool')

@section('content')
<x-form-shell>
    <x-form-errors />

    <form method="POST" action="{{ route('certification-tools.update', $certificationTool) }}" id="certification-tools-edit-form">
        @csrf
        @method('PUT')
        <x-page-header context="form" entity-type="Certification Tool" mode="edit" />
        @include('admin.certification-tools.partials.form-fields')
    </form>
</x-form-shell>
<x-save-bar form-id="certification-tools-edit-form" save-label="Save Tool" />
@endsection
