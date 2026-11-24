<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ config('app.name') }} - @yield('title', 'Home')</title>

    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-T3c6CoIi6uLrA9TneNEoa7RxnatzjcDSCmG1MXxSR1GAsXEV/Dwwykc2MPK8M2HN" crossorigin="anonymous">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet" integrity="sha384-XGjxtQfXaH2tnPFa9x+ruJTuLE3Aa6LhHSWRr1XeTyhezb4abCG4ccI5AkVDxqC+" crossorigin="anonymous">

    @auth
        <script>
            (function () {
                var state = null;
                try { state = localStorage.getItem('ta-rail-pin-{{ auth()->id() }}'); } catch (e) {}
                if (state !== 'pinned' && state !== 'unpinned') {
                    state = window.innerWidth < 1200 ? 'unpinned' : 'pinned';
                }
                document.documentElement.setAttribute('data-rail', state);
            })();
        </script>
    @endauth

    @if (file_exists(public_path('build/manifest.json')) || file_exists(public_path('hot')))
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    @endif

    <!-- HTMX -->
    <script src="https://unpkg.com/htmx.org@1.9.10" integrity="sha384-D1Kt99CQMDuVetoL1lrYwg5t+9QdHe7NLX/SoJYkXDFfX37iInKRy5xLSi8nO7UC" crossorigin="anonymous"></script>
</head>
<body data-repeater-has-errors="{{ $errors->any() ? 'true' : 'false' }}">
    <a href="#main" class="visually-hidden-focusable app-skip-link">Skip to content</a>
    <div id="app-live" class="visually-hidden" role="status" aria-live="polite"></div>
    @auth
        <x-toasts />
        <x-app-navbar />
        <div class="app-body">
            <x-app-sidebar />
    @endauth

    <main id="main" @class(['py-4', 'app-main' => auth()->check()]) tabindex="-1">
        <div class="container">
            @yield('content')
        </div>
    </main>
    @auth
        </div>
    @endauth

    <x-confirmation-dialog />

    <!-- Bootstrap 5 JS Bundle -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js" integrity="sha384-C6RzsynM9kWDrMNeT87bh95OGNyZPhcTNXj1NW7RuBCsyN/o0jlpcV8Qyq46cDfL" crossorigin="anonymous"></script>

    <script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.6/Sortable.min.js"></script>

    @stack('scripts')
</body>
</html>
