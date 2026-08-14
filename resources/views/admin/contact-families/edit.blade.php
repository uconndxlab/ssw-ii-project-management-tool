@extends('layouts.app')

@section('title', 'Edit Activity Family')

@section('content')
<x-form-shell>
    <x-form-errors />

    <form method="POST" action="{{ route('contact-families.update', $contactFamily) }}" id="contact-families-edit-form">
        @csrf
        @method('PUT')
        <x-page-header
            context="form"
            :title="old('name', $contactFamily->name)"
            entity-type="Activity Family"
            mode="edit"
        />
        @include('admin.contact-families.partials.form-fields', ['contactFamily' => $contactFamily])
    </form>
</x-form-shell>
<x-save-bar form-id="contact-families-edit-form" save-label="Save Activity Family" :last-saved-at="$contactFamily->updated_at" />
@endsection
