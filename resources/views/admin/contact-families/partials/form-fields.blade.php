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

<div class="row mb-3">
    <div class="col-md-6">
    <div class="form-check form-switch">
        <input type="checkbox"
               class="form-check-input"
               id="active"
               name="active"
               value="1"
               {{ old('active', $contactFamily->active ?? true) ? 'checked' : '' }}>
        <label class="form-check-label" for="active">
            Active
        </label>
    </div>
    <div class="form-text">Only active contact families appear in activity forms.</div>
</div>
    <div class="col-md-6">
    <div class="form-check form-switch">
        <input type="checkbox"
               class="form-check-input"
               id="track_additional_time"
               name="track_additional_time"
               value="1"
               {{ old('track_additional_time', $contactFamily->track_additional_time ?? false) ? 'checked' : '' }}>
        <label class="form-check-label" for="track_additional_time">
            Track activity preparation and follow up time
        </label>
    </div>
    <div class="form-text">Require this contact family to capture preparation and follow up time in activity logging.</div>
</div>
</div>

<div class="mb-4">
    <x-project-program-scope-picker
        :scope-id="$scopeId"
        :projects="$projects"
        :selected-project-ids="$selectedProjectIds"
        :selected-program-ids="$selectedProgramIds"
        project-help-text="Optional project scope for reporting and agreement-form filtering."
        program-help-text="Leave projects and programs empty to make this contact family available everywhere."
    />
</div>

<div class="mb-4">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <div>
            <h5 class="mb-1">Classification Logging Fields</h5>
            <p class="text-muted small mb-0">These fields appear in the activity classification area for this contact family.</p>
        </div>
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
</div>

@once
<script>
(function () {
    function selectedIdsFromPicker(picker) {
        if (!picker) {
            return [];
        }

        return Array.from(picker.querySelectorAll('[data-token-inputs] input')).map(function (input) {
            return String(input.value);
        });
    }

    function refreshScopedLoggingFields(programPicker) {
        const selectedPrograms = new Set(selectedIdsFromPicker(programPicker));

        document.querySelectorAll('[data-scoped-logging-field-option]').forEach(function (option) {
            const programIds = JSON.parse(option.dataset.programIds || '[]').map(String);
            const isGlobal = option.dataset.global === 'true';
            const visible = isGlobal || (selectedPrograms.size > 0 && programIds.some(function (programId) {
                return selectedPrograms.has(programId);
            }));

            option.classList.toggle('d-none', !visible);
            option.querySelectorAll('input').forEach(function (input) {
                if (!visible) {
                    input.checked = false;
                }
                input.disabled = !visible;
            });
        });
    }

    document.addEventListener('DOMContentLoaded', function () {
        const programPicker = document.getElementById('{{ $scopeId }}-programs');

        if (!programPicker) {
            return;
        }

        programPicker.addEventListener('token-picker:change', function () {
            refreshScopedLoggingFields(programPicker);
        });

        refreshScopedLoggingFields(programPicker);
    });
})();
</script>
@endonce
