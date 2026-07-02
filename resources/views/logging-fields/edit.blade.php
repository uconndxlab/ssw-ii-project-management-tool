@extends('layouts.app')

@section('title', 'Edit Logging Field')

@section('content')
<div class="row justify-content-center mb-4">
    <div class="col-lg-8">
        <h1>Edit Activity Logging Field</h1>
        <p class="text-muted">Update the field definition for "{{ $loggingField->name }}".</p>
    </div>
</div>

<div class="row justify-content-center">
    <div class="col-lg-8">
        <div class="card">
            <div class="card-body">
                @if ($errors->any())
                    <div class="alert alert-danger">
                        <ul class="mb-0">
                            @foreach ($errors->all() as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                <form method="POST" action="{{ route('logging-fields.update', $loggingField) }}" id="logging-field-edit-form">
                    @csrf
                    @method('PUT')

                    <div class="mb-3">
                        <label for="name" class="form-label">Field Name <span class="text-danger">*</span></label>
                        <input type="text"
                               class="form-control @error('name') is-invalid @enderror"
                               id="name"
                               name="name"
                               value="{{ old('name', $loggingField->name) }}"
                               placeholder="e.g., Event Hours, Prep Time, Travel Miles"
                               required>
                        <small class="form-text text-muted">The display name for this field. Changing the name will regenerate the slug.</small>
                        @error('name')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Slug</label>
                        <input type="text"
                               class="form-control"
                               value="{{ $loggingField->slug }}"
                               disabled>
                        <small class="form-text text-muted">Auto-generated URL-friendly identifier.</small>
                    </div>

                    <div class="mb-3">
                        <label for="field_type" class="form-label">Field Type <span class="text-danger">*</span></label>
                        <select class="form-select @error('field_type') is-invalid @enderror"
                                id="field_type"
                                name="field_type"
                                required
                                onchange="toggleOptionsField()">
                            <option value="">Select field type…</option>
                            @foreach($fieldTypes as $key => $label)
                                <option value="{{ $key }}" {{ old('field_type', $loggingField->field_type) === $key ? 'selected' : '' }}>{{ $label }}</option>
                            @endforeach
                        </select>
                        <small class="form-text text-muted">The type of input control to render for this field.</small>
                        @error('field_type')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="mb-3" id="options-field" style="display: none;">
                        <label for="options_json" class="form-label">Dropdown Options <span class="text-danger">*</span></label>
                        <textarea class="form-control @error('options_json') is-invalid @enderror"
                                  id="options_json"
                                  name="options_json"
                                  rows="5"
                                  placeholder='["Option 1", "Option 2", "Option 3"]'>{{ old('options_json', $loggingField->field_type === 'select' && $loggingField->options_json ? json_encode($loggingField->options_json) : '') }}</textarea>
                        <small class="form-text text-muted">Enter options as a JSON array. Example: <code>["Yes", "No", "Maybe"]</code></small>
                        @error('options_json')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="mb-3">
                        <label for="help_text" class="form-label">Help Text</label>
                        <textarea class="form-control @error('help_text') is-invalid @enderror"
                                  id="help_text"
                                  name="help_text"
                                  rows="2"
                                  placeholder="Optional instructions or description for this field">{{ old('help_text', $loggingField->help_text) }}</textarea>
                        <small class="form-text text-muted">Optional guidance text that will appear below the field.</small>
                        @error('help_text')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="form-check mb-3">
                        <input class="form-check-input"
                               type="checkbox"
                               id="is_active"
                               name="is_active"
                               value="1"
                               {{ old('is_active', $loggingField->is_active) ? 'checked' : '' }}>
                        <label class="form-check-label" for="is_active">
                            Active
                        </label>
                        <small class="form-text text-muted d-block">Inactive fields cannot be selected in agreements or contact families.</small>
                    </div>

                </form>
            </div>
        </div>
    </div>
</div>

<x-save-bar form-id="logging-field-edit-form" cancel-url="{{ route('logging-fields.index') }}" save-label="Save Changes" :last-saved-at="$loggingField->updated_at" />

<script>
function toggleOptionsField() {
    const fieldType = document.getElementById('field_type').value;
    const optionsField = document.getElementById('options-field');
    if (fieldType === 'select') {
        optionsField.style.display = 'block';
    } else {
        optionsField.style.display = 'none';
    }
}

// Run on page load
document.addEventListener('DOMContentLoaded', toggleOptionsField);
</script>

@endsection
