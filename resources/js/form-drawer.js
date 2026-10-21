// Behavior for every .form-drawer. A page script opens or closes a drawer by toggling `.is-open`; this file does the rest:
// aria-hidden, the page scroll lock, moving focus in, making the page inert, returning focus, the close buttons, and Escape.
let lastFocus = null;

document.addEventListener('focusin', (event) => {
    if (!event.target.closest('.form-drawer.is-open')) {
        lastFocus = event.target;
    }
});

function setBackgroundInert(drawer, inert) {
    for (let node = drawer; node && node !== document.body; node = node.parentElement) {
        for (const sibling of node.parentElement.children) {
            if (sibling === node) {
                continue;
            }

            if (inert && !sibling.hasAttribute('inert')) {
                sibling.setAttribute('inert', '');
                sibling.dataset.drawerInert = '1';
            } else if (!inert && sibling.dataset.drawerInert) {
                sibling.removeAttribute('inert');
                delete sibling.dataset.drawerInert;
            }
        }
    }
}

const observer = new MutationObserver((records) => {
    records.forEach(({ target: drawer }) => {
        const open = drawer.classList.contains('is-open');

        if (open === (drawer.dataset.drawerWasOpen === '1')) {
            return;
        }

        drawer.dataset.drawerWasOpen = open ? '1' : '';
        drawer.setAttribute('aria-hidden', open ? 'false' : 'true');
        document.body.classList.toggle('form-drawer-open', document.querySelector('.form-drawer.is-open') !== null);
        setBackgroundInert(drawer, open);

        if (open) {
            drawer.querySelector('.form-drawer-panel')?.focus();
        } else if (lastFocus?.isConnected) {
            lastFocus.focus();
        }
    });
});

document.querySelectorAll('.form-drawer').forEach((drawer) => {
    observer.observe(drawer, { attributes: true, attributeFilter: ['class'] });
});

document.addEventListener('click', (event) => {
    event.target.closest('[data-form-drawer-close]')?.closest('.form-drawer')?.classList.remove('is-open');
});

// Escape closes the topmost open drawer. A token picker with its list open handles Escape itself first.
document.addEventListener('keydown', (event) => {
    if (event.key === 'Escape') {
        Array.from(document.querySelectorAll('.form-drawer.is-open')).pop()?.classList.remove('is-open');
    }
});
