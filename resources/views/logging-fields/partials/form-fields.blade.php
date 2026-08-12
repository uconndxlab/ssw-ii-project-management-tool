@php
    $field = $agreementLoggingField ?? $contactFamilyLoggingField ?? $loggingField ?? null;
    $availabilityOptions = $availabilityOptions ?? \App\Models\LoggingField::availabilityOptions();
    $selectedProjectIds = old('project_ids', $field?->projects?->pluck('id')->toArray() ?? []);
    $selectedProgramIds = old('program_ids', $field?->programs?->pluck('id')->toArray() ?? []);
    $scopeId = $field?->id ? 'logging-field-edit-scope' : 'logging-field-create-scope';
@endphp

<div class="mb-3">
    <label for="name" class="form-label">Field Name <span class="text-danger">*</span></label>
    <input type="text"
           class="form-control @error('name') is-invalid @enderror"
           id="name"
           name="name"
           value="{{ old('name', $field->name ?? '') }}"
           required>
    @error('name')
        <div class="invalid-feedback">{{ $message }}</div>
    @enderror
</div>

<div class="mb-3">
    <label for="field_type" class="form-label">Field Type <span class="text-danger">*</span></label>
    <select class="form-select @error('field_type') is-invalid @enderror" id="field_type" name="field_type" required>
        <option value="">Select field type…</option>
        @foreach($fieldTypes as $key => $label)
            <option value="{{ $key }}" {{ old('field_type', $field->field_type ?? '') === $key ? 'selected' : '' }}>{{ $label }}</option>
        @endforeach
    </select>
    @error('field_type')
        <div class="invalid-feedback">{{ $message }}</div>
    @enderror
</div>

<div class="mb-3" id="options-field-wrapper">
    <label for="options_json" class="form-label">Dropdown Options</label>
    <textarea class="form-control @error('options_json') is-invalid @enderror"
              id="options_json"
              name="options_json"
              rows="4"
              placeholder='["Option 1", "Option 2"]'>{{ old('options_json', isset($field) && $field->field_type === 'select' && $field->options_json ? json_encode($field->options_json) : '') }}</textarea>
    @error('options_json')
        <div class="invalid-feedback">{{ $message }}</div>
    @enderror
</div>

<div class="mb-3">
    <label for="help_text" class="form-label">Help Text</label>
    <textarea class="form-control @error('help_text') is-invalid @enderror" id="help_text" name="help_text" rows="2">{{ old('help_text', $field->help_text ?? '') }}</textarea>
    @error('help_text')
        <div class="invalid-feedback">{{ $message }}</div>
    @enderror
</div>

<div class="mb-4">
    <x-project-program-scope-picker
        :scope-id="$scopeId"
        :projects="$projects"
        :selected-project-ids="$selectedProjectIds"
        :selected-program-ids="$selectedProgramIds"
        :show-scope-mode-selector="true"
        :selected-scope-mode="old('program_scope_mode', $field->program_scope_mode?->value ?? 'all')"
        project-empty-selection-label="All projects"
        program-empty-selection-label="All programs"
        project-help-text="Optional filter for finding programs; projects are inferred and not saved."
        program-help-text="Programs are the saved scope when Specific is selected."
        scope-mode-help-text="Choose whether this field applies to all programs, only specific programs, or no programs."
    />
</div>

<div class="mb-3">
    <label class="form-label d-block mb-2">Available In</label>
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
    <div class="form-text">Choose every area where this logging field should be available.</div>
</div>

<div class="row g-2 mb-3">
    <div class="col-12 col-sm-6 col-lg-4">
        <div class="border rounded px-3 py-2 h-100 d-flex align-items-center justify-content-between gap-3">
            <label class="form-label mb-0" for="is_active">Active</label>
            <div class="form-check form-switch m-0 ps-0">
                <input class="form-check-input ms-0"
                       type="checkbox"
                       role="switch"
                       id="is_active"
                       name="is_active"
                       value="1"
                       {{ old('is_active', $field->is_active ?? true) ? 'checked' : '' }}>
            </div>
        </div>
    </div>
    <div class="col-12 col-sm-6 col-lg-4">
        <div class="border rounded px-3 py-2 h-100 d-flex align-items-center justify-content-between gap-3">
            <label class="form-label mb-0" for="is_full_width">Full width</label>
            <div class="form-check form-switch m-0 ps-0">
                <input class="form-check-input ms-0"
                       type="checkbox"
                       role="switch"
                       id="is_full_width"
                       name="is_full_width"
                       value="1"
                       {{ old('is_full_width', $field->is_full_width ?? false) ? 'checked' : '' }}>
            </div>
        </div>
    </div>
</div>

<script>
(function () {
    const fieldType = document.getElementById('field_type');
    const wrapper = document.getElementById('options-field-wrapper');
    if (!fieldType || !wrapper) return;

    function sync() {
        wrapper.classList.toggle('d-none', fieldType.value !== 'select');
    }

    fieldType.addEventListener('change', sync);
    sync();
})();
</script>
