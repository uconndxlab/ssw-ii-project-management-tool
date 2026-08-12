@php
    $organization = $organization ?? null;
    $userOptions = collect($users ?? [])->map(function ($user) {
        $role = !empty($user->role) ? ' (' . ucfirst($user->role) . ')' : '';

        return [
            'value' => $user->id,
            'label' => $user->name . $role,
            'search' => trim($user->name . ' ' . ($user->email ?? '') . ' ' . ($user->role ?? '')),
        ];
    });
@endphp

<div class="row g-4 mb-4 align-items-start">
    <div class="col-lg-8">
        <x-section-card title="Organization Details" subtitle="Define the organization and where it should be available." class="h-100">
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
                <label for="po_number" class="form-label">PO Number</label>
                <input type="text"
                       class="form-control @error('po_number') is-invalid @enderror"
                       id="po_number"
                       name="po_number"
                       value="{{ old('po_number', $organization?->po_number ?? '') }}"
                       maxlength="6"
                       pattern="[0-9]{6}"
                       autocomplete="off"
                       placeholder="e.g. 423232">
                <div class="form-text">Optional. Must be exactly 6 digits.</div>
                @error('po_number')
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
                    entity="state"
                />
                @error('state_ids')
                    <div class="text-danger small mt-1">{{ $message }}</div>
                @enderror
            </div>

            <div class="mb-3">
                <label class="form-label">Associated Users</label>
                <x-token-picker
                    picker-id="organization-users"
                    name="user_ids[]"
                    :options="$userOptions"
                    :selected-ids="old('user_ids', $organization?->users?->pluck('id')->toArray() ?? [])"
                    label-key="label"
                    value-key="value"
                    search-key="search"
                    placeholder="Search to add users..."
                    :height="'300px'"
                    entity="user"
                />
                <div class="form-text">Select users associated with this organization.</div>
                @error('user_ids')
                    <div class="text-danger small mt-1">{{ $message }}</div>
                @enderror
            </div>

            <x-project-program-scope-picker
                scope-id="organization-scope"
                :projects="$projects"
                :selected-project-ids="old('project_ids', $organization?->projects?->pluck('id')->toArray() ?? [])"
                :selected-program-ids="old('program_ids', $organization?->programs?->pluck('id')->toArray() ?? [])"
                project-empty-selection-label="None"
                program-empty-selection-label="None"
                project-help-text="Use projects to filter the program list; project participation is inferred and not saved."
                program-help-text="Programs are the saved organization scope. Leave both filters empty for no program scope; otherwise leaving programs empty saves all currently listed programs."
            />
        </x-section-card>
    </div>

    <div class="col-lg-4">
        <x-section-card title="Options" subtitle="Status and behavior settings for this organization." class="h-100">
            <div class="d-grid gap-4">
                <div>
                    <div class="form-check form-switch">
                        <input type="checkbox"
                               class="form-check-input"
                               id="active"
                               name="active"
                               value="1"
                               {{ old('active', $organization?->active ?? true) ? 'checked' : '' }}>
                        <label class="form-check-label" for="active">
                            Active
                        </label>
                    </div>
                    <div class="form-text">Only active organizations appear when adding organizations on agreement and activity forms.</div>
                </div>
            </div>
        </x-section-card>
    </div>
</div>
