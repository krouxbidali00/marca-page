import { Controller } from '@hotwired/stimulus';

/* Imports a Google Books volume via XHR so the user stays on the search page. */
export default class extends Controller {
    static targets = ['button'];

    async submit(event) {
        event.preventDefault();
        if (this.buttonTarget.disabled) {
            return;
        }
        this.setPending();
        try {
            const res = await fetch(this.element.action, {
                method: 'POST',
                headers: { 'X-Requested-With': 'XMLHttpRequest' },
                body: new FormData(this.element),
            });
            if (!res.ok) {
                throw new Error('http-' + res.status);
            }
            this.setAdded();
        } catch (e) {
            this.setError();
        }
    }

    setPending() {
        this.buttonTarget.disabled = true;
        this.buttonTarget.innerHTML = '<span class="spinner-border spinner-border-sm me-1" aria-hidden="true"></span> Ajout…';
    }

    setAdded() {
        const btn = this.buttonTarget;
        btn.classList.remove('btn-primary', 'btn-danger');
        btn.classList.add('btn-success');
        btn.innerHTML = '<i class="bi bi-check-lg me-1"></i> Ajouté';
        btn.disabled = true;
    }

    setError() {
        const btn = this.buttonTarget;
        btn.classList.remove('btn-primary', 'btn-success');
        btn.classList.add('btn-danger');
        btn.innerHTML = '<i class="bi bi-exclamation-triangle me-1"></i> Réessayer';
        btn.disabled = false;
    }
}
