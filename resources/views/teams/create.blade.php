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
<div class="row">
    <div class="col-md-8">
        <x-page-header context="form" entity-type="Team" />

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

                <form method="POST" action="{{ route('teams.store') }}" id="teams-create-form">
                    @csrf

                    <div class="mb-3">
                        <label for="name" class="form-label">Team Name</label>
                        <input type="text"
                               class="form-control @error('name') is-invalid @enderror"
                               id="name"
                               name="name"
                               value="{{ old('name') }}"
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
                            <option value="1" {{ old('active', '1') === '1' ? 'selected' : '' }}>Active</option>
                            <option value="0" {{ old('active') === '0' ? 'selected' : '' }}>Inactive</option>
                        </select>
                        @error('active')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="mb-4">
                        <x-project-program-scope-picker
                            scope-id="team-create-scope"
                            :projects="$projects"
                            :selected-project-ids="$selectedProjectIds"
                            :selected-program-ids="$selectedProgramIds"
                            :show-scope-mode-selector="true"
                            :selected-scope-mode="old('program_scope_mode', 'specific')"
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
                            picker-id="team-create-users"
                            name="user_ids[]"
                            :options="$teamUserOptions"
                            :selected-ids="old('user_ids', [])"
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

                    <div class="d-flex gap-2">
                        <button type="submit" class="btn btn-primary">Create Team</button>
                        <a href="{{ route('teams.index') }}" class="btn btn-secondary">Cancel</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
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
