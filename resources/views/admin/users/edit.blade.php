@extends('layouts.app')

@section('title', 'Edit User')

@section('content')
<x-form-shell>
    <x-form-errors />

    <form method="POST" action="{{ route('admin.users.update', $user) }}" id="users-edit-form">
        @csrf
        @method('PUT')
        <x-page-header context="form" :title="$user->name" entity-type="User" mode="edit" />
        @include('admin.users.partials.form-fields')
    </form>
</x-form-shell>
<x-save-bar form-id="users-edit-form" save-label="Save User" :last-saved-at="$user->updated_at" />
@endsection
