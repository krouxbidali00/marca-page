import { Controller } from '@hotwired/stimulus';

/* Debounced autosave for the personal-notes textarea. */
export default class extends Controller {
    static targets = ['field', 'status'];
    static values = { url: String, token: String, delay: { type: Number, default: 1500 } };

    connect() {
        this.timer = null;
    }

    disconnect() {
        clearTimeout(this.timer);
    }

    schedule() {
        clearTimeout(this.timer);
        this.setStatus('Modifications non enregistrées…');
        this.timer = setTimeout(() => this.save(), this.delayValue);
    }

    async save() {
        try {
            await fetch(this.urlValue, {
                method: 'POST',
                headers: { 'X-Requested-With': 'XMLHttpRequest' },
                body: new URLSearchParams({ personalNotes: this.fieldTarget.value, _token: this.tokenValue }),
            });
            this.setStatus('Enregistré à l’instant');
        } catch (e) {
            this.setStatus('Échec de la sauvegarde — utilisez « Enregistrer ».');
        }
    }

    setStatus(text) {
        if (this.hasStatusTarget) {
            this.statusTarget.textContent = text;
        }
    }
}
