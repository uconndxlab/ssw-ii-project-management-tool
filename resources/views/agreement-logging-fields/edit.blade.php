@extends('layouts.app')

@section('title', 'Edit Agreement Logging Field')

@section('content')
<div class="row justify-content-center mb-4">
    <div class="col-lg-8">
        <h1>Edit Agreement Logging Field</h1>
        <p class="text-muted">Update the field definition for "{{ $agreementLoggingField->name }}".</p>
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

                <form method="POST" action="{{ route('agreement-logging-fields.update', $agreementLoggingField) }}" id="agreement-logging-field-edit-form">
                    @csrf
                    @method('PUT')
                    @include('agreement-logging-fields.partials.form-fields')
                </form>
            </div>
        </div>
    </div>
</div>

<x-save-bar form-id="agreement-logging-field-edit-form" cancel-url="{{ route('agreement-logging-fields.index') }}" save-label="Save Changes" :last-saved-at="$agreementLoggingField->updated_at" />
@endsection
