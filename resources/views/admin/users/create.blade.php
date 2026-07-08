@extends('layouts.app')

@section('title', 'Create User')

@section('content')
<div class="row justify-content-center mb-4">
    <div class="col-md-8">
        <h1>Create User</h1>
    </div>
</div>

<div class="row justify-content-center">
    <div class="col-md-8">
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
            @include('admin.users.partials.form-fields')
        </form>
    </div>
</div>
<x-save-bar form-id="users-create-form" cancel-url="{{ route('admin.users.index') }}" save-label="Create User" />
@endsection
