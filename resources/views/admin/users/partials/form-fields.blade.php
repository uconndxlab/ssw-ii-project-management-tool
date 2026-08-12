@php
    $isEdit = $user->exists;
    $selectedProjectIds = old('project_ids', $user->projects?->pluck('id')->toArray() ?? []);
    $selectedProgramIds = old('program_ids', $user->programs?->pluck('id')->toArray() ?? []);
@endphp

<div class="card mb-4">
    <div class="card-body">
        <h5 class="mb-3">User Information</h5>

        <div class="mb-3">
            <label for="name" class="form-label">Name <span class="text-danger">*</span></label>
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
            <label for="po_number" class="form-label">PO Number</label>
            <input type="text"
                   class="form-control @error('po_number') is-invalid @enderror"
                   id="po_number"
                   name="po_number"
                   value="{{ old('po_number', $user->po_number ?? '') }}"
                   maxlength="6"
                   pattern="[0-9]{6}"
                   autocomplete="off"
                     placeholder="e.g. 423232">
                 <small class="text-muted d-block mt-1">Optional. Must be exactly 6 digits.</small>
            @error('po_number')
                <div class="invalid-feedback">{{ $message }}</div>
            @enderror
        </div>

        <div class="mb-3">
            <label for="email" class="form-label">Email <span class="text-danger">*</span></label>
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

        <div class="mb-0">
            <label for="password" class="form-label">
                Password
                @if(!$isEdit)
                    <span class="text-danger">*</span>
                @else
                    <span class="text-muted fw-normal">(Optional)</span>
                @endif
            </label>
            <input type="password"
                   class="form-control @error('password') is-invalid @enderror"
                   id="password"
                   name="password"
                   {{ $isEdit ? '' : 'required' }}>
            @if($isEdit)
                <small class="text-muted d-block mt-1">Leave blank to keep the current password.</small>
            @endif
            @error('password')
                <div class="invalid-feedback">{{ $message }}</div>
            @enderror
        </div>
    </div>
</div>

<div class="card mb-4">
    <div class="card-body">
        <h5 class="mb-3">Scope Assignments</h5>

        <div class="row g-3 mb-3">
            <div class="col-md-6">
                <label for="role" class="form-label">Role <span class="text-danger">*</span></label>
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
            project-help-text="Use projects to filter the program list; project assignments are inferred and not saved."
            program-help-text="Programs are the saved user scope. Leaving programs empty saves all programs currently listed under the selected projects."
        />
    </div>
</div>
