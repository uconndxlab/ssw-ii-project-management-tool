<nav class="navbar navbar-expand-lg navbar-dark bg-dark app-navbar" aria-label="Primary navigation">
    <div class="container">
        <a class="navbar-brand" href="{{ route('dashboard') }}">{{ config('app.name') }}</a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#appNavbar" aria-label="Toggle navigation" aria-controls="appNavbar" aria-expanded="false">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="appNavbar">
            <ul class="navbar-nav me-auto">
                @foreach (App\Support\AppNav::primaryLinks() as $link)
                    @php($isActive = App\Support\AppNav::isActive($link['active']))
                    <li class="nav-item">
                        <a @class(['nav-link', 'active' => $isActive])
                           href="{{ route($link['route']) }}"
                           @if($isActive) aria-current="page" @endif>
                            {{ $link['label'] }}
                        </a>
                    </li>
                @endforeach
            </ul>

            <ul class="navbar-nav align-items-lg-center ms-lg-2">
                <li class="nav-item app-navbar-tools">
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
                </li>

                @if (count(App\Support\AppNav::adminSections()) > 0)
                    @php($adminIsActive = App\Support\AppNav::adminIsActive())
                    <li class="nav-item dropdown">
                        <button @class(['nav-link dropdown-toggle', 'active' => $adminIsActive])
                           type="button"
                           id="adminDropdown"
                           data-bs-toggle="dropdown"
                           aria-expanded="false">
                            Admin
                        </button>
                        <ul class="dropdown-menu dropdown-menu-lg-end">
                            @foreach (App\Support\AppNav::adminSections() as $sectionIndex => $section)
                                @if ($sectionIndex > 0)
                                    <li><hr class="dropdown-divider"></li>
                                @endif
                                <li><h6 class="dropdown-header">{{ $section['header'] }}</h6></li>
                                @foreach ($section['items'] as $item)
                                    @php($itemIsActive = App\Support\AppNav::isActive($item['active']))
                                    <li>
                                        <a @class(['dropdown-item', 'is-current' => $itemIsActive])
                                           href="{{ route($item['route']) }}"
                                           @if($itemIsActive) aria-current="page" @endif>
                                            {{ $item['label'] }}
                                        </a>
                                    </li>
                                @endforeach
                            @endforeach
                        </ul>
                    </li>
                @endif

                @php($userMenuIsActive = request()->routeIs('profile', 'profile.*'))
                <li class="nav-item dropdown">
                    <button @class(['nav-link dropdown-toggle', 'active' => $userMenuIsActive])
                       type="button"
                       id="userDropdown"
                       data-bs-toggle="dropdown"
                       aria-expanded="false">
                        {{ auth()->user()->name }}
                    </button>
                    <ul class="dropdown-menu dropdown-menu-end">
                        <li>
                            <a class="dropdown-item {{ request()->routeIs('profile', 'profile.*') ? 'is-current' : '' }}"
                               href="{{ route('profile') }}"
                               @if(request()->routeIs('profile', 'profile.*')) aria-current="page" @endif>
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
    </div>
</nav>
