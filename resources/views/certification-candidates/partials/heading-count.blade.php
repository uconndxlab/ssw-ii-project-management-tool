<span class="app-count">{{ $total }}</span>
@if($hidden > 0)
    <span class="app-hidden-note" title="{{ $hidden }} hidden"><i class="bi bi-eye-slash" aria-hidden="true"></i>{{ $hidden }}<span class="visually-hidden"> hidden</span></span>
@endif
