@extends('layouts.app')

@section('title', 'Edit Profile')

@section('content')
<div class="row justify-content-center">
    <div class="col-lg-8">
        @if ($errors->any())
            <div class="alert alert-danger">
                <ul class="mb-0">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <form method="POST" action="{{ route('profile.password.update') }}" id="profile-edit-form">
            @csrf
            @method('PUT')

            <x-form-page-header
                title="Edit Profile"
                :back-route="route('profile')"
                back-label="Back to profile"
            />

            <div class="card mb-4">
                <div class="card-body">
                    <h5 class="mb-3">Account Information</h5>

                    <div class="mb-3">
                        <label for="name" class="form-label">Name</label>
                        <input type="text"
                               class="form-control"
                               id="name"
                               value="{{ $user->name }}"
                               disabled
                               aria-disabled="true">
                    </div>

                    <div class="mb-0">
                        <label for="email" class="form-label">Email</label>
                        <input type="email"
                               class="form-control"
                               id="email"
                               value="{{ $user->email }}"
                               disabled
                               aria-disabled="true">
                    </div>
                </div>
            </div>

            <div class="card mb-4">
                <div class="card-body">
                    <h5 class="mb-3">Change Password</h5>

                    <div class="mb-3">
                        <label for="current_password" class="form-label">Current Password</label>
                        <input type="password"
                               class="form-control @error('current_password') is-invalid @enderror"
                               id="current_password"
                               name="current_password"
                               required
                               autocomplete="current-password">
                        @error('current_password')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="mb-3">
                        <label for="password" class="form-label">New Password</label>
                        <input type="password"
                               class="form-control @error('password') is-invalid @enderror"
                               id="password"
                               name="password"
                               required
                               autocomplete="new-password">
                        @error('password')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="mb-0">
                        <label for="password_confirmation" class="form-label">Confirm New Password</label>
                        <input type="password"
                               class="form-control"
                               id="password_confirmation"
                               name="password_confirmation"
                               required
                               autocomplete="new-password">
                    </div>
                </div>
            </div>
        </form>
    </div>
</div>
<x-save-bar form-id="profile-edit-form" cancel-url="{{ route('profile') }}" save-label="Save" />
@endsection
