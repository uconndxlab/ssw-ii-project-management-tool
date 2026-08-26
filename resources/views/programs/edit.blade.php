@extends('layouts.app')

@section('title', 'Edit Program')

@section('content')
<x-form-shell>
    <x-form-errors />

    <form method="POST" action="{{ route('programs.update', $program) }}" id="programs-edit-form">
        @csrf
        @method('PUT')
        <x-page-header context="form" :title="old('name', $program->name)" entity-type="Program" mode="edit" />

        <x-section-card title="Information">
            <x-form-field label="Name" for="name" name="name" :required="true">
                <input type="text"
                       class="form-control @error('name') is-invalid @enderror"
                       id="name"
                       name="name"
                       value="{{ old('name', $program->name) }}"
                       required>
            </x-form-field>

            <x-form-field label="Description" for="description" name="description">
                <textarea class="form-control @error('description') is-invalid @enderror"
                          id="description"
                          name="description"
                          rows="3"
                          maxlength="2000">{{ old('description', $program->description) }}</textarea>
            </x-form-field>

            <x-form-field label="Projects" name="project_ids" :required="true">
                <x-token-picker
                    picker-id="program-edit-projects"
                    name="project_ids[]"
                    :items="$projects"
                    :selected-ids="old('project_ids', $program->projects->pluck('id')->toArray())"
                    placeholder="Search projects..."
                    height="220px"
                    entity="project"
                />
            </x-form-field>

            <x-form-options>
                <x-form-switch
                    name="active"
                    label="Active"
                    :checked="old('active', $program->active)"
                    class="mb-0"
                />
            </x-form-options>
        </x-section-card>
    </form>
</x-form-shell>
<x-save-bar form-id="programs-edit-form" save-label="Save Program" :last-saved-at="$program->updated_at" />
@endsection
