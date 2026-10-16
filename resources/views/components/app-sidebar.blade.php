@php($railGroups = App\Support\AppNav::railGroups())

<aside id="appRail"
       class="app-rail offcanvas-lg offcanvas-start"
       tabindex="-1"
       aria-labelledby="appRailTitle"
       data-app-rail
       data-rail-key="ta-rail-{{ auth()->id() }}">
    <div class="offcanvas-header d-lg-none">
        <h2 class="offcanvas-title h6 mb-0" id="appRailTitle">Menu</h2>
        <button type="button" class="btn-close" data-bs-dismiss="offcanvas" data-bs-target="#appRail" aria-label="Close menu"></button>
    </div>

    <div class="offcanvas-body app-rail-body">
        <button type="button"
                class="app-rail-link app-rail-toggle d-none d-lg-flex"
                data-rail-toggle
                data-rail-label="Collapse sidebar"
                aria-controls="appRail"
                aria-expanded="true">
            <i class="bi bi-chevron-double-left app-rail-toggle-collapse" aria-hidden="true"></i>
            <i class="bi bi-chevron-double-right app-rail-toggle-expand" aria-hidden="true"></i>
            <span class="app-rail-label" data-rail-toggle-label>Collapse sidebar</span>
        </button>

        <nav class="app-rail-nav" aria-label="Primary navigation">
            @foreach ($railGroups as $group)
                <div class="app-rail-group" role="group" aria-labelledby="appRailGroup{{ $loop->index }}">
                    <h3 class="app-rail-heading" id="appRailGroup{{ $loop->index }}">{{ $group['header'] }}</h3>
                    <ul class="app-rail-list">
                        @foreach ($group['items'] as $item)
                            @php($inSection = App\Support\AppNav::isActive($item['active']))
                            @php($onIndex = request()->routeIs($item['route']))
                            <li>
                                <a @class(['app-rail-link', 'is-section' => $inSection])
                                   href="{{ route($item['route']) }}"
                                   data-rail-label="{{ $item['label'] }}"
                                   @if($inSection) aria-current="{{ $onIndex ? 'page' : 'true' }}" @endif>
                                    <i class="bi {{ $item['icon'] }}" aria-hidden="true"></i>
                                    <span class="app-rail-label">{{ $item['label'] }}</span>
                                </a>
                            </li>
                        @endforeach
                    </ul>
                </div>
            @endforeach
        </nav>

        <div class="app-rail-footer">
            <button type="button"
                    id="recentTrigger"
                    class="app-rail-link"
                    data-bs-toggle="offcanvas"
                    data-bs-target="#recentPanel"
                    data-rail-label="Recent"
                    aria-controls="recentPanel"
                    aria-expanded="false">
                <i class="bi bi-clock-history" aria-hidden="true"></i>
                <span class="app-rail-label">Recent</span>
            </button>
        </div>
    </div>
</aside>

<div id="recentPanel"
     class="offcanvas offcanvas-start app-recent"
     tabindex="-1"
     aria-labelledby="recentPanelTitle">
    <div class="offcanvas-header">
        <h2 class="offcanvas-title h4 mb-0" id="recentPanelTitle">Recent</h2>
        <button type="button" class="btn-close" data-bs-dismiss="offcanvas" aria-label="Close recent"></button>
    </div>
    <div class="app-recent-search">
        <label class="visually-hidden" for="recentSearch">Search recent records</label>
        <i class="bi bi-search" aria-hidden="true"></i>
        <input type="search"
               id="recentSearch"
               class="form-control"
               placeholder="Find a recent record…"
               autocomplete="off">
    </div>
    <div id="recentList"
         class="app-recent-list"
         hx-get="{{ route('recent.panel') }}"
         hx-trigger="recent:load"
         hx-swap="innerHTML"
         aria-live="polite">
        <p class="app-recent-status" role="status">Loading recent records…</p>
    </div>
</div>
