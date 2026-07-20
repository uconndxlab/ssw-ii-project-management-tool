@php
    $fieldId = $field->id;
    $inputId = $inputId ?? str_replace(['[', ']'], ['_', ''], $inputName) . '_' . $fieldId;
    $isRequired = $isRequired ?? false;
    $value = $value ?? null;
    $options = $field->options_json ?? [];
    $downloadContext = $downloadContext ?? (str_starts_with($inputName, 'contact_family') ? 'contact_family' : 'agreement');
@endphp

<div class="mb-3">
    <label for="{{ $inputId }}" class="form-label fw-semibold">
        {{ $field->name }}
        @if($isRequired)
            <span class="text-danger">*</span>
        @endif
    </label>

    @if($field->field_type === 'textarea')
        <textarea class="form-control"
                  id="{{ $inputId }}"
                  name="{{ $inputName }}"
                  rows="3">{{ old($oldKey ?? '', $value) }}</textarea>
    @elseif($field->field_type === 'select')
        <select class="form-select" id="{{ $inputId }}" name="{{ $inputName }}">
            <option value="">Select…</option>
            @foreach($options as $option)
                <option value="{{ $option }}" {{ (string) old($oldKey ?? '', $value) === (string) $option ? 'selected' : '' }}>{{ $option }}</option>
            @endforeach
        </select>
    @elseif($field->field_type === 'checkbox')
        <input type="hidden" name="{{ $inputName }}" value="0">
        <div class="form-check">
            <input class="form-check-input"
                   type="checkbox"
                   id="{{ $inputId }}"
                   name="{{ $inputName }}"
                   value="1"
                   {{ old($oldKey ?? '', $value) ? 'checked' : '' }}>
            <label class="form-check-label" for="{{ $inputId }}">Yes</label>
        </div>
    @elseif($field->field_type === 'document')
        <input type="file"
               class="form-control"
               id="{{ $inputId }}"
               name="{{ $inputName }}"
               accept=".pdf,.doc,.docx,.xls,.xlsx,.png,.jpg,.jpeg">
        <div class="form-text">PDF, Word, Excel, or image files. Max {{ (int) round(config('uploads.max_file_kb') / 1024) }}MB.</div>
        @if(!empty($value))
            <div class="mt-1 small">
                <span class="text-muted">Current file:</span>
                <a href="{{ route('activities.logging-field-document.download', [
                    'activity' => $activity ?? 0,
                    'context'  => $downloadContext,
                    'fieldId'  => $field->id,
                    'agreementId' => $agreementId ?? null,
                ]) }}" class="text-decoration-none" target="_blank">
                    {{ basename($value) }}
                </a>
                <span class="text-muted">(upload a new file to replace)</span>
            </div>
        @endif
    @elseif(in_array($field->field_type, ['number', 'decimal'], true))
        <input type="number"
               class="form-control"
               id="{{ $inputId }}"
               name="{{ $inputName }}"
               step="{{ $field->field_type === 'decimal' ? '0.01' : '1' }}"
               value="{{ old($oldKey ?? '', $value) }}">
    @else
        <input type="text"
               class="form-control"
               id="{{ $inputId }}"
               name="{{ $inputName }}"
               value="{{ old($oldKey ?? '', $value) }}">
    @endif

    @error($oldKey ?? '')
        <div class="text-danger small mt-1">{{ $message }}</div>
    @enderror

    @if($field->help_text)
        <div class="form-text">{{ $field->help_text }}</div>
    @endif
</div>
