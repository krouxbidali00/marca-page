import { Modal } from 'bootstrap';

/*
 * Shared, promise-based confirmation dialog backed by the #confirm-modal markup
 * rendered once in base.html.twig. Resolves true when the user confirms, false
 * when the modal is dismissed (cancel / Escape / backdrop).
 *
 * Single-in-flight: it drives one shared modal, so callers must not open a second
 * dialog while one is pending. The UI already enforces this (Bootstrap's backdrop
 * blocks interaction while the modal is open).
 */
export function confirmDialog({ title = '', message = '', confirmLabel = 'Confirmer', variant = 'danger' } = {}) {
    const modalEl = document.getElementById('confirm-modal');
    const titleEl = document.getElementById('confirm-modal-title');
    const messageEl = document.getElementById('confirm-modal-message');
    const confirmBtn = document.getElementById('confirm-modal-confirm');

    titleEl.textContent = title;
    messageEl.textContent = message;
    confirmBtn.textContent = confirmLabel;
    confirmBtn.className = `btn btn-${variant}`;

    const modal = Modal.getOrCreateInstance(modalEl);

    return new Promise((resolve) => {
        let confirmed = false;

        const onConfirm = () => {
            confirmed = true;
            modal.hide();
        };

        confirmBtn.addEventListener('click', onConfirm, { once: true });

        modalEl.addEventListener('hidden.bs.modal', () => {
            // Drop the unfired confirm listener on the dismiss path (on the confirm
            // path { once: true } has already detached it — this keeps repeated
            // cancels from accumulating listeners on the shared modal).
            confirmBtn.removeEventListener('click', onConfirm);
            resolve(confirmed);
        }, { once: true });

        modal.show();
    });
}
