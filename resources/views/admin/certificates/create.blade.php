@extends('layouts.app')

@section('title', 'Create Certificate')

@section('content')
<x-form-shell>
    <x-form-errors />

    <form method="POST" action="{{ route('certificates.store') }}" id="certificates-create-form">
        @csrf
        <x-page-header context="form" entity-type="Certificate" mode="create" />
        @include('admin.certificates.partials.form-fields')
    </form>
</x-form-shell>
<x-save-bar form-id="certificates-create-form" save-label="Create Certificate" />
@endsection
