@props([
    'user',
    'label' => null,
])

@php
    $href = \App\Support\UserProfileLink::route($user);
    $text = $label ?? $user->name;
    $classes = collect(preg_split('/\s+/', trim($attributes->get('class', ''))))
        ->filter(fn (string $class) => $class !== '' && ! str_starts_with($class, 'text-decoration-'))
        ->push('text-decoration-none')
        ->values()
        ->all();
@endphp

@if($href)
    <a href="{{ $href }}" {{ $attributes->except('class')->class($classes) }}>{{ $text }}</a>
@else
    <span {{ $attributes->except('class')->class($classes) }}>{{ $text }}</span>
@endif
