@props([
    'name',
    'value',
    'label',
    'description' => null,
    'checked' => false,
    'required' => false,
])

@php
    $isChecked = filter_var($checked, FILTER_VALIDATE_BOOLEAN);
@endphp

<label class="form-radio-card form-check border rounded px-3 py-2 mb-0">
    <input {{ $attributes->class(['form-check-input', 'me-2']) }}
           type="radio"
           name="{{ $name }}"
           value="{{ $value }}"
           {{ $isChecked ? 'checked' : '' }}
           {{ $required ? 'required' : '' }}>
    <span class="form-check-label fw-semibold">{{ $label }}</span>
    @if(filled($description))
        <span class="text-muted small d-block">{{ $description }}</span>
    @endif
</label>
