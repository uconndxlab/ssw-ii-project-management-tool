@extends('layouts.app')

@section('title', 'Create User')

@section('content')
<x-form-shell>
    <x-form-errors />

    <form method="POST" action="{{ route('admin.users.store') }}" id="users-create-form">
        @csrf
        <x-page-header form-id="users-create-form" save-label="Create User" context="form" entity-type="User" mode="create" />
        @include('admin.users.partials.form-fields')
    </form>
</x-form-shell>
@endsection
