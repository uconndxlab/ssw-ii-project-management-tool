@extends('layouts.app')

@section('title', 'Create Agreement Logging Field')

@section('content')
<div class="row mb-4">
    <div class="col-12">
        <h1>Create Agreement Logging Field</h1>
        <p class="text-muted">Define a field available to agreements when logging activities.</p>
    </div>
</div>

<div class="row">
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

                <form method="POST" action="{{ route('agreement-logging-fields.store') }}" id="agreement-logging-field-create-form">
                    @csrf
                    @include('agreement-logging-fields.partials.form-fields')
                </form>
            </div>
        </div>
    </div>
</div>

<x-save-bar form-id="agreement-logging-field-create-form" cancel-url="{{ route('agreement-logging-fields.index') }}" save-label="Create Field" />
@endsection
