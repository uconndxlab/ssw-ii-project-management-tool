@php
    $organization = $organization ?? null;
@endphp

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

        <div class="mb-3">
            <label for="name" class="form-label required-label">Organization Name</label>
            <input type="text"
                   class="form-control @error('name') is-invalid @enderror"
                   id="name"
                   name="name"
                   value="{{ old('name', $organization?->name ?? '') }}"
                   required>
            @error('name')
                <div class="invalid-feedback">{{ $message }}</div>
            @enderror
        </div>

        <div class="mb-3">
            <label class="form-label required-label">State(s)</label>
            <x-token-picker
                picker-id="organization-states"
                name="state_ids[]"
                :items="$states"
                :selected-ids="old('state_ids', $organization?->states?->pluck('id')->toArray() ?? [])"
                placeholder="Search states..."
                :height="'300px'"
            />
            @error('state_ids')
                <div class="text-danger small mt-1">{{ $message }}</div>
            @enderror
        </div>

        <x-project-program-scope-picker
            scope-id="organization-scope"
            :projects="$projects"
            :selected-project-ids="old('project_ids', $organization?->projects?->pluck('id')->toArray() ?? [])"
            :selected-program-ids="old('program_ids', $organization?->programs?->pluck('id')->toArray() ?? [])"
            project-help-text="Select the projects this organization participates in."
            program-help-text="Programs are limited to the selected projects."
            program-badge-class="bg-primary"
        />
    </div>
</div>
