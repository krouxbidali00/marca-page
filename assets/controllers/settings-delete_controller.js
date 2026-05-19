import { Controller } from '@hotwired/stimulus';

/* Intercepts the delete form submit to require an explicit native confirm. */
export default class extends Controller {
    confirm(event) {
        if (!window.confirm('Êtes-vous certain·e ? Cette action est définitive et supprimera tous vos livres, citations et étagères.')) {
            event.preventDefault();
        }
    }
}
