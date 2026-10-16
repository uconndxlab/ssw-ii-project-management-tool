const html = document.documentElement;
const rail = document.querySelector('[data-app-rail]');

// Lets the Recent panel and the account menu keep the sidebar open while they are showing.
let holdRail = () => {};

if (rail) {
    const desktop = window.matchMedia('(min-width: 992px)');
    const pin = rail.querySelector('[data-rail-pin]');
    const searchForm = rail.querySelector('[data-rail-search]');
    const searchInput = rail.querySelector('#railSearch');
    const storageKey = rail.dataset.railKey;
    const state = { hover: false, forced: false, dismissed: false, held: {} };
    let enterTimer;
    let leaveTimer;

    const isPinned = () => html.dataset.rail === 'pinned';

    // An unpinned sidebar peeks open (overlaying the page, never moving it) on hover, keyboard focus,
    // a held panel, or a search shortcut. Escape dismisses a peek until the pointer or focus returns.
    function sync() {
        const keyboard = !state.dismissed && rail.querySelector(':focus-visible') !== null;
        const held = Object.values(state.held).some(Boolean);
        const peek = desktop.matches
            && !isPinned()
            && (held || state.forced || (!state.dismissed && (state.hover || keyboard)));

        rail.classList.toggle('is-peek', peek);
    }

    function setPinned(pinned, persist) {
        html.dataset.rail = pinned ? 'pinned' : 'unpinned';
        pin?.setAttribute('aria-pressed', String(pinned));

        if (persist) {
            try {
                localStorage.setItem(storageKey, html.dataset.rail);
            } catch (error) {
                // Storage can be unavailable (private mode); the choice then lasts for this page only.
            }
        }

        sync();
    }

    function focusSearch() {
        state.forced = true;
        state.dismissed = false;
        sync();
        searchInput.focus();
    }

    holdRail = (source, on) => {
        state.held[source] = on;
        sync();
    };

    setPinned(isPinned(), false);

    pin?.addEventListener('click', () => setPinned(!isPinned(), true));

    rail.addEventListener('pointerenter', (event) => {
        if (event.pointerType === 'touch') {
            return;
        }

        clearTimeout(leaveTimer);
        state.dismissed = false;
        enterTimer = setTimeout(() => {
            state.hover = true;
            sync();
        }, 120);
    });

    rail.addEventListener('pointerleave', (event) => {
        if (event.pointerType === 'touch') {
            return;
        }

        clearTimeout(enterTimer);
        leaveTimer = setTimeout(() => {
            state.hover = false;
            sync();
        }, 250);
    });

    rail.addEventListener('focusin', () => {
        state.dismissed = false;
        sync();
    });
    rail.addEventListener('focusout', () => setTimeout(sync, 0));

    rail.addEventListener('keydown', (event) => {
        if (event.key === 'Escape' && rail.classList.contains('is-peek') && !Object.values(state.held).some(Boolean)) {
            state.hover = false;
            state.forced = false;
            state.dismissed = true;
            sync();
        }
    });

    rail.addEventListener('show.bs.dropdown', () => holdRail('menu', true));
    rail.addEventListener('hidden.bs.dropdown', () => holdRail('menu', false));

    searchInput?.addEventListener('blur', () => {
        state.forced = false;
        sync();
    });

    // Clicking the collapsed search icon opens the sidebar and focuses the field.
    searchForm?.addEventListener('click', () => (desktop.matches ? focusSearch() : searchInput.focus()));

    document.addEventListener('keydown', (event) => {
        if (event.key !== '/' || event.ctrlKey || event.metaKey || event.altKey || !searchInput || !desktop.matches) {
            return;
        }

        if (event.target.closest('input, textarea, select, [contenteditable="true"]')) {
            return;
        }

        event.preventDefault();
        focusSearch();
    });

    desktop.addEventListener('change', sync);
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
        holdRail('recent', true);
        loadList();
    });

    panel.addEventListener('shown.bs.offcanvas', () => search.focus());

    panel.addEventListener('hidden.bs.offcanvas', () => {
        trigger?.setAttribute('aria-expanded', 'false');
        document.body.classList.remove('app-recent-open');
        holdRail('recent', false);
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
