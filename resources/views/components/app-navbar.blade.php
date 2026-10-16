<header class="navbar navbar-expand navbar-dark bg-dark app-topbar">
    <div class="container-fluid app-topbar-inner">
        <button class="app-topbar-toggler d-lg-none" type="button" data-bs-toggle="offcanvas" data-bs-target="#appRail" aria-controls="appRail" aria-label="Open menu">
            <span class="navbar-toggler-icon"></span>
        </button>
        <a class="navbar-brand" href="{{ route('dashboard') }}">{{ config('app.name') }}</a>

        @if (App\Support\AppNav::showSearch())
            <form action="{{ route('search') }}" method="get" class="app-navbar-search" role="search">
                <input type="search"
                       name="q"
                       class="app-navbar-search-input"
                       placeholder="Search…"
                       value="{{ request('q') }}"
                       aria-label="Search">
            </form>
        @endif

        <a href="{{ route('activities.create') }}"
           @class(['btn btn-primary btn-sm app-navbar-cta', 'is-active' => request()->routeIs('activities.create')])
           @if(request()->routeIs('activities.create')) aria-current="page" @endif>
            Log Activity
        </a>

        @php($userMenuIsActive = request()->routeIs('profile', 'profile.*'))
        <ul class="navbar-nav">
            <li class="nav-item dropdown">
                <button @class(['nav-link dropdown-toggle', 'active' => $userMenuIsActive])
                   type="button"
                   id="userDropdown"
                   data-bs-toggle="dropdown"
                   aria-expanded="false">
                    <i class="bi bi-person-circle d-md-none" aria-hidden="true"></i>
                    <span class="d-none d-md-inline">{{ auth()->user()->name }}</span>
                    <span class="visually-hidden d-md-none">{{ auth()->user()->name }}</span>
                </button>
                <ul class="dropdown-menu dropdown-menu-end">
                    <li>
                        <a class="dropdown-item {{ $userMenuIsActive ? 'is-current' : '' }}"
                           href="{{ route('profile') }}"
                           @if($userMenuIsActive) aria-current="page" @endif>
                            Profile
                        </a>
                    </li>
                    <li><hr class="dropdown-divider"></li>
                    <li>
                        <form method="POST" action="{{ route('logout') }}">
                            @csrf
                            <button type="submit" class="dropdown-item">Logout</button>
                        </form>
                    </li>
                </ul>
            </li>
        </ul>
    </div>
</header>
