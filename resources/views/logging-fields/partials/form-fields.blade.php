@php
    $field = $agreementLoggingField ?? $contactFamilyLoggingField ?? $loggingField ?? null;
    $availabilityOptions = $availabilityOptions ?? \App\Models\LoggingField::availabilityOptions();
    $selectedFieldType = old('field_type', $field->field_type ?? '');

    if ($selectedFieldType === \App\Models\LoggingField::FIELD_TYPE_CHECKBOX_GROUP) {
        $selectedFieldType = \App\Models\LoggingField::FIELD_TYPE_MULTISELECT;
    }

    $selectedProjectIds = old('project_ids', $field?->projects?->pluck('id')->toArray() ?? []);
    $selectedProgramIds = old('program_ids', $field?->programs?->pluck('id')->toArray() ?? []);
    $scopeId = $field?->id ? 'logging-field-edit-scope' : 'logging-field-create-scope';
    $optionRows = collect(old('option_rows', []));

    if ($optionRows->isEmpty() && $field) {
        $optionRows = collect($field->normalizedOptions())
            ->map(function ($option, $index) {
                $rowKey = 'option-' . ($option['id'] ?? $index);

                return [
                    'row_key' => $rowKey,
                    'id' => $option['id'] ?? '',
                    'value' => $option['label'] ?? '',
                    '_delete' => '0',
                ];
            });
    }

    $optionRows = $optionRows
        ->map(function ($row, $index) {
            if (!is_array($row)) {
                return null;
            }

            return [
                'row_key' => (string) ($row['row_key'] ?? ('option-row-' . $index)),
                'id' => (string) ($row['id'] ?? ''),
                'value' => (string) ($row['value'] ?? $row['label'] ?? ''),
                '_delete' => !empty($row['_delete']) ? '1' : '0',
            ];
        })
        ->filter()
        ->values()
        ->all();
@endphp

<x-section-card title="Information">
    <x-form-field label="Name" for="name" name="name" :required="true">
        <input type="text"
               class="form-control @error('name') is-invalid @enderror"
               id="name"
               name="name"
               value="{{ old('name', $field->name ?? '') }}"
               required>
    </x-form-field>

    <x-form-field label="Type" for="field_type" name="field_type" :required="true">
        <select class="form-select @error('field_type') is-invalid @enderror" id="field_type" name="field_type" required>
            <option value="">Select field type…</option>
            @foreach($fieldTypes as $key => $label)
                <option value="{{ $key }}" {{ $selectedFieldType === $key ? 'selected' : '' }}>{{ $label }}</option>
            @endforeach
        </select>
    </x-form-field>

    <div class="mb-3 d-none" id="options-field-wrapper">
        <x-inline-string-list
            list-id="logging-field-options"
            name="option_rows"
            :rows="$optionRows"
            label="Options"
            :required="true"
            value-field="label"
            add-button-text="Add Option"
            empty-message="Add at least one option."
            input-placeholder="Enter an option label..."
        />
    </div>

    <x-form-field label="Help Text" for="help_text" name="help_text">
        <textarea class="form-control @error('help_text') is-invalid @enderror" id="help_text" name="help_text" rows="2" maxlength="1000">{{ old('help_text', $field->help_text ?? '') }}</textarea>
    </x-form-field>

    <x-form-options>
        <x-form-switch
            name="is_active"
            id="is_active"
            label="Active"
            :checked="old('is_active', $field->is_active ?? true)"
            class="mb-0"
        />
    </x-form-options>
</x-section-card>

<x-section-card title="Availability">
    <x-project-program-scope-picker
        :scope-id="$scopeId"
        :projects="$projects"
        :selected-project-ids="$selectedProjectIds"
        :selected-program-ids="$selectedProgramIds"
        :show-scope-mode-selector="true"
        :selected-scope-mode="old('program_scope_mode', $field->program_scope_mode?->value ?? ($field->exists ? 'all' : 'specific'))"
        :lock-all="$field->exists && $field->program_scope_mode?->value === 'all'"
        project-empty-selection-label="All projects"
        program-empty-selection-label="All programs"
    />

    <x-form-field label="Available In" class="mt-4">
        <div class="d-flex flex-wrap gap-3">
            @foreach($availabilityOptions as $key => $label)
                <div class="form-check">
                    <input class="form-check-input"
                           type="checkbox"
                           id="{{ $key }}"
                           name="{{ $key }}"
                           value="1"
                           {{ old($key, $field->{$key} ?? false) ? 'checked' : '' }}>
                    <label class="form-check-label" for="{{ $key }}">{{ $label }}</label>
                </div>
            @endforeach
        </div>
    </x-form-field>

    <x-form-options class="mt-4">
        <x-form-switch
            name="is_full_width"
            id="is_full_width"
            label="Full width"
            help="Use a full row when this field is shown on an activity form."
            :checked="old('is_full_width', $field->is_full_width ?? false)"
            class="mb-0"
        />
    </x-form-options>
</x-section-card>

<script>
(function () {
    const fieldType = document.getElementById('field_type');
    const wrapper = document.getElementById('options-field-wrapper');
    if (!fieldType || !wrapper) return;

    function sync() {
        const type = fieldType.value;
        const usesOptions = type === 'select' || type === 'multiselect';
        wrapper.classList.toggle('d-none', !usesOptions);
    }

    fieldType.addEventListener('change', sync);
    sync();
})();
</script>
