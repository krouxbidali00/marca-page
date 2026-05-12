import { Controller } from '@hotwired/stimulus';

/* Debounced live search of the Google Books catalogue; renders the results fragment. */
export default class extends Controller {
    static targets = ['input', 'results'];
    static values = { url: String, delay: { type: Number, default: 350 } };

    connect() {
        this.timer = null;
    }

    disconnect() {
        clearTimeout(this.timer);
    }

    schedule() {
        clearTimeout(this.timer);
        this.timer = setTimeout(() => this.run(), this.delayValue);
    }

    async run(event) {
        if (event) {
            event.preventDefault();
        }
        const q = this.inputTarget.value.trim();
        try {
            const res = await fetch(`${this.urlValue}?fragment=1&q=${encodeURIComponent(q)}`, {
                headers: { 'X-Requested-With': 'XMLHttpRequest' },
            });
            this.resultsTarget.innerHTML = await res.text();
        } catch (e) {
            // Keep whatever was rendered server-side.
        }
    }
}
