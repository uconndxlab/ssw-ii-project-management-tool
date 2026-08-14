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

<x-section-card title="Information">
    <x-form-field label="Name" for="name" name="name" :required="true">
        <input type="text"
               class="form-control @error('name') is-invalid @enderror"
               id="name"
               name="name"
               value="{{ old('name', $organization?->name ?? '') }}"
               required>
    </x-form-field>

    <x-form-field label="PO Number" for="po_number" name="po_number" help="Exactly 6 digits.">
        <input type="text"
               class="form-control @error('po_number') is-invalid @enderror"
               id="po_number"
               name="po_number"
               value="{{ old('po_number', $organization?->po_number ?? '') }}"
               maxlength="6"
               pattern="[0-9]{6}"
               autocomplete="off"
               placeholder="e.g. 423232">
    </x-form-field>

    <x-form-field label="States" name="state_ids" :required="true">
        <x-token-picker
            picker-id="organization-states"
            name="state_ids[]"
            :items="$states"
            :selected-ids="old('state_ids', $organization?->states?->pluck('id')->toArray() ?? [])"
            placeholder="Search states..."
            :height="'220px'"
            entity="state"
        />
    </x-form-field>

    <x-form-field label="Associated Users" name="user_ids">
        <x-token-picker
            picker-id="organization-users"
            name="user_ids[]"
            :options="$userOptions"
            :selected-ids="old('user_ids', $organization?->users?->pluck('id')->toArray() ?? [])"
            label-key="label"
            value-key="value"
            search-key="search"
            placeholder="Search to add users..."
            :height="'220px'"
            entity="user"
        />
    </x-form-field>

    <x-project-program-scope-picker
        scope-id="organization-scope"
        :projects="$projects"
        :selected-project-ids="old('project_ids', $organization?->projects?->pluck('id')->toArray() ?? [])"
        :selected-program-ids="old('program_ids', $organization?->programs?->pluck('id')->toArray() ?? [])"
        :show-scope-mode-selector="true"
        :selected-scope-mode="old('program_scope_mode', $organization?->program_scope_mode?->value ?? 'specific')"
        project-empty-selection-label=""
        program-empty-selection-label=""
    />

    <x-form-options class="mt-4">
        <x-form-switch
            name="active"
            label="Active"
            help="Only active organizations appear on agreement and activity forms."
            :checked="old('active', $organization?->active ?? true)"
            class="mb-0"
        />
    </x-form-options>
</x-section-card>
