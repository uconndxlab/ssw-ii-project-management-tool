@if($count > 0)
    <div class="app-hidden-note mt-1" title="You don't have access to {{ $count }} {{ \Illuminate\Support\Str::plural(\Illuminate\Support\Str::singular($kind), $count) }}"><i class="bi bi-eye-slash" aria-hidden="true"></i>{{ $count }} hidden</div>
@endif
