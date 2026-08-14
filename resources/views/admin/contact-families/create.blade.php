@extends('layouts.app')

@section('title', 'Create Activity Family')

@section('content')
<x-form-shell>
    <x-form-errors />

    <form method="POST" action="{{ route('contact-families.store') }}" id="contact-families-create-form">
        @csrf
        <x-page-header context="form" entity-type="Activity Family" mode="create" />
        @include('admin.contact-families.partials.form-fields')
    </form>
</x-form-shell>
<x-save-bar form-id="contact-families-create-form" save-label="Create Activity Family" />
@endsection
