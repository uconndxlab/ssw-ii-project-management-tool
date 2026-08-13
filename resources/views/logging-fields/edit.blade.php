@extends('layouts.app')

@section('title', 'Edit Logging Field')

@section('content')
<div class="row justify-content-center">
    <div class="col-xl-10">
        <x-page-header
            context="form"
            :title="old('name', $loggingField->name)"
            entity-type="Logging Field"
            mode="edit"
            description="Update the field definition for this field."
        />

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

<x-save-bar form-id="logging-field-edit-form" save-label="Save Changes" :last-saved-at="$loggingField->updated_at" />

@endsection
