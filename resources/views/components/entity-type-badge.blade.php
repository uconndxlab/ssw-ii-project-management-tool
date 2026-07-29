@props([
    'label',
    'badgeClass',
])

<span {{ $attributes->merge(['class' => "badge {$badgeClass} rounded-pill text-uppercase"]) }} style="font-size:.7rem;letter-spacing:.05em;">
    {{ $label }}
</span>
