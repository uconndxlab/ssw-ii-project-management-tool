<nav class="navbar navbar-expand-lg navbar-dark bg-dark app-navbar">
    <div class="container-fluid">
        <a class="navbar-brand" href="{{ route('dashboard') }}">{{ config('app.name') }}</a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#appNavbar">
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
                    <input type="search"
                           class="app-navbar-search-input"
                           placeholder="Search agreements, organizations, people…"
                           disabled
                           aria-label="Search (coming soon)">
                    <a href="{{ route('activities.create') }}"
                       @class(['app-navbar-cta', 'is-active' => request()->routeIs('activities.create')])>
                        Log Activity
                    </a>
                </li>

                @if (auth()->user()->isAdmin())
                    @php($adminIsActive = App\Support\AppNav::adminIsActive())
                    <li class="nav-item dropdown app-navbar-admin">
                        <a @class(['nav-link dropdown-toggle', 'active' => $adminIsActive])
                           href="#"
                           id="adminDropdown"
                           role="button"
                           data-bs-toggle="dropdown"
                           aria-expanded="false">
                            Admin
                        </a>
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

                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle" href="#" id="userDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                        {{ auth()->user()->name }} ({{ ucfirst(auth()->user()->role) }})
                    </a>
                    <ul class="dropdown-menu dropdown-menu-end">
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
