@extends('layouts.app')

@section('title', 'Forgot Password')

@section('content')
<div class="app-auth">
    <div class="app-auth-panel">
        <div class="card">
            <div class="card-body">
                @if(session('reset_link_sent'))
                    <div class="app-auth-brand">
                        <span class="app-record-icon" aria-hidden="true"><i class="bi bi-envelope"></i></span>
                        <h1 class="app-auth-title">Check your email</h1>
                    </div>

                    <p class="app-auth-lede" role="status">
                        If an account exists for <strong class="text-body-emphasis text-break">{{ old('email') }}</strong>, we've sent a link to reset your password. It expires in {{ $expireMinutes }} minutes.
                    </p>

                    <a href="{{ route('login') }}" class="btn btn-outline-primary w-100">Back to login</a>

                    <p class="small text-muted text-center mt-3 mb-0">
                        Wrong address? <a href="{{ route('password.request') }}">Use a different email</a>
                    </p>
                @else
                    <div class="app-auth-brand">
                        <span class="app-rail-mark app-auth-mark" aria-hidden="true">II</span>
                        <h1 class="app-auth-title">Reset your password</h1>
                    </div>

                    <p class="app-auth-lede">Enter your email and we'll send you a link to reset your password.</p>

                    @if ($errors->any())
                        <div class="alert alert-danger">
                            <ul class="mb-0">
                                @foreach ($errors->all() as $error)
                                    <li>{{ $error }}</li>
                                @endforeach
                            </ul>
                        </div>
                    @endif

                    <form method="POST" action="{{ route('password.email') }}">
                        @csrf

                        <div class="mb-3">
                            <label for="email" class="form-label">Email</label>
                            <input type="email"
                                   class="form-control @error('email') is-invalid @enderror"
                                   id="email"
                                   name="email"
                                   value="{{ old('email') }}"
                                   autocomplete="username"
                                   required
                                   autofocus>
                            @error('email')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <button type="submit" class="btn btn-primary w-100" data-busy-label="Sending…">Send reset link</button>
                    </form>

                    <div class="text-center mt-3">
                        <a href="{{ route('login') }}" class="btn btn-link btn-sm px-0 text-decoration-none">Back to login</a>
                    </div>
                @endif
            </div>
        </div>
    </div>
</div>
@endsection
