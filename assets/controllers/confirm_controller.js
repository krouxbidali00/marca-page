import { Controller } from '@hotwired/stimulus';
import { confirmDialog } from '../confirm.js';

/*
 * Declarative confirmation gate for form submissions: intercepts submit, asks for
 * confirmation via the shared modal (assets/confirm.js), and resubmits only if the
 * user confirms. requestSubmit() (not submit()) replays HTML5 validation and Turbo;
 * the `confirmed` instance flag lets the second, confirmed submit pass through.
 */
export default class extends Controller {
    confirmed = false;

    static values = {
        title: String,
        message: String,
        confirmLabel: { type: String, default: 'Confirmer' },
        variant: { type: String, default: 'danger' },
    };

    async gate(event) {
        if (this.confirmed) {
            this.confirmed = false;
            return;
        }
        event.preventDefault();
        const ok = await confirmDialog({
            title: this.titleValue,
            message: this.messageValue,
            confirmLabel: this.confirmLabelValue,
            variant: this.variantValue,
        });
        if (!ok) {
            return;
        }
        this.confirmed = true;
        this.element.requestSubmit();
    }
}
