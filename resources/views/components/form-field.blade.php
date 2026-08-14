@props([
    'label' => null,
    'for' => null,
    'name' => null,
    'required' => false,
    'help' => null,
])

<div {{ $attributes->class(['mb-3']) }}>
    @if(filled($label))
        <label @if(filled($for)) for="{{ $for }}" @endif class="form-label{{ $required ? ' required-label' : '' }}">
            {{ $label }}
        </label>
    @endif

    {{ $slot }}

    @if(filled($help))
        <div class="form-text">{{ $help }}</div>
    @endif

    @if(filled($name))
        @error($name)
            <div class="text-danger small mt-1">{{ $message }}</div>
        @enderror
    @endif
</div>
