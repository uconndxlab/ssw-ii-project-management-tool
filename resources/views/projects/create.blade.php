@extends('layouts.app')

@section('title', 'Create Project')

@section('content')
<x-form-shell>
    <x-form-errors />

    <form method="POST" action="{{ route('projects.store') }}" id="projects-create-form">
        @csrf
        <x-page-header context="form" entity-type="Project" />

        <x-section-card title="Information">
            <x-form-field label="Name" for="name" name="name" :required="true">
                <input type="text"
                       class="form-control @error('name') is-invalid @enderror"
                       id="name"
                       name="name"
                       value="{{ old('name') }}"
                       required>
            </x-form-field>

            <x-form-field label="Description" for="description" name="description">
                <textarea class="form-control @error('description') is-invalid @enderror"
                          id="description"
                          name="description"
                          rows="4">{{ old('description') }}</textarea>
            </x-form-field>

            <x-form-options>
                <x-form-switch
                    name="active"
                    label="Active"
                    :checked="old('active', '1') === '1' || old('active') === null"
                    class="mb-0"
                />
            </x-form-options>
        </x-section-card>
    </form>
</x-form-shell>
<x-save-bar form-id="projects-create-form" save-label="Create Project" />
@endsection
