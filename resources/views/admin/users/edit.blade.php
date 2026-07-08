@extends('layouts.app')

@section('title', 'Edit User')

@section('content')
<div class="row justify-content-center mb-4">
    <div class="col-md-8">
        <h1>Edit User</h1>
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

        <form method="POST" action="{{ route('admin.users.update', $user) }}" id="users-edit-form">
            @csrf
            @method('PUT')
            @include('admin.users.partials.form-fields')
        </form>
    </div>
</div>
<x-save-bar form-id="users-edit-form" cancel-url="{{ route('admin.users.index') }}" save-label="Save User" :last-saved-at="$user->updated_at" />
@endsection
