document.addEventListener('click', (event) => {
    const opener = event.target.closest('[data-bs-toggle="modal"]');

    if (opener) {
        const modal = document.querySelector(opener.getAttribute('data-bs-target'));

        if (modal) {
            modal.classList.add('show');
        }

        return;
    }

    const closer = event.target.closest('[data-bs-dismiss="modal"]');

    if (closer) {
        closer.closest('.modal')?.classList.remove('show');

        return;
    }

    if (event.target.classList.contains('modal')) {
        event.target.classList.remove('show');
    }
});

document.addEventListener('keydown', (event) => {
    if (event.key !== 'Escape') {
        return;
    }

    document.querySelectorAll('.modal.show').forEach((modal) => modal.classList.remove('show'));
});

document.addEventListener('wheel', (event) => {
    const field = document.activeElement;

    if (field instanceof HTMLInputElement && field.type === 'number' && field === event.target) {
        field.blur();
    }
}, { passive: true });

document.addEventListener('click', (event) => {
    document.querySelectorAll('[data-user-menu]').forEach((menu) => {
        const panel = menu.querySelector('.sfp-user-menu-panel');
        const toggle = menu.querySelector('[data-user-menu-toggle]');
        const clickedToggle = toggle.contains(event.target);

        panel.hidden = clickedToggle ? ! panel.hidden : true;
        toggle.setAttribute('aria-expanded', String(! panel.hidden));
    });
});

document.addEventListener('keydown', (event) => {
    if (event.key === 'Escape') {
        document.querySelectorAll('.sfp-user-menu-panel').forEach((panel) => {
            panel.hidden = true;
        });
    }
});
