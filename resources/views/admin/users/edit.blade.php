@extends('layouts.app')

@section('title', 'Edit User')

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

        <form method="POST" action="{{ route('admin.users.update', $user) }}" id="users-edit-form">
            @csrf
            @method('PUT')
            <x-form-page-header
                entity-type="User"
                :entity-type-badge-class="\App\Support\EntityBadge::typeClasses('user')"
                mode="edit"
                :record-name="$user->name"
                :back-route="route('users.show', $user)"
                back-label="Back to user"
                :show-active="true"
                :active-default="old('active', $user->active)"
                active-help="Inactive users cannot log in and are removed from teams, agreements, and assignment pickers. Activity history and contributions are kept."
            />
            @include('admin.users.partials.form-fields')
        </form>
    </div>
</div>
<x-save-bar form-id="users-edit-form" cancel-url="{{ route('admin.users.index') }}" save-label="Save User" :last-saved-at="$user->updated_at" />
@endsection
