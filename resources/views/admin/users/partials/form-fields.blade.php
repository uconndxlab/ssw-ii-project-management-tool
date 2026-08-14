@php
    $isEdit = $user->exists;
    $selectedProjectIds = old('project_ids', $user->projects?->pluck('id')->toArray() ?? []);
    $selectedProgramIds = old('program_ids', $user->programs?->pluck('id')->toArray() ?? []);
@endphp

<x-section-card title="Information">
    <x-form-field label="Name" for="name" name="name" :required="true">
        <input type="text"
               class="form-control @error('name') is-invalid @enderror"
               id="name"
               name="name"
               value="{{ old('name', $user->name) }}"
               required>
    </x-form-field>

    <x-form-field label="Email" for="email" name="email" :required="true">
        <input type="email"
               class="form-control @error('email') is-invalid @enderror"
               id="email"
               name="email"
               value="{{ old('email', $user->email) }}"
               required>
    </x-form-field>

    <x-form-field
        label="Password"
        for="password"
        name="password"
        :required="! $isEdit"
        :help="$isEdit ? 'Leave blank to keep the current password.' : null"
    >
        <input type="password"
               class="form-control @error('password') is-invalid @enderror"
               id="password"
               name="password"
               {{ $isEdit ? '' : 'required' }}>
    </x-form-field>

    <x-form-field label="PO Number" for="po_number" name="po_number" help="Exactly 6 digits.">
        <input type="text"
               class="form-control @error('po_number') is-invalid @enderror"
               id="po_number"
               name="po_number"
               value="{{ old('po_number', $user->po_number ?? '') }}"
               maxlength="6"
               pattern="[0-9]{6}"
               autocomplete="off"
               placeholder="e.g. 423232">
    </x-form-field>

    <x-form-options>
        <x-form-switch
            name="active"
            label="Active"
            help="Inactive users cannot log in and are removed from teams, agreements, and assignment pickers. Activity history is kept."
            :checked="old('active', $user->exists ? $user->active : true)"
            class="mb-0"
        />
    </x-form-options>
</x-section-card>

<x-section-card title="Role">
    <div class="row g-3">
        <div class="col-md-6">
            <x-form-field label="Role" for="role" name="role" :required="true" class="mb-0">
                <select class="form-select @error('role') is-invalid @enderror"
                        id="role"
                        name="role"
                        required>
                    <option value="">Select role...</option>
                    <option value="admin" {{ old('role', $user->role) === 'admin' ? 'selected' : '' }}>Admin</option>
                    <option value="staff" {{ old('role', $user->role) === 'staff' ? 'selected' : '' }}>Staff</option>
                    <option value="consultant" {{ old('role', $user->role) === 'consultant' ? 'selected' : '' }}>Consultant</option>
                </select>
            </x-form-field>
        </div>

        <div class="col-md-6">
            <x-form-field label="Supervisor" for="supervisor_id" name="supervisor_id" class="mb-0">
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
            </x-form-field>
        </div>
    </div>
</x-section-card>

<x-section-card title="Scope">
    <x-project-program-scope-picker
        scope-id="user-scope"
        :projects="$projects"
        :selected-project-ids="$selectedProjectIds"
        :selected-program-ids="$selectedProgramIds"
        :show-scope-mode-selector="true"
        :selected-scope-mode="old('program_scope_mode', $user->program_scope_mode?->value ?? 'specific')"
    />
</x-section-card>
