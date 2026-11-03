@props([
    'label' => null,
    'for' => null,
    'name' => null,
    'required' => false,
    'help' => null,
    'group' => false,
])

@php
    // `group` renders a fieldset and legend, for a set of radios that share one question.
    $fieldKey = $for ?: 'field-'.\Illuminate\Support\Str::random(6);
    $labelId = $fieldKey.'-label';
    $helpId = $fieldKey.'-help';
    $errorId = $fieldKey.'-error';
    $hasError = filled($name) && $errors->has($name);
    $describedBy = trim((filled($help) ? $helpId : '').' '.($hasError ? $errorId : ''));
@endphp

<{{ $group ? 'fieldset' : 'div' }} {{ $attributes->class(['mb-3']) }}
    data-form-field
    @if(filled($name)) data-field-name="{{ $name }}" @endif
    @if(filled($for)) data-control-id="{{ $for }}" @endif
    @if($required) data-field-required @endif
    @if($group && $describedBy !== '') aria-describedby="{{ $describedBy }}" @endif>
    @if(filled($label))
        @if($group)
            <legend id="{{ $labelId }}" class="form-label float-none w-auto p-0 fs-6{{ $required ? ' required-label' : '' }}">{{ $label }}</legend>
        @else
            <label id="{{ $labelId }}" @if(filled($for)) for="{{ $for }}" @endif class="form-label{{ $required ? ' required-label' : '' }}">
                {{ $label }}
            </label>
        @endif
    @endif

    {{ $slot }}

    @if(filled($help))
        <div class="form-text" id="{{ $helpId }}" data-field-help>{{ $help }}</div>
    @endif

    @if($hasError)
        <div class="text-danger small mt-1" id="{{ $errorId }}" data-field-error>{{ $errors->first($name) }}</div>
    @endif
</{{ $group ? 'fieldset' : 'div' }}>
