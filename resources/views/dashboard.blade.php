@extends('layouts.app')

@section('title', 'Dashboard')

@section('content')
<div class="row">
    <div class="col-12">
        <h1>Welcome, {{ auth()->user()->name }}</h1>
        <p class="lead">You are logged in as <strong>{{ ucfirst(auth()->user()->role) }}</strong></p>
    </div>
</div>

<div class="row mt-4">
    <div class="col-md-4">
        <div class="card">
            <div class="card-body">
                <h5 class="card-title">Your Profile</h5>
                <p class="card-text">
                    <strong>Name:</strong> {{ auth()->user()->name }}<br>
                    <strong>Email:</strong> {{ auth()->user()->email }}<br>
                    <strong>Role:</strong> {{ ucfirst(auth()->user()->role) }}
                </p>
            </div>
        </div>
    </div>

    @if(auth()->user()->role === 'admin')
    <div class="col-md-4">
        <div class="card">
            <div class="card-body">
                <h5 class="card-title">Admin Panel</h5>
                <p class="card-text">Manage users and system settings.</p>
                <a href="{{ route('admin.users.index') }}" class="btn btn-primary">Manage Users</a>
            </div>
        </div>
    </div>
    @endif
</div>
@endsection
