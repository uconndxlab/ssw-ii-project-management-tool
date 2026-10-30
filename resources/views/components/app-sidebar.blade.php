@php($railGroups = App\Support\AppNav::railGroups())
@php($user = auth()->user())
@php($initials = Str::of($user->name)->explode(' ')->filter()->map(fn ($part) => Str::upper(Str::substr($part, 0, 1)))->take(2)->implode(''))
@php($onProfile = request()->routeIs('profile', 'profile.*'))

<div class="app-rail-slot">
    <aside id="appRail"
           class="app-rail offcanvas-lg offcanvas-start"
           tabindex="-1"
           aria-labelledby="appRailTitle"
           data-app-rail
           data-rail-key="ta-rail-pin-{{ $user->id }}">
        <div class="offcanvas-header d-lg-none">
            <h2 class="offcanvas-title h6 mb-0" id="appRailTitle">Menu</h2>
            <button type="button" class="btn-close" data-bs-dismiss="offcanvas" data-bs-target="#appRail" aria-label="Close menu"></button>
        </div>

        <div class="offcanvas-body app-rail-body">
            <div class="app-rail-head d-none d-lg-flex">
                <a class="app-rail-brand" href="{{ route('dashboard') }}">
                    <span class="app-rail-mark" aria-hidden="true">II</span>
                    <span class="app-rail-label app-rail-name">{{ config('app.name') }}</span>
                </a>
                <button type="button"
                        class="app-rail-pin"
                        data-rail-pin
                        title="Pin sidebar open"
                        aria-label="Pin sidebar open"
                        aria-pressed="true">
                    <i class="bi bi-pin-angle app-rail-pin-off" aria-hidden="true"></i>
                    <i class="bi bi-pin-angle-fill app-rail-pin-on" aria-hidden="true"></i>
                </button>
            </div>

            <div class="app-rail-actions">
                @if (App\Support\AppNav::showSearch())
                    <form action="{{ route('search') }}" method="get" role="search" class="app-rail-search" data-rail-search>
                        <i class="bi bi-search" aria-hidden="true"></i>
                        <input type="search"
                               name="q"
                               id="railSearch"
                               class="app-rail-search-input"
                               placeholder="Search…"
                               value="{{ request('q') }}"
                               aria-label="Search"
                               autocomplete="off">
                        <kbd class="app-rail-kbd" aria-hidden="true">/</kbd>
                    </form>
                @endif

                <a href="{{ route('activities.create') }}"
                   @class(['app-rail-log d-none d-lg-flex', 'is-active' => request()->routeIs('activities.create')])
                   @if(request()->routeIs('activities.create')) aria-current="page" @endif>
                    <i class="bi bi-plus-lg" aria-hidden="true"></i>
                    <span class="app-rail-label">Log Activity</span>
                </a>
            </div>

            <nav class="app-rail-nav" aria-label="Primary navigation">
                @foreach ($railGroups as $group)
                    <div class="app-rail-group" role="group" aria-labelledby="appRailGroup{{ $loop->index }}">
                        <h3 class="app-rail-heading" id="appRailGroup{{ $loop->index }}">{{ $group['header'] }}</h3>
                        <x-app-rail-items :items="$group['items']" />
                    </div>
                @endforeach
            </nav>

            <div class="app-rail-footer">
                <button type="button"
                        id="recentTrigger"
                        class="app-rail-link"
                        data-bs-toggle="offcanvas"
                        data-bs-target="#recentPanel"
                        aria-controls="recentPanel"
                        aria-expanded="false">
                    <i class="bi bi-clock-history" aria-hidden="true"></i>
                    <span class="app-rail-label">Recent</span>
                </button>

                <div class="dropup">
                    <button type="button"
                            id="userDropdown"
                            @class(['app-rail-account', 'is-section' => $onProfile])
                            data-bs-toggle="dropdown"
                            data-bs-popper-config='{"strategy":"fixed"}'
                            aria-expanded="false">
                        <span class="app-rail-avatar" aria-hidden="true">{{ $initials }}</span>
                        <span class="app-rail-label app-rail-who">
                            <span class="app-rail-person">{{ $user->name }}</span>
                            <span class="app-rail-role">{{ $user->access_profile->label() }}</span>
                        </span>
                        <i class="bi bi-chevron-expand app-rail-label" aria-hidden="true"></i>
                    </button>
                    <ul class="dropdown-menu app-rail-menu">
                        <li>
                            <a class="dropdown-item {{ $onProfile ? 'is-current' : '' }}"
                               href="{{ route('profile') }}"
                               @if($onProfile) aria-current="page" @endif>
                                <i class="bi bi-person me-2" aria-hidden="true"></i>Profile
                            </a>
                        </li>
                        <li><hr class="dropdown-divider"></li>
                        <li>
                            <form method="POST" action="{{ route('logout') }}">
                                @csrf
                                <button type="submit" class="dropdown-item">
                                    <i class="bi bi-box-arrow-right me-2" aria-hidden="true"></i>Log out
                                </button>
                            </form>
                        </li>
                    </ul>
                </div>
            </div>
        </div>
    </aside>
</div>

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
