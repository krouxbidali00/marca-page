import { Controller } from '@hotwired/stimulus';

/* Shows / hides a password field. */
export default class extends Controller {
    static targets = ['input', 'icon'];

    toggle() {
        const show = this.inputTarget.type === 'password';
        this.inputTarget.type = show ? 'text' : 'password';
        if (this.hasIconTarget) {
            this.iconTarget.className = show ? 'bi bi-eye-slash' : 'bi bi-eye';
        }
    }
}
