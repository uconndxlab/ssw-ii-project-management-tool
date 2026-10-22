const frames = new Set();

function syncEdges(frame) {
    const scroller = frame.querySelector('.table-responsive');
    const table = scroller.querySelector('table');

    if (!frame.isConnected) {
        observer.unobserve(frame);
        observer.unobserve(table);
        frames.delete(frame);
        return;
    }

    frame.classList.toggle('has-more-above', scroller.scrollTop > 1);
    frame.classList.toggle('has-more-below', scroller.scrollHeight - scroller.clientHeight - scroller.scrollTop > 1);
    frame.classList.toggle('has-more-left', scroller.scrollLeft > 1);
    frame.classList.toggle('has-more-right', scroller.scrollWidth - scroller.clientWidth - scroller.scrollLeft > 1);
    const header = table.tHead;
    const headerHeight = header && getComputedStyle(header).position === 'sticky' ? header.getBoundingClientRect().height : 0;
    frame.style.setProperty('--app-table-header-height', `${headerHeight}px`);
}

const observer = new ResizeObserver((entries) => {
    entries.forEach(({ target }) => syncEdges(target.closest('.app-table-scroll-frame')));
});

function initTableScroll() {
    frames.forEach(syncEdges);
    document.querySelectorAll('.app-table-scroll-frame').forEach((frame) => {
        if (!frames.has(frame)) {
            frames.add(frame);
            frame.querySelector('.table-responsive').addEventListener('scroll', () => syncEdges(frame), { passive: true });
            observer.observe(frame);
            observer.observe(frame.querySelector('table'));
        }

        syncEdges(frame);
    });
}

document.addEventListener('DOMContentLoaded', initTableScroll);
document.addEventListener('htmx:afterSettle', initTableScroll);
