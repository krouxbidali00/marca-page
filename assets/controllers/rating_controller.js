import { Controller } from '@hotwired/stimulus';

/* Clickable star rating with optimistic UI; persists via a background POST. */
export default class extends Controller {
    static targets = ['star', 'output'];
    static values = { url: String, token: String, current: Number };

    connect() {
        this.render(this.currentValue || 0);
    }

    hover(event) {
        this.render(Number(event.params.value));
    }

    reset() {
        this.render(this.currentValue || 0);
    }

    async pick(event) {
        const value = Number(event.params.value);
        this.currentValue = value;
        this.render(value);

        try {
            await fetch(this.urlValue, {
                method: 'POST',
                headers: { 'X-Requested-With': 'XMLHttpRequest' },
                body: new URLSearchParams({ rating: String(value), _token: this.tokenValue }),
            });
        } catch (e) {
            // Network failure: the next page load will reflect the server state.
        }
    }

    render(value) {
        this.starTargets.forEach((star, i) => {
            const icon = star.querySelector('i');
            icon.classList.toggle('bi-star-fill', i < value);
            icon.classList.toggle('bi-star', i >= value);
        });
        if (this.hasOutputTarget) {
            this.outputTarget.textContent = String(value);
        }
    }
}
