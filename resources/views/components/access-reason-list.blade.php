@props(['reasons' => []])

<div class="app-chip-list app-access-reasons">
    @forelse(array_slice($reasons, 0, 1) as $reason)
        <span class="app-tag" title="{{ $reason['explanation'] }}">{{ $reason['label'] }}</span>
    @empty
        <span class="text-muted">—</span>
    @endforelse
    @if(count($reasons) > 1)
        <div class="dropdown app-more">
            <button type="button" class="app-more-btn"
                    data-bs-toggle="dropdown"
                    data-bs-popper-config='{"strategy":"fixed"}'
                    aria-expanded="false"
                    aria-label="Show all {{ count($reasons) }} reasons you can see this row">+{{ count($reasons) - 1 }}</button>
            <div class="dropdown-menu app-more-menu">
                <div class="app-more-head"><span>My role</span><span>{{ count($reasons) }}</span></div>
                <ul class="app-more-list" aria-label="All reasons you can see this row">
                    @foreach($reasons as $reason)
                        <li class="dropdown-item-text text-wrap">
                            <span class="app-tag">{{ $reason['label'] }}</span>
                            <span class="d-block small text-body-secondary">{{ $reason['explanation'] }}</span>
                        </li>
                    @endforeach
                </ul>
            </div>
        </div>
    @endif
</div>
