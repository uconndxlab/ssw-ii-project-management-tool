@if($total)<span class="app-count">{{ $total }}</span>@endif
<x-restricted-count :count="$hidden" :kind="$kind" />
