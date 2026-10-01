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
