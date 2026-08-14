@extends('layouts.app')

@section('title', 'Create State')

@section('content')
<x-form-shell>
    <x-form-errors />

    <form method="POST" action="{{ route('states.store') }}" id="states-create-form">
        @csrf
        <x-page-header context="form" entity-type="State" />

        <x-section-card title="Information">
            <x-form-field label="Name" for="name" name="name" :required="true" class="mb-0">
                <input type="text"
                       class="form-control @error('name') is-invalid @enderror"
                       id="name"
                       name="name"
                       value="{{ old('name') }}"
                       required>
            </x-form-field>
        </x-section-card>
    </form>
</x-form-shell>
<x-save-bar form-id="states-create-form" save-label="Create State" />
@endsection
