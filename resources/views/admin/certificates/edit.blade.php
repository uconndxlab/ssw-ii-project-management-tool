@extends('layouts.app')

@section('title', 'Edit Certificate')

@section('content')
<x-form-shell>
    <x-form-errors />

    <form method="POST" action="{{ route('certificates.update', $certificate) }}" id="certificates-edit-form">
        @csrf
        @method('PUT')
        <x-page-header context="form" entity-type="Certificate" mode="edit" />
        @include('admin.certificates.partials.form-fields')
    </form>
</x-form-shell>
<x-save-bar form-id="certificates-edit-form" save-label="Save Certificate" />
@endsection
