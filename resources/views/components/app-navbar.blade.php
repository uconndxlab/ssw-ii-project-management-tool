{{-- Phone-only top bar: menu button, brand, Log Activity. On desktop all of this lives in the sidebar. --}}
<header class="navbar navbar-dark bg-dark app-topbar d-lg-none">
    <div class="container-fluid app-topbar-inner">
        <button class="app-topbar-toggler" type="button" data-bs-toggle="offcanvas" data-bs-target="#appRail" aria-controls="appRail" aria-label="Open menu">
            <span class="navbar-toggler-icon"></span>
        </button>
        <a class="navbar-brand" href="{{ route('dashboard') }}">{{ config('app.name') }}</a>
        <a href="{{ route('activities.create') }}"
           @class(['btn btn-primary btn-sm app-navbar-cta', 'is-active' => request()->routeIs('activities.create')])
           @if(request()->routeIs('activities.create')) aria-current="page" @endif>
            Log Activity
        </a>
    </div>
</header>
