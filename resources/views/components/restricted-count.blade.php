@props(['count', 'kind'])

@if($count > 0)
    <span {{ $attributes->merge(['class' => 'app-count app-count--restricted']) }} title="You don't have access to {{ $count }} {{ \Illuminate\Support\Str::lower(\Illuminate\Support\Str::plural(\Illuminate\Support\Str::singular($kind), $count)) }}"><i class="bi bi-lock-fill" aria-hidden="true"></i>{{ $count }} restricted</span>
@endif
