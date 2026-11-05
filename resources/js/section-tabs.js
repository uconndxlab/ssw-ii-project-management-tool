const owned = (root, selector) => [...root.querySelectorAll(selector)]
    .filter((node) => node.closest('[data-section-tabs]') === root);

export function navigateSection(root, key, { focus = false } = {}) {
    const panels = owned(root, '[data-section]');
    const nav = owned(root, '[data-section-nav]');
    const available = nav.filter((item) => !item.hidden && getComputedStyle(item).display !== 'none'
        && panels.some((panel) => panel.dataset.section === item.dataset.sectionNav));
    const selected = available.find((item) => item.dataset.sectionNav === key) ?? available[0];
    if (!selected) return;

    const section = selected.dataset.sectionNav;
    panels.forEach((panel) => { panel.hidden = panel.dataset.section !== section; });
    nav.forEach((item) => {
        const active = item === selected;
        item.classList.toggle('active', active);
        if (active) item.setAttribute('aria-current', 'page');
        else item.removeAttribute('aria-current');
    });
    if (focus) selected.focus();
    root.dispatchEvent(new CustomEvent('section-tabs:change', { bubbles: true, detail: { section } }));
    window.dispatchEvent(new Event('resize'));
    return section;
}

document.querySelectorAll('[data-section-tabs]').forEach((root) => {
    const hash = root.hasAttribute('data-section-tabs-hash');
    root.addEventListener('click', (event) => {
        const trigger = event.target.closest('[data-section-nav], [data-section-goto]');
        if (!trigger || trigger.closest('[data-section-tabs]') !== root) return;
        event.preventDefault();
        const section = navigateSection(root, trigger.dataset.sectionNav ?? trigger.dataset.sectionGoto);
        if (hash && section) history.replaceState(history.state, '', `#${encodeURIComponent(section)}`);
    });
    if (hash) {
        const followHash = () => navigateSection(root, location.hash.slice(1));
        followHash();
        window.addEventListener('hashchange', followHash);
    }
});
