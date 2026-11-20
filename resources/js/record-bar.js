document.querySelectorAll('[data-record-bar]').forEach((bar) => {
    const sentinel = bar.previousElementSibling;
    let observer;
    const observe = () => {
        observer?.disconnect();
        const top = parseFloat(getComputedStyle(bar).top) || 0;
        observer = new IntersectionObserver(([entry]) => {
            bar.classList.toggle('is-stuck', !entry.isIntersecting && entry.boundingClientRect.top <= top);
        }, { rootMargin: `-${top}px 0px 0px 0px` });
        observer.observe(sentinel);
    };
    observe();
    window.addEventListener('resize', observe);
});
