@php
    $isEditMode = isset($activityType);
    $selectedProjectIds = old('project_ids', $isEditMode ? $activityType->projects->pluck('id')->toArray() : []);
    $selectedProgramIds = old('program_ids', $isEditMode ? $activityType->programs->pluck('id')->toArray() : []);
    $selectedActivityTypeLoggingFieldIds = old('activity_type_logging_field_ids', $isEditMode ? $activityType->activityTypeLoggingFields->pluck('id')->toArray() : []);
    $requiredActivityTypeLoggingFieldIds = old(
        'required_activity_type_logging_field_ids',
        $isEditMode ? $activityType->activityTypeLoggingFields->filter(fn ($field) => $field->pivot->is_required)->pluck('id')->toArray() : []
    );
    $scopeId = $isEditMode ? 'activity-type-edit-scope' : 'activity-type-create-scope';

    $durationUnit = old('duration_unit');
    $durationValue = old('duration_value');

    if ($durationUnit === null && $isEditMode) {
        if ($activityType->duration_days > 0) {
            $durationUnit = 'days';
            $durationValue = $activityType->duration_days;
        } elseif ($activityType->duration_hours > 0) {
            $durationUnit = 'hours';
            $durationValue = $activityType->duration_hours;
        } else {
            $durationUnit = 'none';
            $durationValue = null;
        }
    }

    $durationUnit ??= 'none';
@endphp

<x-section-card title="Activity Type Details" subtitle="Define the activity type, scope, and reporting duration." class="mb-4">
    <div class="mb-3">
        <label for="contact_family_id" class="form-label">Activity Family <span class="text-danger">*</span></label>
        <select class="form-select @error('contact_family_id') is-invalid @enderror"
                id="contact_family_id"
                name="contact_family_id"
                required>
            <option value="">Select activity family...</option>
            @foreach($contactFamilies as $family)
                <option value="{{ $family->id }}" {{ old('contact_family_id', $activityType->contact_family_id ?? null) == $family->id ? 'selected' : '' }}>
                    {{ $family->name }}
                </option>
            @endforeach
        </select>
        @error('contact_family_id')
            <div class="invalid-feedback">{{ $message }}</div>
        @enderror
    </div>

    <div class="mb-3">
        <label for="name" class="form-label">Name <span class="text-danger">*</span></label>
        <input type="text"
               class="form-control @error('name') is-invalid @enderror"
               id="name"
               name="name"
               value="{{ old('name', $activityType->name ?? '') }}"
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
                  rows="3">{{ old('helper_text', $activityType->helper_text ?? '') }}</textarea>
        @error('helper_text')
            <div class="invalid-feedback">{{ $message }}</div>
        @enderror
        <div class="form-text">Optional text shown beneath this activity type when selected in activity logging.</div>
    </div>

    <div class="mb-3">
        <x-project-program-scope-picker
            :scope-id="$scopeId"
            :projects="$projects"
            :selected-project-ids="$selectedProjectIds"
            :selected-program-ids="$selectedProgramIds"
            :show-scope-mode-selector="true"
            :selected-scope-mode="old('program_scope_mode', $activityType->program_scope_mode?->value ?? 'all')"
            project-empty-selection-label="All projects"
            program-empty-selection-label="All programs"
            project-help-text="Optional filter for finding programs; projects are inferred and not saved."
            program-help-text="Programs are the saved scope when Specific is selected."
            scope-mode-help-text="Choose whether this activity type applies to all programs, only specific programs, or no programs."
        />
    </div>

    <div class="mb-3">
        <label for="duration_value" class="form-label">Duration</label>
        <div class="input-group" style="max-width: 20rem;">
            <input type="number"
                   class="form-control @error('duration_value') is-invalid @enderror"
                   id="duration_value"
                   name="duration_value"
                   value="{{ $durationValue }}"
                   min="0"
                   step="0.5"
                   placeholder="0"
                   @if($durationUnit === 'none') disabled @endif>
            <select class="form-select @error('duration_unit') is-invalid @enderror"
                    id="duration_unit"
                    name="duration_unit"
                    data-duration-unit-select
                    style="max-width: 9rem;">
                <option value="none" {{ $durationUnit === 'none' ? 'selected' : '' }}>No duration</option>
                <option value="days" {{ $durationUnit === 'days' ? 'selected' : '' }}>Days</option>
                <option value="hours" {{ $durationUnit === 'hours' ? 'selected' : '' }}>Hours</option>
            </select>
        </div>
        @error('duration_unit')
            <div class="text-danger small mt-1">{{ $message }}</div>
        @enderror
        @error('duration_value')
            <div class="text-danger small mt-1">{{ $message }}</div>
        @enderror
        <div class="form-text">Optional duration for reporting. Specify either days or hours, not both.</div>
    </div>
</x-section-card>

<x-section-card title="Activity Logging Fields" subtitle="These fields appear in the activity log when this activity type is selected." class="mb-4">
    <div class="d-flex justify-content-end mb-3">
        <a href="{{ route('logging-fields.index') }}" class="btn btn-sm btn-outline-secondary">Manage Logging Fields</a>
    </div>

    @if($activityTypeLoggingFields->isEmpty())
        <div class="alert alert-light border mb-0">No activity logging fields have been defined yet.</div>
    @else
        <x-logging-field-assignment-picker
            :fields="$activityTypeLoggingFields"
            :selected-field-ids="$selectedActivityTypeLoggingFieldIds"
            :required-field-ids="$requiredActivityTypeLoggingFieldIds"
            field-id-input-name="activity_type_logging_field_ids"
            required-input-name="required_activity_type_logging_field_ids"
            picker-id="activity-type-logging-field-picker"
        />
    @endif
</x-section-card>

@once
<script>
(function () {
    function refreshDurationField() {
        const select = document.getElementById('duration_unit');
        const input = document.getElementById('duration_value');

        if (!select || !input) {
            return;
        }

        input.disabled = select.value === 'none';
    }

    const durationUnitSelect = document.querySelector('[data-duration-unit-select]');
    if (durationUnitSelect) {
        durationUnitSelect.addEventListener('change', refreshDurationField);
        refreshDurationField();
    }
})();
</script>
@endonce
