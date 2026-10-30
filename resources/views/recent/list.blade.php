@if ($items->isEmpty())
    <div class="app-recent-empty">
        <i class="bi bi-clock-history" aria-hidden="true"></i>
        <p class="fw-semibold mb-1">Nothing opened recently</p>
        <p class="text-muted small mb-0">Records you open show up here for seven days, so you can get back to them quickly.</p>
    </div>
@else
    <h3 class="app-recent-label">Last opened</h3>

    <ul class="app-recent-items" data-recent-items>
        @foreach ($items as $item)
            @php($isCurrent = $currentPath !== null && parse_url($item['url'], PHP_URL_PATH) === $currentPath)
            <li data-recent-search="{{ Str::lower($item['title'].' '.$item['type']->label()) }}">
                <a class="app-recent-link" href="{{ $item['url'] }}" @if($isCurrent) aria-current="page" @endif>
                    <i class="bi {{ $item['type']->icon() }} app-recent-icon" aria-hidden="true"></i>
                    <span class="app-recent-text">
                        <span class="app-recent-title">{{ $item['title'] }}</span>
                        <span class="app-recent-meta">
                            {{ $item['type']->label() }}
                            · <time datetime="{{ $item['opened_at']->toIso8601String() }}">{{ $item['opened_at']->gt(now()->subMinute()) ? 'Just now' : $item['opened_at']->diffForHumans() }}</time>
                        </span>
                    </span>
                    @if ($isCurrent)
                        <span class="app-recent-current">Current</span>
                    @endif
                </a>
            </li>
        @endforeach
    </ul>

    <p class="app-recent-empty" data-recent-nomatch hidden>No recent records match your search.</p>

    <div class="app-recent-footer">
        <p class="app-recent-note">Up to 30 records · last 7 days.<br>Only records you can still open.</p>
        <button type="button"
                class="btn btn-link btn-sm p-0 flex-shrink-0"
                hx-delete="{{ route('recent.clear') }}"
                data-confirm-title="Clear recent records?"
                data-confirm-message="This empties your Recent list on every device. The records themselves are not changed."
                data-confirm-action="Clear recent"
                data-confirm-icon="bi-clock-history"
                hx-target="#recentList"
                hx-swap="innerHTML">
            Clear recent
        </button>
    </div>
@endif
