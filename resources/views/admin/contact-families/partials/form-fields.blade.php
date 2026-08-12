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

<x-section-card title="Contact Family Details" subtitle="Define the contact family and where it should be available." class="mb-4">
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

    <div class="mb-4">
        <x-project-program-scope-picker
            :scope-id="$scopeId"
            :projects="$projects"
            :selected-project-ids="$selectedProjectIds"
            :selected-program-ids="$selectedProgramIds"
            project-empty-selection-label="All projects"
            program-empty-selection-label="All programs"
            project-help-text="Optional filter for finding programs; projects are inferred and not saved."
            program-help-text="Programs are the saved scope. Leave both filters empty to make this family global; leaving programs empty after selecting projects saves all currently listed programs."
        />
    </div>

    <div class="row">
        <div class="col-6">
            <div class="border rounded px-3 py-3 h-100">
                <div class="d-flex align-items-center gap-3">
                    <div class="flex-grow-1 min-w-0">
                        <label class="form-label mb-1" for="track_additional_time">Track activity preparation and follow up time</label>
                        <p class="form-text mb-0">Require this contact family to capture preparation and follow up time in activity logging.</p>
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

<x-section-card title="Classification Logging Fields" subtitle="These fields appear in the activity classification area for this contact family." class="mb-4">
    <div class="d-flex justify-content-end mb-3">
        <a href="{{ route('logging-fields.index') }}" class="btn btn-sm btn-outline-secondary">Manage Logging Fields</a>
    </div>

    @if($contactFamilyLoggingFields->isEmpty())
        <div class="alert alert-light border mb-0">No contact family logging fields have been defined yet.</div>
    @else
        <div class="border rounded">
            @foreach($contactFamilyLoggingFields as $field)
                @php
                    $fieldProgramIds = $field->programs->pluck('id')->map(fn ($id) => (string) $id)->values()->all();
                @endphp
                <label class="d-flex align-items-start gap-3 px-3 py-2 border-bottom {{ $loop->last ? 'border-bottom-0' : '' }}"
                       data-scoped-logging-field-option
                       data-option-id="{{ $field->id }}"
                       data-program-ids='@json($fieldProgramIds)'
                       data-global="{{ empty($fieldProgramIds) ? 'true' : 'false' }}">
                    <input class="form-check-input mt-1"
                           type="checkbox"
                           name="contact_family_logging_field_ids[]"
                           value="{{ $field->id }}"
                           {{ in_array($field->id, $selectedContactFamilyLoggingFieldIds) ? 'checked' : '' }}>
                    <div class="flex-grow-1">
                        <div class="fw-semibold">{{ $field->name }}</div>
                        <div class="small text-muted">{{ ucfirst($field->field_type) }}{{ $field->help_text ? ' · ' . $field->help_text : '' }}</div>
                    </div>
                    <div class="form-check m-0">
                        <input class="form-check-input"
                               type="checkbox"
                               name="required_contact_family_logging_field_ids[]"
                               value="{{ $field->id }}"
                               {{ in_array($field->id, $requiredContactFamilyLoggingFieldIds) ? 'checked' : '' }}>
                        <label class="form-check-label small">Required</label>
                    </div>
                </label>
            @endforeach
        </div>
    @endif
</x-section-card>

@once
<script>
(function () {
    function refreshScopedLoggingFields(effectiveProgramIds) {
        if ((effectiveProgramIds || []).length === 0) {
            document.querySelectorAll('[data-scoped-logging-field-option]').forEach(function (option) {
                option.classList.remove('d-none');
                option.querySelectorAll('input').forEach(function (input) {
                    input.disabled = false;
                });
            });

            return;
        }

        const selectedPrograms = new Set((effectiveProgramIds || []).map(String));

        document.querySelectorAll('[data-scoped-logging-field-option]').forEach(function (option) {
            const programIds = JSON.parse(option.dataset.programIds || '[]').map(String);
            const isGlobal = option.dataset.global === 'true';
            const visible = isGlobal || programIds.some(function (programId) {
                return selectedPrograms.has(programId);
            });

            option.classList.toggle('d-none', !visible);
            option.querySelectorAll('input').forEach(function (input) {
                if (!visible) {
                    input.checked = false;
                }
                input.disabled = !visible;
            });
        });
    }

    document.addEventListener('project-program-scope:change', function (event) {
        refreshScopedLoggingFields(event.detail?.effectiveProgramIds || []);
    });
})();
</script>
@endonce
