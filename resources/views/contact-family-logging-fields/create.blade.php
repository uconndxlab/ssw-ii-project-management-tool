@extends('layouts.app')

@section('title', 'Create Contact Family Logging Field')

@section('content')
<div class="row justify-content-center mb-4">
    <div class="col-lg-8">
        <h1>Create Contact Family Logging Field</h1>
        <p class="text-muted">Define a field available to contact families when classifying activities.</p>
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

                <form method="POST" action="{{ route('contact-family-logging-fields.store') }}" id="contact-family-logging-field-create-form">
                    @csrf
                    @include('contact-family-logging-fields.partials.form-fields')
                </form>
            </div>
        </div>
    </div>
</div>

<x-save-bar form-id="contact-family-logging-field-create-form" cancel-url="{{ route('contact-family-logging-fields.index') }}" save-label="Create Field" />
@endsection
