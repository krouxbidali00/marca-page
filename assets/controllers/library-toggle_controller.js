import { Controller } from '@hotwired/stimulus';
import { confirmDialog } from '../confirm.js';

/*
 * Toggles a search result between "add to library" and "in library / remove",
 * via XHR, without leaving the search page.
 */
export default class extends Controller {
    static targets = ['add', 'ownedBadge', 'remove'];
    static values = {
        importUrl: String,
        importToken: String,
        volumeId: String,
        query: String,
        deleteUrlTemplate: String,
        bookId: Number,
        deleteToken: String,
    };

    async add(event) {
        event.preventDefault();
        this.setPending(this.addTarget, 'Ajout…');
        try {
            const body = new FormData();
            body.append('_token', this.importTokenValue);
            body.append('volumeId', this.volumeIdValue);
            body.append('q', this.queryValue);
            const res = await fetch(this.importUrlValue, {
                method: 'POST',
                headers: { 'X-Requested-With': 'XMLHttpRequest' },
                body,
            });
            if (!res.ok) {
                throw new Error('http-' + res.status);
            }
            const data = await res.json();
            this.bookIdValue = data.id;
            this.deleteTokenValue = data.deleteToken;
            this.showOwned();
        } catch (e) {
            this.setError(this.addTarget);
        }
    }

    async remove(event) {
        event.preventDefault();
        const confirmed = await confirmDialog({
            title: 'Retirer ce livre ?',
            message: 'Vos notes, citations et votre note seront supprimées.',
            confirmLabel: 'Retirer',
            variant: 'danger',
        });
        if (!confirmed) {
            return;
        }
        this.setPending(this.removeTarget, 'Retrait…');
        try {
            const url = this.deleteUrlTemplateValue.replace('/0/', `/${this.bookIdValue}/`);
            const body = new FormData();
            body.append('_token', this.deleteTokenValue);
            const res = await fetch(url, {
                method: 'POST',
                headers: { 'X-Requested-With': 'XMLHttpRequest' },
                body,
            });
            if (!res.ok) {
                throw new Error('http-' + res.status);
            }
            this.bookIdValue = 0;
            this.deleteTokenValue = '';
            this.showAdd();
        } catch (e) {
            this.setError(this.removeTarget);
        }
    }

    showOwned() {
        this.addTarget.classList.add('d-none');
        this.ownedBadgeTarget.classList.remove('d-none');
        this.removeTarget.classList.remove('d-none');
        this.resetRemove();
    }

    showAdd() {
        this.ownedBadgeTarget.classList.add('d-none');
        this.removeTarget.classList.add('d-none');
        this.addTarget.classList.remove('d-none');
        this.resetAdd();
    }

    setPending(btn, label) {
        btn.disabled = true;
        btn.innerHTML = `<span class="spinner-border spinner-border-sm me-1" aria-hidden="true"></span> ${label}`;
    }

    setError(btn) {
        btn.disabled = false;
        btn.classList.remove('btn-primary', 'btn-outline-danger');
        btn.classList.add('btn-danger');
        btn.innerHTML = '<i class="bi bi-exclamation-triangle me-1"></i> Réessayer';
    }

    resetAdd() {
        this.addTarget.disabled = false;
        this.addTarget.classList.remove('btn-danger');
        this.addTarget.classList.add('btn-primary');
        this.addTarget.innerHTML = '<i class="bi bi-plus-lg me-1"></i> Ajouter';
    }

    resetRemove() {
        this.removeTarget.disabled = false;
        this.removeTarget.classList.remove('btn-danger');
        this.removeTarget.classList.add('btn-outline-danger');
        this.removeTarget.innerHTML = '<i class="bi bi-trash me-1"></i> Retirer';
    }
}
