<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ config('app.name') }} - @yield('title', 'Home')</title>

    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">

    @if (file_exists(public_path('build/manifest.json')) || file_exists(public_path('hot')))
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    @endif

    <!-- HTMX -->
    <script src="https://unpkg.com/htmx.org@1.9.10"></script>
</head>
<body>
    @auth
        <x-app-navbar />
    @endauth

    <main class="py-4">
        <div class="container">
            @if(session('success') || session('status'))
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    {{ session('success') ?? session('status') }}
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            @endif

            @if(session('error'))
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    {{ session('error') }}
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            @endif

            @yield('content')
        </div>
    </main>

    <!-- Bootstrap 5 JS Bundle -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>

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

    @stack('scripts')
</body>
</html>
