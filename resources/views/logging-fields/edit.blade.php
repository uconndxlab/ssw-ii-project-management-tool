@extends('layouts.app')

@section('title', 'Edit Logging Field')

@section('content')
<div class="row justify-content-center mb-4">
    <div class="col-lg-8">
        <h1>Edit Logging Field</h1>
        <p class="text-muted">Update the field definition for "{{ $loggingField->name }}".</p>
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

                <form method="POST" action="{{ route('logging-fields.update', $loggingField) }}" id="logging-field-edit-form">
                    @csrf
                    @method('PUT')
                    @include('logging-fields.partials.form-fields')
                </form>
            </div>
        </div>
    </div>
</div>

<x-save-bar form-id="logging-field-edit-form" cancel-url="{{ route('logging-fields.index') }}" save-label="Save Changes" :last-saved-at="$loggingField->updated_at" />

@endsection
