const bodies = new Set();

function syncScroll(body) {
    if (!body.isConnected) {
        resizeObserver.unobserve(body);
        bodies.delete(body);
        return;
    }

    const frame = body.parentElement;
    const status = frame.nextElementSibling;
    const items = [...body.querySelectorAll('[data-ledger-item]')]
        .filter((item) => item.closest('[data-ledger-scroll]') === body);
    const preview = body.closest('.app-ledger').querySelector(':scope > .app-ledger-head [data-ledger-preview]');

    if (preview) {
        const names = items.map((item) => item.dataset.ledgerTitle).filter(Boolean);
        preview.textContent = names.slice(0, 2).join(', ') + (names.length > 2 ? ` +${names.length - 2}` : '');
        preview.hidden = names.length === 0;
    }

    const overflowing = body.clientHeight > 0 && body.scrollHeight > body.clientHeight + 2;
    const moreBelow = overflowing && body.scrollTop + body.clientHeight < body.scrollHeight - 2;

    frame.classList.toggle('has-more-below', moreBelow);
    body.classList.toggle('is-scrollable', overflowing);
    status.hidden = !overflowing;

    if (!overflowing) {
        body.removeAttribute('tabindex');
        body.removeAttribute('role');
        body.removeAttribute('aria-label');
        return;
    }

    body.tabIndex = 0;
    body.setAttribute('role', 'region');
    body.setAttribute('aria-label', body.dataset.scrollLabel);

    const bounds = body.getBoundingClientRect();
    const visible = items.map((item, index) => ({ index, rect: item.getBoundingClientRect() }))
        .filter(({ rect }) => rect.bottom > bounds.top + 1 && rect.top < bounds.bottom - 1);
    const range = visible.length
        ? `Showing ${visible[0].index + 1}–${visible.at(-1).index + 1} of ${items.length}`
        : 'Scroll within this list';

    status.textContent = `${range} · ${moreBelow ? 'more below' : 'end of list'}`;
}

const resizeObserver = new ResizeObserver((entries) => {
    entries.forEach(({ target }) => syncScroll(target));
});

function initScrollPanels() {
    bodies.forEach(syncScroll);
    document.querySelectorAll('[data-ledger-scroll]').forEach((body) => {
        if (!bodies.has(body)) {
            bodies.add(body);
            body.addEventListener('scroll', () => syncScroll(body), { passive: true });
            resizeObserver.observe(body);
        }

        syncScroll(body);
    });
}

document.addEventListener('DOMContentLoaded', initScrollPanels);
document.addEventListener('htmx:afterSettle', initScrollPanels);
