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

<x-section-card title="Activity Family Details" subtitle="Define the activity family and where it should be available." class="mb-4">
    <div class="mb-3">
        <label for="name" class="form-label">Name <span class="text-danger">*</span></label>
        <input type="text"
               class="form-control @error('name') is-invalid @enderror"
               id="name"
               name="name"
               value="{{ old('name', $contactFamily->name ?? '') }}"
               required>
        @error('name')
            <div class="invalid-feedback">{{ $message }}</div>
        @enderror
    </div>

    <div class="mb-3">
        <label for="helper_text" class="form-label">Activity Form Helper Text</label>
        <textarea class="form-control @error('helper_text') is-invalid @enderror"
                  id="helper_text"
                  name="helper_text"
                  rows="3">{{ old('helper_text', $contactFamily->helper_text ?? '') }}</textarea>
        @error('helper_text')
            <div class="invalid-feedback">{{ $message }}</div>
        @enderror
        <div class="form-text">Optional text shown beneath this activity family when selected in activity logging.</div>
    </div>

    <div class="mb-4">
        <x-project-program-scope-picker
            :scope-id="$scopeId"
            :projects="$projects"
            :selected-project-ids="$selectedProjectIds"
            :selected-program-ids="$selectedProgramIds"
            :show-scope-mode-selector="true"
            :selected-scope-mode="old('program_scope_mode', $contactFamily->program_scope_mode?->value ?? 'all')"
            project-empty-selection-label="All projects"
            program-empty-selection-label="All programs"
            project-help-text="Optional filter for finding programs; projects are inferred and not saved."
            program-help-text="Programs are the saved scope when Specific is selected."
            scope-mode-help-text="Choose whether this activity family applies to all programs, only specific programs, or no programs."
        />
    </div>

    <div class="row">
        <div class="col-6">
            <div class="border rounded px-3 py-3 h-100">
                <div class="d-flex align-items-center gap-3">
                    <div class="flex-grow-1 min-w-0">
                        <label class="form-label mb-1" for="track_additional_time">Track activity preparation and follow up time</label>
                        <p class="form-text mb-0">Require this activity family to capture preparation and follow up time in activity logging.</p>
                    </div>
                    <div class="form-check form-switch m-0 ps-0 flex-shrink-0 align-self-center">
                        <input type="checkbox"
                               class="form-check-input ms-0 @error('track_additional_time') is-invalid @enderror"
                               role="switch"
                               id="track_additional_time"
                               name="track_additional_time"
                               value="1"
                               {{ old('track_additional_time', $contactFamily->track_additional_time ?? false) ? 'checked' : '' }}>
                    </div>
                </div>
                @error('track_additional_time')
                    <div class="text-danger small mt-2">{{ $message }}</div>
                @enderror
            </div>
        </div>
    </div>
</x-section-card>

<x-section-card title="Classification Logging Fields" subtitle="These fields appear in the activity classification area for this activity family." class="mb-4">
    <div class="d-flex justify-content-end mb-3">
        <a href="{{ route('logging-fields.index') }}" class="btn btn-sm btn-outline-secondary">Manage Logging Fields</a>
    </div>

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
