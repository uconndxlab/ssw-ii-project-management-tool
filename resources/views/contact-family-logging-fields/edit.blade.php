@extends('layouts.app')

@section('title', 'Edit Contact Family Logging Field')

@section('content')
<div class="row justify-content-center mb-4">
    <div class="col-lg-8">
        <h1>Edit Contact Family Logging Field</h1>
        <p class="text-muted">Update the field definition for "{{ $contactFamilyLoggingField->name }}".</p>
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

                <form method="POST" action="{{ route('contact-family-logging-fields.update', $contactFamilyLoggingField) }}" id="contact-family-logging-field-edit-form">
                    @csrf
                    @method('PUT')
                    @include('contact-family-logging-fields.partials.form-fields')
                </form>
            </div>
        </div>
    </div>
</div>

<x-save-bar form-id="contact-family-logging-field-edit-form" cancel-url="{{ route('contact-family-logging-fields.index') }}" save-label="Save Changes" :last-saved-at="$contactFamilyLoggingField->updated_at" />
@endsection
