@extends('layouts.app')

@section('title', 'Edit Team')

@section('content')
@php
    $selectedProjectIds = old('project_ids', $team->projects->pluck('id')->toArray());
    $selectedProgramIds = old('program_ids', $team->programs->pluck('id')->toArray());
    $teamUserOptions = $users->map(function ($user) {
        $role = !empty($user->role) ? ' (' . ucfirst($user->role) . ')' : '';

        return [
            'value' => $user->id,
            'label' => $user->name . $role,
            'search' => trim($user->name . ' ' . ($user->email ?? '') . ' ' . ($user->role ?? '')),
        ];
    });
@endphp
<div class="row">
    <div class="col-md-8">
        <x-page-header context="form" :title="old('name', $team->name)" entity-type="Team" mode="edit" />

        <div class="card">
            <div class="card-body">
                @if ($errors->any())
                    <div class="alert alert-danger">
                        <ul class="mb-0">
                            @foreach ($errors->all() as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                <form method="POST" action="{{ route('teams.update', $team) }}" id="teams-edit-form">
                    @csrf
                    @method('PUT')

                    <div class="mb-3">
                        <label for="name" class="form-label">Team Name</label>
                        <input type="text"
                               class="form-control @error('name') is-invalid @enderror"
                               id="name"
                               name="name"
                               value="{{ old('name', $team->name) }}"
                               required>
                        @error('name')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="mb-3">
                        <label for="active" class="form-label">Status</label>
                        <select class="form-select @error('active') is-invalid @enderror"
                                id="active"
                                name="active"
                                required>
                            <option value="1" {{ old('active', $team->active) ? 'selected' : '' }}>Active</option>
                            <option value="0" {{ !old('active', $team->active) ? 'selected' : '' }}>Inactive</option>
                        </select>
                        @error('active')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="mb-4">
                        <x-project-program-scope-picker
                            scope-id="team-edit-scope"
                            :projects="$projects"
                            :selected-project-ids="$selectedProjectIds"
                            :selected-program-ids="$selectedProgramIds"
                            :show-scope-mode-selector="true"
                            :selected-scope-mode="old('program_scope_mode', $team->program_scope_mode?->value ?? 'specific')"
                            project-help-text="Use projects to filter programs; team projects are inferred and not saved."
                            program-help-text="Programs are the saved team scope when Specific is selected."
                            scope-mode-help-text="Choose whether this team applies to all programs, only specific programs, or no programs."
                        />
                        <div class="alert alert-warning small mt-3 mb-0 d-none" data-team-none-scope-warning>
                            Teams with None scope will save successfully, but they will not be available for program-scoped agreement assignment.
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Team Members</label>

                        <x-token-picker
                            picker-id="team-edit-users"
                            name="user_ids[]"
                            :options="$teamUserOptions"
                            :selected-ids="old('user_ids', $team->users->pluck('id')->toArray())"
                            label-key="label"
                            value-key="value"
                            search-key="search"
                            placeholder="Search to add members..."
                            :height="'300px'"
                            entity="user"
                        />

                        <small class="text-muted">
                            Select users who should be members of this team.
                        </small>
                    </div>

                </form>
            </div>
        </div>
    </div>
</div>
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
