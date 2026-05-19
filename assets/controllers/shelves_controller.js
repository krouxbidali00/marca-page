import { Controller } from '@hotwired/stimulus';
import { Modal } from 'bootstrap';

/**
 * Wires the kebab actions on /shelves to the shared rename/delete modals.
 * The dropdown buttons pass id/name/token via data-shelves-*-param; this controller
 * injects them into the modal form before opening it via Bootstrap's API.
 */
export default class extends Controller {
    static targets = [
        'renameModal', 'renameForm', 'renameInput', 'renameToken', 'renameLabel',
        'deleteModal', 'deleteForm', 'deleteToken', 'deleteLabel',
    ];

    openRename({ params: { id, name, token } }) {
        this.renameFormTarget.action = `/shelves/${id}/rename`;
        this.renameInputTarget.value = name;
        this.renameTokenTarget.value = token;
        this.renameLabelTarget.textContent = name;
        Modal.getOrCreateInstance(this.renameModalTarget).show();
    }

    openDelete({ params: { id, name, token } }) {
        this.deleteFormTarget.action = `/shelves/${id}/delete`;
        this.deleteTokenTarget.value = token;
        this.deleteLabelTarget.textContent = name;
        Modal.getOrCreateInstance(this.deleteModalTarget).show();
    }
}
