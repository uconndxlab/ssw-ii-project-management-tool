@php
    $isEdit = $user->exists;
    $selectedProjectIds = old('project_ids', $user->projects?->pluck('id')->toArray() ?? []);
    $selectedProgramIds = old('program_ids', $user->programs?->pluck('id')->toArray() ?? []);
    $isActiveDefault = old('active', $user->exists ? $user->active : true);
@endphp

<div class="row g-4 mb-4 align-items-start">
    <div class="col-lg-8">
        <x-section-card title="User Information" subtitle="Account credentials and identity." class="mb-4">
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
        </x-section-card>

        <x-section-card title="Scope Assignments" subtitle="Projects and programs this user can work within.">
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

            <x-project-program-scope-picker
                scope-id="user-scope"
                :projects="$projects"
                :selected-project-ids="$selectedProjectIds"
                :selected-program-ids="$selectedProgramIds"
                project-help-text="Select the projects this user can work within."
                program-help-text="Programs are limited to the selected projects."
            />
        </x-section-card>
    </div>

    <div class="col-lg-4">
        <x-section-card title="Options" subtitle="Status and behavior settings for this user." class="h-100">
            <div class="d-grid gap-4">
                <div>
                    <div class="form-check form-switch">
                        <input type="checkbox"
                               class="form-check-input @error('active') is-invalid @enderror"
                               id="active"
                               name="active"
                               value="1"
                               {{ filter_var($isActiveDefault, FILTER_VALIDATE_BOOLEAN) ? 'checked' : '' }}>
                        <label class="form-check-label" for="active">
                            Active
                        </label>
                    </div>
                    <div class="form-text">Inactive users cannot log in and are removed from teams, agreements, and assignment pickers. Activity history and contributions are kept.</div>
                    @error('active')
                        <div class="text-danger small mt-1">{{ $message }}</div>
                    @enderror
                </div>
            </div>
        </x-section-card>
    </div>
</div>
