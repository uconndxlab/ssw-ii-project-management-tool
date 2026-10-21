// A form drawer behaves as a modal: while it is open the rest of the page is inert, and focus goes back to
// whatever opened it. The drawer scripts toggle `.is-open`; this watches for that class.
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
        setBackgroundInert(drawer, open);

        if (!open && lastFocus?.isConnected) {
            lastFocus.focus();
        }
    });
});

document.querySelectorAll('.form-drawer').forEach((drawer) => {
    observer.observe(drawer, { attributes: true, attributeFilter: ['class'] });
});
