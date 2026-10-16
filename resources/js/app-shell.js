const html = document.documentElement;
const rail = document.querySelector('[data-app-rail]');

if (rail) {
    const desktop = window.matchMedia('(min-width: 992px)');
    const toggle = rail.querySelector('[data-rail-toggle]');
    const toggleLabel = rail.querySelector('[data-rail-toggle-label]');
    const storageKey = rail.dataset.railKey;

    const isCollapsed = () => html.dataset.rail === 'collapsed';

    function syncTooltips() {
        if (!window.bootstrap) {
            return;
        }

        const showLabels = desktop.matches && isCollapsed();

        rail.querySelectorAll('[data-rail-label]').forEach((element) => {
            if (showLabels) {
                window.bootstrap.Tooltip.getOrCreateInstance(element, {
                    title: element.dataset.railLabel,
                    placement: 'right',
                    container: 'body',
                    trigger: 'hover focus',
                });
            } else {
                window.bootstrap.Tooltip.getInstance(element)?.dispose();
            }
        });
    }

    function applyRailState(state, persist) {
        html.dataset.rail = state;

        const collapsed = state === 'collapsed';
        toggle?.setAttribute('aria-expanded', String(!collapsed));
        toggle?.setAttribute('data-rail-label', collapsed ? 'Expand sidebar' : 'Collapse sidebar');

        if (toggleLabel) {
            toggleLabel.textContent = collapsed ? 'Expand sidebar' : 'Collapse sidebar';
        }

        syncTooltips();

        if (persist) {
            try {
                localStorage.setItem(storageKey, state);
            } catch (error) {
                // Storage can be unavailable (private mode); the choice then lasts for this page only.
            }
        }
    }

    applyRailState(html.dataset.rail === 'collapsed' ? 'collapsed' : 'expanded', false);

    toggle?.addEventListener('click', () => {
        applyRailState(isCollapsed() ? 'expanded' : 'collapsed', true);
    });

    desktop.addEventListener('change', syncTooltips);
}

const panel = document.getElementById('recentPanel');

if (panel) {
    const list = document.getElementById('recentList');
    const search = document.getElementById('recentSearch');
    const trigger = document.getElementById('recentTrigger');
    const loadingMarkup = '<p class="app-recent-status" role="status">Loading recent records…</p>';

    function loadList() {
        list.innerHTML = loadingMarkup;
        list.setAttribute('aria-busy', 'true');
        window.htmx.trigger(list, 'recent:load');
    }

    function showError() {
        list.removeAttribute('aria-busy');
        list.innerHTML = '';

        const message = document.createElement('div');
        message.className = 'app-recent-empty';
        message.setAttribute('role', 'alert');
        message.innerHTML = '<p class="fw-semibold mb-1">Recent records didn’t load</p>'
            + '<p class="small mb-2">Check your connection and try again.</p>'
            + '<button type="button" class="btn btn-sm btn-outline-secondary" data-recent-retry>Try again</button>';
        list.append(message);
    }

    function applyFilter() {
        const query = search.value.trim().toLowerCase();
        const items = list.querySelectorAll('[data-recent-search]');
        let visible = 0;

        items.forEach((item) => {
            const matches = query === '' || item.dataset.recentSearch.includes(query);
            item.hidden = !matches;
            visible += matches ? 1 : 0;
        });

        const noMatch = list.querySelector('[data-recent-nomatch]');

        if (noMatch) {
            noMatch.hidden = items.length === 0 || visible > 0;
        }
    }

    panel.addEventListener('show.bs.offcanvas', () => {
        // Bootstrap only auto-closes elements with the plain .offcanvas class, not the responsive .offcanvas-lg rail.
        if (rail?.classList.contains('show')) {
            window.bootstrap.Offcanvas.getInstance(rail)?.hide();
        }

        trigger?.setAttribute('aria-expanded', 'true');
        document.body.classList.add('app-recent-open');
        loadList();
    });

    panel.addEventListener('shown.bs.offcanvas', () => search.focus());

    panel.addEventListener('hidden.bs.offcanvas', () => {
        trigger?.setAttribute('aria-expanded', 'false');
        document.body.classList.remove('app-recent-open');
        search.value = '';
    });

    list.addEventListener('htmx:afterSwap', () => {
        list.removeAttribute('aria-busy');
        applyFilter();
    });
    list.addEventListener('htmx:responseError', showError);
    list.addEventListener('htmx:sendError', showError);
    list.addEventListener('click', (event) => {
        if (event.target.closest('[data-recent-retry]')) {
            loadList();
        }
    });
    search.addEventListener('input', applyFilter);

    // The desktop backdrop is click-through so the rail stays usable, so close on outside clicks here.
    document.addEventListener('mousedown', (event) => {
        if (!panel.classList.contains('show') || panel.contains(event.target) || trigger?.contains(event.target)) {
            return;
        }

        window.bootstrap.Offcanvas.getInstance(panel)?.hide();
    });
}
