<div class="mb-3">
    <label for="name" class="form-label">Field Name <span class="text-danger">*</span></label>
    <input type="text"
           class="form-control @error('name') is-invalid @enderror"
           id="name"
           name="name"
           value="{{ old('name', $contactFamilyLoggingField->name ?? '') }}"
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
            <option value="{{ $key }}" {{ old('field_type', $contactFamilyLoggingField->field_type ?? '') === $key ? 'selected' : '' }}>{{ $label }}</option>
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
              placeholder='["Option 1", "Option 2"]'>{{ old('options_json', isset($contactFamilyLoggingField) && $contactFamilyLoggingField->field_type === 'select' && $contactFamilyLoggingField->options_json ? json_encode($contactFamilyLoggingField->options_json) : '') }}</textarea>
    @error('options_json')
        <div class="invalid-feedback">{{ $message }}</div>
    @enderror
</div>

<div class="mb-3">
    <label for="help_text" class="form-label">Help Text</label>
    <textarea class="form-control @error('help_text') is-invalid @enderror" id="help_text" name="help_text" rows="2">{{ old('help_text', $contactFamilyLoggingField->help_text ?? '') }}</textarea>
    @error('help_text')
        <div class="invalid-feedback">{{ $message }}</div>
    @enderror
</div>

<div class="form-check mb-3">
    <input class="form-check-input" type="checkbox" id="is_active" name="is_active" value="1" {{ old('is_active', $contactFamilyLoggingField->is_active ?? true) ? 'checked' : '' }}>
    <label class="form-check-label" for="is_active">Active</label>
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
