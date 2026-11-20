const grid = document.querySelector('[data-overview-grid]');
const reducedMotion = matchMedia('(prefers-reduced-motion: reduce)');

// Grow or shrink an element to its new natural height instead of snapping, so content below glides.
function animateHeight(element, change) {
    const from = element.offsetHeight;
    change();
    if (reducedMotion.matches) return;
    const to = element.offsetHeight;
    element.style.overflow = 'hidden';
    element.animate({ height: [`${from}px`, `${to}px`] }, { duration: 200, easing: 'ease-out' })
        .finished.then(() => element.style.removeProperty('overflow'), () => {});
}

if (grid) {
    const abstract = grid.querySelector('[data-overview-abstract]');
    const abstractToggle = grid.querySelector('[data-abstract-toggle]');
    const checkAbstract = () => {
        if (abstract && abstract.offsetWidth && abstractToggle.getAttribute('aria-expanded') === 'false') {
            abstractToggle.hidden = abstract.scrollHeight <= abstract.clientHeight + 1;
        }
    };
    abstractToggle?.addEventListener('click', () => {
        const expanded = abstractToggle.getAttribute('aria-expanded') !== 'true';
        abstractToggle.setAttribute('aria-expanded', String(expanded));
        abstractToggle.textContent = expanded ? 'Show less' : 'Read more';
        animateHeight(abstract, () => abstract.classList.toggle('is-expanded', expanded));
    });

    grid.querySelectorAll('[data-activity-chart]').forEach((chart) => {
        const bars = [...chart.querySelectorAll('[data-chart-bar]')];
        chart.addEventListener('keydown', (event) => {
            let index = bars.indexOf(document.activeElement);
            if (index < 0 || !['ArrowLeft', 'ArrowRight', 'Home', 'End'].includes(event.key)) return;
            event.preventDefault();
            bars[index].tabIndex = -1;
            index = event.key === 'Home' ? 0 : event.key === 'End' ? bars.length - 1 : (index + (event.key === 'ArrowRight' ? 1 : -1) + bars.length) % bars.length;
            bars[index].tabIndex = 0;
            bars[index].focus();
        });
    });

    new ResizeObserver(checkAbstract).observe(grid);
    document.addEventListener('section-tabs:change', (event) => {
        if (event.detail.section === 'overview') checkAbstract();
    });
}
