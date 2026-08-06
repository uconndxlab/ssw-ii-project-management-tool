@extends('layouts.app')

@section('title', 'Create User')

@section('content')
<div class="row justify-content-center">
    <div class="col-lg-10">
        @if ($errors->any())
            <div class="alert alert-danger">
                <ul class="mb-0">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <form method="POST" action="{{ route('admin.users.store') }}" id="users-create-form">
            @csrf
            <x-page-header
                context="form"
                entity-type="User"
                mode="create"
                :show-active="true"
                :active-default="old('active', true)"
                active-help="Inactive users cannot log in and are removed from teams, agreements, and assignment pickers. Activity history and contributions are kept."
            />
            @include('admin.users.partials.form-fields')
        </form>
    </div>
</div>
<x-save-bar form-id="users-create-form" save-label="Create User" />
@endsection
