// Tooltips for header buttons. Skipped when the button already shows exactly that text.
document.querySelectorAll('[data-tip]').forEach((button) => {
    const label = button.querySelector('.app-header-btn-label');
    const text = button.dataset.tip;

    if (label && label.offsetParent !== null && label.textContent.trim() === text) {
        return;
    }

    const tooltip = window.bootstrap.Tooltip.getOrCreateInstance(button, {
        title: text,
        placement: 'bottom',
        trigger: 'hover focus',
    });

    button.addEventListener('click', () => tooltip.hide());
});

const sticky = document.querySelector('[data-header-sticky]');
const header = document.querySelector('[data-form-header]');

// The compact bar slides in once the full header has scrolled out of view.
if (sticky) {
    const main = document.querySelector('[data-header-main]');
    const phoneBarHeight = document.querySelector('.app-topbar')?.offsetHeight ?? 0;

    const setVisible = (visible) => {
        sticky.classList.toggle('is-visible', visible);
        sticky.toggleAttribute('inert', !visible);
        sticky.setAttribute('aria-hidden', String(!visible));
    };

    new IntersectionObserver(
        ([entry]) => setVisible(!entry.isIntersecting && entry.boundingClientRect.top < 0),
        { rootMargin: `-${phoneBarHeight + 8}px 0px 0px 0px` },
    ).observe(main);
}

// Save state for the form this header belongs to (the full header and the compact bar share it).
if (header) {
    const form = document.getElementById(header.dataset.formId) ?? header.closest('form');
    const statuses = document.querySelectorAll('[data-form-status]');
    const saveButtons = document.querySelectorAll('[data-form-save]');
    const savedTime = header.dataset.savedTime;
    const initial = header.dataset.initial || 'idle';

    let dirty = false;
    let submitted = false;
    let leaving = false;

    const states = {
        idle: '',
        dirty: '<span class="is-dirty"><i class="bi bi-circle-fill" aria-hidden="true"></i>Unsaved changes</span>',
        saving: '<span class="is-saving">Saving…</span>',
        saved: `<span class="is-saved"><i class="bi bi-check-lg" aria-hidden="true"></i>Saved · ${savedTime}</span>`,
        error: '<span class="is-error"><i class="bi bi-exclamation-triangle" aria-hidden="true"></i>Fix errors above to save</span>',
    };

    // Only one status is announced, even though the bar repeats it.
    statuses[0]?.setAttribute('aria-live', 'polite');

    function setState(name) {
        statuses.forEach((status) => {
            status.innerHTML = states[name] ?? '';
        });
        saveButtons.forEach((button) => {
            button.disabled = name === 'saving';
        });
    }

    function save() {
        form?.requestSubmit();
    }

    if (form) {
        setState(initial);

        saveButtons.forEach((button) => button.addEventListener('click', save));

        const markDirty = () => {
            if (!dirty) {
                dirty = true;
                setState('dirty');
            }
        };

        form.addEventListener('input', markDirty);
        form.addEventListener('change', markDirty);

        // Token pickers write hidden inputs, so they signal edits themselves. Only a person adding or removing
        // a token counts; the pickers also fire this event when other code syncs them.
        form.addEventListener('token-picker:change', (event) => {
            if (event.detail?.user) {
                markDirty();
            }
        });

        form.addEventListener('submit', () => {
            submitted = true;
            dirty = false;
            setState('saving');
        });

        document.addEventListener('click', async (event) => {
            const link = event.target.closest('a[href]');
            if (!dirty || submitted || leaving || !link || event.defaultPrevented || event.button !== 0
                || event.metaKey || event.ctrlKey || event.shiftKey || event.altKey
                || link.hasAttribute('download') || (link.target && link.target !== '_self')
                || link.matches('[data-bs-toggle], [data-bs-dismiss], [hx-get], [hx-post]')) {
                return;
            }

            const sameDocument = link.origin === location.origin && link.pathname === location.pathname && link.search === location.search;
            if (!['http:', 'https:'].includes(link.protocol) || (sameDocument && link.getAttribute('href').includes('#'))) {
                return;
            }

            event.preventDefault();
            event.stopImmediatePropagation();
            const confirmed = await window.appConfirm({
                title: 'Leave without saving?',
                record: header.querySelector('h1')?.textContent.trim(),
                message: 'Your changes have not been saved. Leaving will discard them.',
                action: 'Discard changes',
                cancelLabel: 'Keep editing',
                icon: 'bi-box-arrow-right',
                trigger: link,
            });

            if (confirmed && link.isConnected) {
                leaving = true;
                link.click();
            }
        }, true);

        // Ctrl/⌘ + S saves without the browser's own save dialog.
        document.addEventListener('keydown', (event) => {
            if ((event.metaKey || event.ctrlKey) && event.key.toLowerCase() === 's') {
                event.preventDefault();
                save();
            }
        });

        window.addEventListener('beforeunload', (event) => {
            if (dirty && !submitted && !leaving) {
                event.preventDefault();
                event.returnValue = '';
            }
        });

        // Coming back through the browser's back button restores a page that was mid-save.
        window.addEventListener('pageshow', (event) => {
            if (event.persisted) {
                submitted = false;
                leaving = false;
                setState(dirty ? 'dirty' : initial);
            }
        });
    }
}
