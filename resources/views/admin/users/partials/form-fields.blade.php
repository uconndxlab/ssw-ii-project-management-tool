@php
    $isEdit = $user->exists;
    $selectedProjectIds = old('project_ids', $user->projects?->pluck('id')->toArray() ?? []);
    $selectedProgramIds = old('program_ids', $user->programs?->pluck('id')->toArray() ?? []);
    $programOptions = $projects->flatMap(function ($project) {
        return $project->programs->map(function ($program) use ($project) {
            return [
                'id' => $program->id,
                'name' => $program->name,
                'context' => $project->name,
                'contextBadgeClass' => 'bg-primary',
            ];
        });
    })->values();
    $projectProgramMap = $projects->mapWithKeys(function ($project) {
        return [(string) $project->id => $project->programs->pluck('id')->map(fn ($id) => (string) $id)->values()->all()];
    })->all();
@endphp

<div class="card mb-4">
    <div class="card-body">
        <h5 class="mb-3">User Information</h5>

        <div class="mb-3">
            <label for="name" class="form-label required-label">Name</label>
            <input type="text"
                   class="form-control @error('name') is-invalid @enderror"
                   id="name"
                   name="name"
                   value="{{ old('name', $user->name) }}"
                   required>
            @error('name')
                <div class="invalid-feedback">{{ $message }}</div>
            @enderror
        </div>

        <div class="mb-3">
            <label for="email" class="form-label required-label">Email</label>
            <input type="email"
                   class="form-control @error('email') is-invalid @enderror"
                   id="email"
                   name="email"
                   value="{{ old('email', $user->email) }}"
                   required>
            @error('email')
                <div class="invalid-feedback">{{ $message }}</div>
            @enderror
        </div>

        <div>
            <label for="password" class="form-label {{ $isEdit ? '' : 'required-label' }}">Password{{ $isEdit ? ' (Optional)' : '' }}</label>
            <input type="password"
                   class="form-control @error('password') is-invalid @enderror"
                   id="password"
                   name="password"
                   {{ $isEdit ? '' : 'required' }}>
            @if($isEdit)
                <div class="form-text">Leave blank to keep the current password.</div>
            @endif
            @error('password')
                <div class="invalid-feedback">{{ $message }}</div>
            @enderror
        </div>
    </div>
</div>

<div class="card mb-4" data-user-scope-section data-project-program-map='@json($projectProgramMap)'>
    <div class="card-body">
        <div class="mb-3">
            <h5 class="mb-1">Scope Assignments</h5>
        </div>

        <div class="row g-3 mb-3">
            <div class="col-md-6">
                <label for="role" class="form-label required-label">Role</label>
                <select class="form-select @error('role') is-invalid @enderror"
                        id="role"
                        name="role"
                        required>
                    <option value="">Select role...</option>
                    <option value="admin" {{ old('role', $user->role) === 'admin' ? 'selected' : '' }}>Admin</option>
                    <option value="staff" {{ old('role', $user->role) === 'staff' ? 'selected' : '' }}>Staff</option>
                    <option value="consultant" {{ old('role', $user->role) === 'consultant' ? 'selected' : '' }}>Consultant</option>
                </select>
                @error('role')
                    <div class="invalid-feedback">{{ $message }}</div>
                @enderror
            </div>

            <div class="col-md-6">
                <label for="supervisor_id" class="form-label">Supervisor (Optional)</label>
                <select class="form-select @error('supervisor_id') is-invalid @enderror"
                        id="supervisor_id"
                        name="supervisor_id">
                    <option value="">No supervisor</option>
                    @foreach($supervisors as $supervisor)
                        <option value="{{ $supervisor->id }}" {{ (string) old('supervisor_id', $user->supervisor_id) === (string) $supervisor->id ? 'selected' : '' }}>
                            {{ $supervisor->name }} ({{ ucfirst($supervisor->role) }})
                        </option>
                    @endforeach
                </select>
                @error('supervisor_id')
                    <div class="invalid-feedback">{{ $message }}</div>
                @enderror
            </div>
        </div>

        <div class="row g-3">
            <div class="col-md-6">
                <label class="form-label">Projects</label>
                <x-token-picker
                    picker-id="user-projects"
                    name="project_ids[]"
                    :items="$projects"
                    :selected-ids="$selectedProjectIds"
                    placeholder="Search projects..."
                    :height="'260px'"
                />
                <div class="form-text">Select the projects this user can work within.</div>
                @error('project_ids')
                    <div class="text-danger small mt-1">{{ $message }}</div>
                @enderror
            </div>

            <div class="col-md-6">
                <label class="form-label">Programs</label>
                <x-token-picker
                    picker-id="user-programs"
                    name="program_ids[]"
                    :options="$programOptions"
                    :selected-ids="$selectedProgramIds"
                    placeholder="Search programs..."
                    disabled-placeholder="Select at least one project first..."
                    :disabled="empty($selectedProjectIds)"
                    :height="'260px'"
                />
                <div class="form-text">Programs are limited to the selected projects.</div>
                @error('program_ids')
                    <div class="text-danger small mt-1">{{ $message }}</div>
                @enderror
            </div>
        </div>
    </div>
</div>

@once
<script>
(function () {
    function parseJson(value, fallback) {
        try {
            return JSON.parse(value || '');
        } catch (error) {
            return fallback;
        }
    }

    function selectedIds(picker) {
        return Array.from(picker.querySelectorAll('[data-token-inputs] input')).map(function (input) {
            return String(input.value);
        });
    }

    function initializeUserScopeSection(section) {
        if (section.dataset.userScopeInitialized === 'true') {
            return;
        }

        const projectPicker = section.querySelector('#user-projects');
        const programPicker = section.querySelector('#user-programs');

        if (!projectPicker || !programPicker) {
            return;
        }

        const projectProgramMap = parseJson(section.dataset.projectProgramMap, {});

        function refreshProgramPicker() {
            const projectIds = selectedIds(projectPicker);
            const allowedProgramIds = [];

            projectIds.forEach(function (projectId) {
                const programIds = Array.isArray(projectProgramMap[projectId]) ? projectProgramMap[projectId] : [];
                programIds.forEach(function (programId) {
                    if (!allowedProgramIds.includes(String(programId))) {
                        allowedProgramIds.push(String(programId));
                    }
                });
            });

            programPicker.dispatchEvent(new CustomEvent('token-picker:set-disabled', {
                detail: {
                    disabled: projectIds.length === 0,
                    placeholder: 'Select at least one project first...',
                },
                bubbles: true,
            }));

            programPicker.dispatchEvent(new CustomEvent('token-picker:restrict', {
                detail: allowedProgramIds,
                bubbles: true,
            }));
        }

        projectPicker.addEventListener('token-picker:change', refreshProgramPicker);
        refreshProgramPicker();
        section.dataset.userScopeInitialized = 'true';
    }

    document.addEventListener('DOMContentLoaded', function () {
        document.querySelectorAll('[data-user-scope-section]').forEach(function (section) {
            initializeUserScopeSection(section);
        });
    });

    document.body.addEventListener('htmx:afterSwap', function (event) {
        event.target.querySelectorAll('[data-user-scope-section]').forEach(function (section) {
            initializeUserScopeSection(section);
        });
    });
})();
</script>
@endonce
