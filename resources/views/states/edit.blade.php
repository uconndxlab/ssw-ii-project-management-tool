@extends('layouts.app')

@section('title', 'Edit State')

@section('content')
<x-form-shell>
    <x-form-errors />

    <form method="POST" action="{{ route('states.update', $state) }}" id="states-edit-form">
        @csrf
        @method('PUT')
        <x-page-header context="form" :title="old('name', $state->name)" entity-type="State" mode="edit" />

        <x-section-card title="Information">
            <x-form-field label="Name" for="name" name="name" :required="true" class="mb-0">
                <input type="text"
                       class="form-control @error('name') is-invalid @enderror"
                       id="name"
                       name="name"
                       value="{{ old('name', $state->name) }}"
                       required>
            </x-form-field>
        </x-section-card>
    </form>
</x-form-shell>
<x-save-bar form-id="states-edit-form" save-label="Save State" :last-saved-at="$state->updated_at" />
@endsection
