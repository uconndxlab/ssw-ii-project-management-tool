@extends('layouts.app')

@section('title', 'Create Logging Field')

@section('content')
<div class="row justify-content-center mb-4">
    <div class="col-lg-8">
        <h1>Create Logging Field</h1>
        <p class="text-muted">Define a new field that can be used across the application.</p>
    </div>
</div>

<div class="row justify-content-center">
    <div class="col-lg-8">
        <div class="card">
            <div class="card-body">
                @if ($errors->any())
                    <div class="alert alert-danger">
                        <ul class="mb-0">
                            @foreach ($errors->all() as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                <form method="POST" action="{{ route('logging-fields.store') }}" id="logging-field-create-form">
                    @csrf
                    @include('logging-fields.partials.form-fields')
                </form>
            </div>
        </div>
    </div>
</div>

<x-save-bar form-id="logging-field-create-form" cancel-url="{{ route('logging-fields.index') }}" save-label="Create Logging Field" />

@endsection
