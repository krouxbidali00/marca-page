import { Controller } from '@hotwired/stimulus';

/* Show the "current password" field only when the email is being changed. */
export default class extends Controller {
    static targets = ['email', 'passwordRow'];
    static values = { originalEmail: String };

    connect() { this.update(); }

    update() {
        const changed = this.emailTarget.value.trim() !== this.originalEmailValue;
        this.passwordRowTarget.hidden = !changed;
        const input = this.passwordRowTarget.querySelector('input');
        if (input) input.required = changed;
    }
}
