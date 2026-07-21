@props([
    'agreement',
    'class' => '',
])

@if($agreement->isLinkable())
    <a href="{{ route('agreements.show', $agreement) }}" {{ $attributes->merge(['class' => 'text-decoration-none '.$class]) }}>
        {{ $agreement->name }}
    </a>
@else
    <span {{ $attributes->merge(['class' => $class]) }}>{{ $agreement->name }}</span>
@endif
