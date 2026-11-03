@props([
    'kind',
    'count',
])

<span {{ $attributes->merge(['class' => 'app-count']) }}>{{ $count }}</span>
