@php
    $contactFamily = $contactFamily ?? null;
    $isEditMode = (bool) $contactFamily;
    $scopeId = $isEditMode ? 'contact-family-edit-scope' : 'contact-family-create-scope';
    $selectedProjectIds = old('project_ids', $isEditMode ? $contactFamily->projects->pluck('id')->toArray() : []);
    $selectedProgramIds = old('program_ids', $isEditMode ? $contactFamily->programs->pluck('id')->toArray() : []);
    $selectedContactFamilyLoggingFieldIds = old('contact_family_logging_field_ids', $isEditMode ? $contactFamily->contactFamilyLoggingFields->pluck('id')->toArray() : []);
    $requiredContactFamilyLoggingFieldIds = old(
        'required_contact_family_logging_field_ids',
        $isEditMode ? $contactFamily->contactFamilyLoggingFields->filter(fn ($field) => $field->pivot->is_required)->pluck('id')->toArray() : []
    );
@endphp

<x-section-card title="Information">
    <x-form-field label="Name" for="name" name="name" :required="true">
        <input type="text"
               class="form-control @error('name') is-invalid @enderror"
               id="name"
               name="name"
               value="{{ old('name', $contactFamily->name ?? '') }}"
               required>
    </x-form-field>

    <x-form-field label="Helper Text" for="helper_text" name="helper_text" help="Shown under this family on the activity form.">
        <textarea class="form-control @error('helper_text') is-invalid @enderror"
                  id="helper_text"
                  name="helper_text"
                  rows="3"
                  maxlength="1000">{{ old('helper_text', $contactFamily->helper_text ?? '') }}</textarea>
    </x-form-field>

    <x-project-program-scope-picker
        :scope-id="$scopeId"
        :projects="$projects"
        :selected-project-ids="$selectedProjectIds"
        :selected-program-ids="$selectedProgramIds"
        :show-scope-mode-selector="true"
        :selected-scope-mode="old('program_scope_mode', $contactFamily->program_scope_mode?->value ?? ($contactFamily->exists ? 'all' : 'specific'))"
        :lock-all="$contactFamily->exists && $contactFamily->program_scope_mode?->value === 'all'"
        project-empty-selection-label="All projects"
        program-empty-selection-label="All programs"
    />

    <x-form-options class="mt-4">
        <x-form-switch
            name="track_additional_time"
            label="Track prep and follow up time"
            help="Require preparation and follow up time when logging this family."
            :checked="old('track_additional_time', $contactFamily->track_additional_time ?? false)"
        />

        <x-form-switch
            name="active"
            label="Active"
            help="Only active activity families appear in activity forms."
            :checked="old('active', $contactFamily->active ?? true)"
            class="mb-0"
        />
    </x-form-options>
</x-section-card>

<x-section-card title="Logging Fields">
    <x-slot:actions>
        <a href="{{ route('logging-fields.index') }}" class="btn btn-sm btn-outline-secondary">Manage Logging Fields</a>
    </x-slot:actions>

    @if($contactFamilyLoggingFields->isEmpty())
        <div class="alert alert-light border mb-0">No activity family logging fields have been defined yet.</div>
    @else
        <x-logging-field-assignment-picker
            :fields="$contactFamilyLoggingFields"
            :selected-field-ids="$selectedContactFamilyLoggingFieldIds"
            :required-field-ids="$requiredContactFamilyLoggingFieldIds"
            field-id-input-name="contact_family_logging_field_ids"
            required-input-name="required_contact_family_logging_field_ids"
            picker-id="contact-family-logging-field-picker"
        />
    @endif
</x-section-card>
