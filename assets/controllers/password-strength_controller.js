import { Controller } from '@hotwired/stimulus';

/* Live password-strength meter on the signup form. */
export default class extends Controller {
    static targets = ['input', 'bar', 'label'];

    connect() {
        this.evaluate();
    }

    evaluate() {
        const v = this.hasInputTarget ? this.inputTarget.value : '';
        let s = 0;
        if (v.length >= 8) s++;
        if (/[A-Z]/.test(v)) s++;
        if (/\d/.test(v)) s++;
        if (/[^A-Za-z0-9]/.test(v) && v.length >= 12) s++;

        if (this.hasBarTarget) {
            this.barTarget.className = `strength s${s}`;
        }
        if (this.hasLabelTarget) {
            this.labelTarget.textContent = ['—', 'Faible', 'Correct', 'Bien', 'Excellent'][s];
        }
    }
}
