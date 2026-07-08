@php
    $isEdit = $user->exists;
@endphp

<div class="mb-3">
    <label for="name" class="form-label">Name</label>
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
    <label for="email" class="form-label">Email</label>
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

<div class="mb-3">
    <label for="password" class="form-label">Password{{ $isEdit ? ' (Optional)' : '' }}</label>
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

<div class="mb-3">
    <label for="role" class="form-label">Role</label>
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

<div class="mb-3">
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