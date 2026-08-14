@extends('layouts.app')

@section('title', 'Edit Project')

@section('content')
<x-form-shell>
    <x-form-errors />

    <form method="POST" action="{{ route('projects.update', $project) }}" id="projects-edit-form">
        @csrf
        @method('PUT')
        <x-page-header context="form" :title="old('name', $project->name)" entity-type="Project" mode="edit" />

        <x-section-card title="Information">
            <x-form-field label="Name" for="name" name="name" :required="true">
                <input type="text"
                       class="form-control @error('name') is-invalid @enderror"
                       id="name"
                       name="name"
                       value="{{ old('name', $project->name) }}"
                       required>
            </x-form-field>

            <x-form-field label="Description" for="description" name="description">
                <textarea class="form-control @error('description') is-invalid @enderror"
                          id="description"
                          name="description"
                          rows="4">{{ old('description', $project->description) }}</textarea>
            </x-form-field>

            <x-form-options>
                <x-form-switch
                    name="active"
                    label="Active"
                    :checked="old('active', $project->active)"
                    class="mb-0"
                />
            </x-form-options>
        </x-section-card>
    </form>
</x-form-shell>
<x-save-bar form-id="projects-edit-form" save-label="Save Project" :last-saved-at="$project->updated_at" />
@endsection
