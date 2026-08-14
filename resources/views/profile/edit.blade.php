@extends('layouts.app')

@section('title', 'Edit Profile')

@section('content')
<x-form-shell>
    <x-form-errors />

    <form method="POST" action="{{ route('profile.password.update') }}" id="profile-edit-form">
        @csrf
        @method('PUT')

        <x-page-header context="form" title="Edit Profile" />

        <x-section-card title="Account">
            <x-form-field label="Name" for="name">
                <input type="text"
                       class="form-control"
                       id="name"
                       value="{{ $user->name }}"
                       disabled
                       aria-disabled="true">
            </x-form-field>

            <x-form-field label="Email" for="email" class="mb-0">
                <input type="email"
                       class="form-control"
                       id="email"
                       value="{{ $user->email }}"
                       disabled
                       aria-disabled="true">
            </x-form-field>
        </x-section-card>

        <x-section-card title="Change password">
            <x-form-field label="Current Password" for="current_password" name="current_password" :required="true">
                <input type="password"
                       class="form-control @error('current_password') is-invalid @enderror"
                       id="current_password"
                       name="current_password"
                       required
                       autocomplete="current-password">
            </x-form-field>

            <x-form-field label="New Password" for="password" name="password" :required="true">
                <input type="password"
                       class="form-control @error('password') is-invalid @enderror"
                       id="password"
                       name="password"
                       required
                       autocomplete="new-password">
            </x-form-field>

            <x-form-field label="Confirm New Password" for="password_confirmation" :required="true" class="mb-0">
                <input type="password"
                       class="form-control"
                       id="password_confirmation"
                       name="password_confirmation"
                       required
                       autocomplete="new-password">
            </x-form-field>
        </x-section-card>
    </form>
</x-form-shell>
<x-save-bar form-id="profile-edit-form" :resolve-cancel="true" save-label="Save" />
@endsection
