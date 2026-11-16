@php
    $isEditMode = isset($subActivity);
    $selectedProjectIds = old('project_ids', $isEditMode ? $subActivity->projects->pluck('id')->toArray() : []);
    $selectedProgramIds = old('program_ids', $isEditMode ? $subActivity->programs->pluck('id')->toArray() : []);
    $selectedSubActivityLoggingFieldIds = old('sub_activity_logging_field_ids', $isEditMode ? $subActivity->subActivityLoggingFields->pluck('id')->toArray() : []);
    $requiredSubActivityLoggingFieldIds = old(
        'required_sub_activity_logging_field_ids',
        $isEditMode ? $subActivity->subActivityLoggingFields->filter(fn ($field) => $field->pivot->is_required)->pluck('id')->toArray() : []
    );
    $scopeId = $isEditMode ? 'sub-activity-edit-scope' : 'sub-activity-create-scope';

    $durationUnit = old('duration_unit');
    $durationValue = old('duration_value');

    if ($durationUnit === null && $isEditMode) {
        if ($subActivity->duration_days > 0) {
            $durationUnit = 'days';
            $durationValue = $subActivity->duration_days;
        } elseif ($subActivity->duration_hours > 0) {
            $durationUnit = 'hours';
            $durationValue = $subActivity->duration_hours;
        } else {
            $durationUnit = 'none';
            $durationValue = null;
        }
    }

    $durationUnit ??= 'none';
@endphp

<x-section-card title="Information">
    <x-form-field label="Activity Type" for="activity_type_id" name="activity_type_id" :required="true">
        <select class="form-select @error('activity_type_id') is-invalid @enderror"
                id="activity_type_id"
                name="activity_type_id"
                required>
            <option value="">Select activity type...</option>
            @foreach($activityTypes->groupBy('contactFamily.name') as $familyName => $types)
                <optgroup label="{{ $familyName }}">
                    @foreach($types as $type)
                        <option value="{{ $type->id }}" data-duration-label="{{ \App\Support\ActivityTypeDuration::fromActivityType($type)->formatLabel() }}" data-duration-hours="{{ $type->duration_hours }}" data-duration-days="{{ $type->duration_days }}" {{ old('activity_type_id', $subActivity->activity_type_id ?? null) == $type->id ? 'selected' : '' }}>{{ $type->name }}</option>
                    @endforeach
                </optgroup>
            @endforeach
        </select>
    </x-form-field>

    <x-form-field label="Name" for="name" name="name" :required="true">
        <input type="text"
               class="form-control @error('name') is-invalid @enderror"
               id="name"
               name="name"
               value="{{ old('name', $subActivity->name ?? '') }}"
               required>
    </x-form-field>

    <x-form-field label="Helper Text" for="helper_text" name="helper_text" help="Shown under this sub-activity on the activity form.">
        <textarea class="form-control @error('helper_text') is-invalid @enderror"
                  id="helper_text"
                  name="helper_text"
                  rows="3"
                  maxlength="1000">{{ old('helper_text', $subActivity->helper_text ?? '') }}</textarea>
    </x-form-field>

    <x-form-field label="Duration" for="duration_value" name="duration_value">
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
        <div id="duration-source" class="form-text" aria-live="polite">No duration inherits the activity type’s duration. A duration set here overrides the type’s.</div>
        @error('duration_unit')
            <div class="text-danger small mt-1">{{ $message }}</div>
        @enderror
    </x-form-field>

    <x-project-program-scope-picker
        :scope-id="$scopeId"
        :projects="$projects"
        :selected-project-ids="$selectedProjectIds"
        :selected-program-ids="$selectedProgramIds"
        :show-scope-mode-selector="true"
        :selected-scope-mode="old('program_scope_mode', $subActivity->program_scope_mode?->value ?? ($isEditMode ? 'all' : 'specific'))"
        :lock-all="$isEditMode && $subActivity->program_scope_mode?->value === 'all'"
        project-empty-selection-label="All projects"
        program-empty-selection-label="All programs"
    />

    <x-form-options class="mt-4">
        <x-form-switch
            name="active"
            label="Active"
            help="Only active sub-activities appear in activity forms."
            :checked="old('active', $isEditMode ? $subActivity->active : true)"
            class="mb-0"
        />
    </x-form-options>
</x-section-card>

<x-section-card title="Logging Fields">
    <x-slot:actions>
        <a href="{{ route('logging-fields.index') }}" class="btn btn-sm btn-outline-secondary">Manage Logging Fields</a>
    </x-slot:actions>

    @if($subActivityLoggingFields->isEmpty())
        <div class="alert alert-light border mb-0">No sub-activity logging fields have been defined yet.</div>
    @else
        <x-logging-field-assignment-picker
            :fields="$subActivityLoggingFields"
            :selected-field-ids="$selectedSubActivityLoggingFieldIds"
            :required-field-ids="$requiredSubActivityLoggingFieldIds"
            field-id-input-name="sub_activity_logging_field_ids"
            required-input-name="required_sub_activity_logging_field_ids"
            picker-id="sub-activity-logging-field-picker"
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
        const type = document.getElementById('activity_type_id').selectedOptions[0];
        const label = type?.dataset.durationLabel;
        document.getElementById('duration-source').textContent = label
            ? (select.value === 'none' ? 'No duration here: uses ' : 'Overrides ') + type.textContent.trim() + "'s " + label + '.'
            : 'No duration inherits the activity type’s duration. A duration set here overrides the type’s.';
    }

    document.getElementById('activity_type_id').addEventListener('change', refreshDurationField);
    const durationUnitSelect = document.querySelector('[data-duration-unit-select]');
    if (durationUnitSelect) {
        durationUnitSelect.addEventListener('change', refreshDurationField);
        refreshDurationField();
    }
})();
</script>
@endonce
