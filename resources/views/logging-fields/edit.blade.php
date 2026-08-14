@extends('layouts.app')

@section('title', 'Edit Logging Field')

@section('content')
<x-form-shell>
    <x-form-errors />

    <form method="POST" action="{{ route('logging-fields.update', $loggingField) }}" id="logging-field-edit-form">
        @csrf
        @method('PUT')
        <x-page-header
            context="form"
            :title="old('name', $loggingField->name)"
            entity-type="Logging Field"
            mode="edit"
        />
        @include('logging-fields.partials.form-fields')
    </form>
</x-form-shell>

<x-save-bar form-id="logging-field-edit-form" save-label="Save Changes" :last-saved-at="$loggingField->updated_at" />
@endsection
