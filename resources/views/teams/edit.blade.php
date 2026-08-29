@extends('layouts.app')

@section('title', 'Edit Team')

@section('content')
@php
    $selectedProjectIds = old('project_ids', $team->projects->pluck('id')->toArray());
    $selectedProgramIds = old('program_ids', $team->programs->pluck('id')->toArray());
    $teamUserOptions = $users->map(function ($user) {
        $role = $user->accessLabel() ? ' (' . $user->accessLabel() . ')' : '';

        return [
            'value' => $user->id,
            'label' => $user->name . $role,
            'search' => trim($user->name . ' ' . ($user->email ?? '') . ' ' . $user->accessLabel()),
        ];
    });
@endphp
<x-form-shell>
    <x-form-errors />

    <form method="POST" action="{{ route('teams.update', $team) }}" id="teams-edit-form">
        @csrf
        @method('PUT')
        <x-page-header context="form" :title="old('name', $team->name)" entity-type="Team" mode="edit" />

        <x-section-card title="Information">
            <x-form-field label="Name" for="name" name="name" :required="true">
                <input type="text"
                       class="form-control @error('name') is-invalid @enderror"
                       id="name"
                       name="name"
                       value="{{ old('name', $team->name) }}"
                       required>
            </x-form-field>

            <x-form-options>
                <x-form-switch
                    name="active"
                    label="Active"
                    :checked="old('active', $team->active)"
                    class="mb-0"
                />
            </x-form-options>

            <x-project-program-scope-picker
                scope-id="team-edit-scope"
                :projects="$projects"
                :selected-project-ids="$selectedProjectIds"
                :selected-program-ids="$selectedProgramIds"
                :show-scope-mode-selector="true"
                :selected-scope-mode="old('program_scope_mode', $team->program_scope_mode?->value ?? 'specific')"
                :lock-all="$team->program_scope_mode?->value === 'all'"
            />
            <div class="alert alert-warning small mt-3 mb-0 d-none" data-team-none-scope-warning>
                Teams with None scope save, but cannot be assigned to program-scoped agreements.
            </div>
        </x-section-card>

        <x-section-card title="Members">
            <x-form-field label="Team Members" name="user_ids" class="mb-0">
                <x-token-picker
                    picker-id="team-edit-users"
                    name="user_ids[]"
                    :options="$teamUserOptions"
                    :selected-ids="old('user_ids', $team->users->pluck('id')->toArray())"
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
<x-save-bar form-id="teams-edit-form" save-label="Save Team" :last-saved-at="$team->updated_at" />

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
        const section = document.querySelector('[data-scope-id="team-edit-scope"]');
        syncTeamNoneScopeWarning(section);
        section?.addEventListener('project-program-scope:change', function () {
            syncTeamNoneScopeWarning(section);
        });
    });
})();
</script>
@endonce
@endsection
