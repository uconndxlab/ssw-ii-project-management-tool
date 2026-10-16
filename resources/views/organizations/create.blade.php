@extends('layouts.app')

@section('title', 'Create Organization')

@section('content')
<x-form-shell>
    <x-form-errors />

    <form method="POST" action="{{ route('organizations.store') }}" id="organizations-create-form">
        @csrf
        <x-page-header form-id="organizations-create-form" save-label="Create Organization" context="form" entity-type="Organization" />
        @include('organizations.partials.form-fields')
    </form>
</x-form-shell>
@endsection
