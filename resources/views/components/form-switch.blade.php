@props([
    'name',
    'id' => null,
    'label' => null,
    'help' => null,
    'checked' => false,
    'value' => '1',
    'offValue' => '0',
])

@php
    $inputId = $id ?: $name;
    $isChecked = filter_var($checked, FILTER_VALIDATE_BOOLEAN);
@endphp

<div {{ $attributes->class(['mb-3']) }}>
    <input type="hidden" name="{{ $name }}" value="{{ $offValue }}">
    <div class="form-check form-switch m-0 ps-0 d-flex align-items-start gap-2">
        <input type="checkbox"
               class="form-check-input ms-0 mt-1 @error($name) is-invalid @enderror"
               role="switch"
               id="{{ $inputId }}"
               name="{{ $name }}"
               value="{{ $value }}"
               {{ $isChecked ? 'checked' : '' }}>
        @if(filled($label))
            <label class="form-check-label" for="{{ $inputId }}">{{ $label }}</label>
        @endif
    </div>
    @if(filled($help))
        <div class="form-text">{{ $help }}</div>
    @endif
    @error($name)
        <div class="text-danger small mt-1">{{ $message }}</div>
    @enderror
</div>
