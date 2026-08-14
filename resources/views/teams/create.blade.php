@extends('layouts.app')

@section('title', 'Create Team')

@section('content')
@php
    $selectedProjectIds = old('project_ids', []);
    $selectedProgramIds = old('program_ids', []);
    $teamUserOptions = $users->map(function ($user) {
        $role = !empty($user->role) ? ' (' . ucfirst($user->role) . ')' : '';

        return [
            'value' => $user->id,
            'label' => $user->name . $role,
            'search' => trim($user->name . ' ' . ($user->email ?? '') . ' ' . ($user->role ?? '')),
        ];
    });
@endphp
<x-form-shell>
    <x-form-errors />

    <form method="POST" action="{{ route('teams.store') }}" id="teams-create-form">
        @csrf
        <x-page-header context="form" entity-type="Team" />

        <x-section-card title="Information">
            <x-form-field label="Name" for="name" name="name" :required="true">
                <input type="text"
                       class="form-control @error('name') is-invalid @enderror"
                       id="name"
                       name="name"
                       value="{{ old('name') }}"
                       required>
            </x-form-field>

            <x-form-options>
                <x-form-switch
                    name="active"
                    label="Active"
                    :checked="old('active', true)"
                    class="mb-0"
                />
            </x-form-options>

            <x-project-program-scope-picker
                scope-id="team-create-scope"
                :projects="$projects"
                :selected-project-ids="$selectedProjectIds"
                :selected-program-ids="$selectedProgramIds"
                :show-scope-mode-selector="true"
                :selected-scope-mode="old('program_scope_mode', 'specific')"
            />
            <div class="alert alert-warning small mt-3 mb-0 d-none" data-team-none-scope-warning>
                Teams with None scope save, but cannot be assigned to program-scoped agreements.
            </div>
        </x-section-card>

        <x-section-card title="Members">
            <x-form-field label="Team Members" name="user_ids" class="mb-0">
                <x-token-picker
                    picker-id="team-create-users"
                    name="user_ids[]"
                    :options="$teamUserOptions"
                    :selected-ids="old('user_ids', [])"
                    label-key="label"
                    value-key="value"
                    search-key="search"
                    placeholder="Search to add members..."
                    :height="'220px'"
                    entity="user"
                />
            </x-form-field>
        </x-section-card>
    </form>
</x-form-shell>
<x-save-bar form-id="teams-create-form" save-label="Create Team" />

@once
<script>
(function () {
    function syncTeamNoneScopeWarning(section) {
        const warning = document.querySelector('[data-team-none-scope-warning]');

        if (!section || !warning) {
            return;
        }

        const checked = section.querySelector('input[name="program_scope_mode"]:checked');
        warning.classList.toggle('d-none', !checked || checked.value !== 'none');
    }

    document.addEventListener('DOMContentLoaded', function () {
        const section = document.querySelector('[data-scope-id="team-create-scope"]');
        syncTeamNoneScopeWarning(section);
        section?.addEventListener('project-program-scope:change', function () {
            syncTeamNoneScopeWarning(section);
        });
    });
})();
</script>
@endonce
@endsection
