import { Controller } from '@hotwired/stimulus';
import { Modal } from 'bootstrap';

/*
 * Renames a book's title from the library grid via a shared modal.
 * Each card's pencil button passes id/title/token through data-title-edit-*-param;
 * this controller fills the modal, submits over XHR and writes the new title
 * back into the originating card without a page reload.
 */
export default class extends Controller {
    static targets = ['modal', 'form', 'input', 'token', 'error'];

    open(event) {
        const { id, title, token } = event.params;
        this.titleEl = event.currentTarget.closest('.book-card-wrap')?.querySelector('[data-book-title]');
        this.formTarget.action = `/books/${id}/title`;
        this.inputTarget.value = title;
        this.tokenTarget.value = token;
        this.hideError();
        Modal.getOrCreateInstance(this.modalTarget).show();
        this.inputTarget.focus();
        this.inputTarget.select();
    }

    async submit(event) {
        event.preventDefault();
        this.hideError();
        try {
            const res = await fetch(this.formTarget.action, {
                method: 'POST',
                headers: { 'X-Requested-With': 'XMLHttpRequest' },
                body: new FormData(this.formTarget),
            });
            if (res.status === 422) {
                const data = await res.json();
                this.showError(data.error || 'Titre invalide.');
                return;
            }
            if (!res.ok) {
                throw new Error('http-' + res.status);
            }
            const data = await res.json();
            if (this.titleEl) {
                this.titleEl.textContent = data.title;
            }
            Modal.getOrCreateInstance(this.modalTarget).hide();
        } catch (e) {
            this.showError('Une erreur est survenue. Réessayez.');
        }
    }

    showError(message) {
        this.errorTarget.textContent = message;
        this.errorTarget.classList.remove('d-none');
    }

    hideError() {
        this.errorTarget.textContent = '';
        this.errorTarget.classList.add('d-none');
    }
}
