@if($count > 0)
    <div class="text-muted small" title="{{ $count }} other {{ $kind }} you can't see">+{{ $count }} {{ \Illuminate\Support\Str::plural('other', $count) }}<span class="visually-hidden"> {{ $kind }} you can't see</span></div>
@endif
