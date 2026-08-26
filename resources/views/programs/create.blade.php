@extends('layouts.app')

@section('title', 'Create Program')

@section('content')
<x-form-shell>
    <x-form-errors />

    <form method="POST" action="{{ route('programs.store') }}" id="programs-create-form">
        @csrf
        <x-page-header context="form" entity-type="Program" />

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
                          rows="3"
                          maxlength="2000">{{ old('description') }}</textarea>
            </x-form-field>

            <x-form-field label="Projects" name="project_ids" :required="true">
                <x-token-picker
                    picker-id="program-create-projects"
                    name="project_ids[]"
                    :items="$projects"
                    :selected-ids="old('project_ids', [])"
                    placeholder="Search projects..."
                    height="220px"
                    entity="project"
                />
            </x-form-field>

            <x-form-options>
                <x-form-switch
                    name="active"
                    label="Active"
                    :checked="old('active', '1') !== '0'"
                    class="mb-0"
                />
            </x-form-options>
        </x-section-card>
    </form>
</x-form-shell>
<x-save-bar form-id="programs-create-form" save-label="Create Program" />
@endsection
