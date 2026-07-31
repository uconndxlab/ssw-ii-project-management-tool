@props([
    'user',
    'label' => null,
])

@php
    $href = \App\Support\UserProfileLink::route($user);
    $text = $label ?? $user->name;
@endphp

@if($href)
    <a href="{{ $href }}" {{ $attributes }}>{{ $text }}</a>
@else
    <span {{ $attributes }}>{{ $text }}</span>
@endif
