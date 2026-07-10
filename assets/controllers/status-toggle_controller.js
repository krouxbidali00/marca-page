import { Controller } from '@hotwired/stimulus';

/*
 * Toggles a book's reading- or purchase-status from its library card.
 * The clicked pill posts `toggle=1` to its action route; the server flips the
 * status and returns the re-rendered pill, which replaces the button in place.
 * The flip rule and the pill markup both live on the server — never duplicated here.
 */
export default class extends Controller {
    static values = { token: String };

    async toggle(event) {
        const button = event.currentTarget;
        const url = event.params.url;
        if (button.classList.contains('is-pending')) {
            return;
        }
        button.classList.add('is-pending');
        button.disabled = true;

        try {
            const body = new FormData();
            body.append('_token', this.tokenValue);
            body.append('toggle', '1');
            const res = await fetch(url, {
                method: 'POST',
                headers: { 'X-Requested-With': 'XMLHttpRequest' },
                body,
            });
            if (!res.ok) {
                throw new Error('http-' + res.status);
            }
            const data = await res.json();
            // Swap the pill; Stimulus rebinds the action/target on the new node,
            // and the token value stays on the (unchanged) controller element.
            const hadFocus = document.activeElement === button;
            button.outerHTML = data.html;
            if (hadFocus) {
                this.element.querySelector(`[data-status-toggle-url-param="${url}"]`)?.focus();
            }
        } catch (e) {
            button.disabled = false;
            button.classList.remove('is-pending');
            button.classList.add('is-error');
            setTimeout(() => button.classList.remove('is-error'), 1500);
        }
    }
}
