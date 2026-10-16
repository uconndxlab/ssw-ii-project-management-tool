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
<body>
    <a href="#main" class="visually-hidden-focusable app-skip-link">Skip to content</a>
    @auth
        <x-app-navbar />
        <div class="app-body">
            <x-app-sidebar />
    @endauth

    <main id="main" @class(['py-4', 'app-main' => auth()->check()]) tabindex="-1">
        <div class="container">
            @if(session('success') || session('status'))
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    {{ session('success') ?? session('status') }}
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            @endif

            @if(session('allocation_warnings') && count(session('allocation_warnings')) > 0)
                <div class="alert alert-warning alert-dismissible fade show" role="alert">
                    <div class="fw-semibold mb-1">Deliverable allocation needs attention</div>
                    <ul class="mb-0 small">
                        @foreach(session('allocation_warnings') as $warning)
                            <li>{{ $warning }}</li>
                        @endforeach
                    </ul>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            @endif

            @if(session('error'))
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    {{ session('error') }}
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            @endif

            @yield('content')
        </div>
    </main>
    @auth
        </div>
    @endauth

    <!-- Bootstrap 5 JS Bundle -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js" integrity="sha384-C6RzsynM9kWDrMNeT87bh95OGNyZPhcTNXj1NW7RuBCsyN/o0jlpcV8Qyq46cDfL" crossorigin="anonymous"></script>

    <script>
        (function () {
            var defaultIgnoredNames = ['sort', 'direction', 'page', 'partial'];

            function ignoredNamesForForm(form) {
                var ignored = new Set(defaultIgnoredNames);
                (form.dataset.tableFilterIgnore || '')
                    .split(',')
                    .map(function (name) { return name.trim(); })
                    .filter(Boolean)
                    .forEach(function (name) { ignored.add(name); });

                return ignored;
            }

            function formHasActiveFilters(form) {
                var ignored = ignoredNamesForForm(form);
                var hasFilter = false;

                form.querySelectorAll('input, select, textarea').forEach(function (element) {
                    if (!element.name || element.disabled) {
                        return;
                    }

                    if (ignored.has(element.name)) {
                        return;
                    }

                    if (element.type === 'checkbox' || element.type === 'radio') {
                        if (element.checked && String(element.value || '').trim() !== '') {
                            hasFilter = true;
                        }

                        return;
                    }

                    if (String(element.value || '').trim() !== '') {
                        hasFilter = true;
                    }
                });

                return hasFilter;
            }

            function initTooltips(scope) {
                if (!window.bootstrap || !bootstrap.Tooltip) {
                    return;
                }

                var root = scope || document;
                root.querySelectorAll('[data-bs-toggle="tooltip"]').forEach(function (element) {
                    bootstrap.Tooltip.getOrCreateInstance(element);
                });
            }

            function disposeTooltips(scope) {
                if (!window.bootstrap || !bootstrap.Tooltip) {
                    return;
                }

                var root = scope || document;
                root.querySelectorAll('[data-bs-toggle="tooltip"]').forEach(function (element) {
                    var tooltip = bootstrap.Tooltip.getInstance(element);
                    if (tooltip) {
                        tooltip.hide();
                        tooltip.dispose();
                    }
                });
            }

            window.initTooltips = initTooltips;
            window.disposeTooltips = disposeTooltips;

            window.syncTableFilterClearButtons = function (scope) {
                var root = scope || document;
                root.querySelectorAll('[data-table-filter-form]').forEach(function (form) {
                    var wrap = form.querySelector('[data-table-filter-clear-wrap]');
                    if (!wrap) {
                        return;
                    }

                    wrap.classList.toggle('d-none', !formHasActiveFilters(form));
                });

                initTooltips(root);
            };

            document.addEventListener('DOMContentLoaded', function () {
                syncTableFilterClearButtons();
                initTooltips();
            });

            document.body.addEventListener('input', function (event) {
                var form = event.target && event.target.closest
                    ? event.target.closest('[data-table-filter-form]')
                    : null;
                if (form) {
                    syncTableFilterClearButtons(form);
                }
            });

            document.body.addEventListener('change', function (event) {
                var form = event.target && event.target.closest
                    ? event.target.closest('[data-table-filter-form]')
                    : null;
                if (form) {
                    syncTableFilterClearButtons(form);
                }
            });

            document.body.addEventListener('htmx:afterSettle', function () {
                syncTableFilterClearButtons();
                initTooltips();
            });
        })();
    </script>

    <script>
        // Attach CSRF token to every HTMX request globally
        document.addEventListener('htmx:configRequest', function (evt) {
            var meta = document.querySelector('meta[name="csrf-token"]');
            if (meta) evt.detail.headers['X-CSRF-TOKEN'] = meta.getAttribute('content');
        });
    </script>

    @once
        @include('components.repeater-rows-script')
    @endonce

    @stack('scripts')
</body>
</html>
