@extends('layouts.app')

@section('title', 'Forgot Password')

@section('content')
<div class="row justify-content-center">
    <div class="col-md-5">
        <div class="card shadow-sm">
            <div class="card-body p-5">
                <h3 class="card-title text-center mb-2">Forgot Password</h3>
                <p class="text-muted text-center small mb-4">
                    Enter your email and we will send you a link to reset your password.
                </p>

                @if(session('status'))
                    <div class="alert alert-success">{{ session('status') }}</div>
                @endif

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
                               required
                               autofocus>
                        @error('email')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <button type="submit" class="btn btn-primary w-100 mb-3">Send Reset Link</button>

                    <div class="text-center">
                        <a href="{{ route('login') }}" class="btn btn-link btn-sm px-0 text-decoration-none">
                            Back to login
                        </a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection
