import { Controller } from '@hotwired/stimulus';

// Persisted library view state (search + filters + sort, minus pagination),
// remembered across navigation until the user resets. Sibling to 'library-view'.
const FILTERS_KEY = 'library-filters';

/* Owns library page state: search, filters, sort, pagination, grid/list view.
   One XHR loop refreshes the results fragment on any change. */
export default class extends Controller {
    static targets = ['form', 'search', 'results', 'sortLabel', 'sortItem', 'viewButton', 'grid', 'counter'];
    static values = {
        url: String,
        initialSort: { type: String, default: 'recent' },
        debounce: { type: Number, default: 350 },
    };

    connect() {
        this.currentSort = this.initialSortValue || 'recent';
        this.currentPage = 1;
        this.searchTimer = null;

        this.viewMode = localStorage.getItem('library-view') === 'list' ? 'list' : 'grid';
        this.applyViewMode();

        this.onPopState = () => this.syncFromUrl();
        window.addEventListener('popstate', this.onPopState);
    }

    disconnect() {
        clearTimeout(this.searchTimer);
        window.removeEventListener('popstate', this.onPopState);
    }

    // --- Event handlers (bound via data-action in templates) ---

    searchInput() {
        clearTimeout(this.searchTimer);
        this.searchTimer = setTimeout(() => {
            this.currentPage = 1;
            this.refresh();
        }, this.debounceValue);
    }

    submitSearch(event) {
        event.preventDefault();
        clearTimeout(this.searchTimer);
        this.currentPage = 1;
        this.refresh();
    }

    submitFilters(event) {
        // The form has no visible submit, but pressing Enter on a future text input would submit it.
        event.preventDefault();
    }

    applyFilters(event) {
        const form = event.currentTarget.closest('form');
        this.mirrorForm(form);
        this.currentPage = 1;
        this.refresh();
    }

    selectSort(event) {
        event.preventDefault();
        const item = event.currentTarget;
        this.currentSort = item.dataset.sort;
        this.sortLabelTarget.textContent = item.dataset.sortLabel;
        this.sortItemTargets.forEach(el => el.classList.toggle('active', el === item));
        this.currentPage = 1;
        this.refresh();
    }

    setView(event) {
        this.viewMode = event.currentTarget.dataset.view;
        localStorage.setItem('library-view', this.viewMode);
        this.applyViewMode();
    }

    resetAll(event) {
        event.preventDefault();
        this.formTargets.forEach(form => {
            form.querySelectorAll('input[type="checkbox"]').forEach(cb => { cb.checked = false; });
            form.querySelectorAll('select').forEach(sel => { sel.value = ''; });
            form.querySelectorAll('input[type="range"]').forEach(r => { r.value = r.max; });
        });
        this.searchTarget.value = '';
        this.currentSort = 'recent';
        this.currentPage = 1;
        this.refresh();
    }

    // --- Delegated click handlers (chips + pagination) bound in results fragment ---

    resultsTargetConnected(el) {
        el.addEventListener('click', this.onResultsClick);
    }

    resultsTargetDisconnected(el) {
        el.removeEventListener('click', this.onResultsClick);
    }

    onResultsClick = (event) => {
        const link = event.target.closest('a');
        if (!link) return;

        // Pagination link
        if (link.classList.contains('page-link') && link.getAttribute('href') && link.getAttribute('href') !== '#') {
            event.preventDefault();
            this.applyUrl(link.getAttribute('href'));
            return;
        }

        // Active chip or "Réinitialiser" link
        if (link.classList.contains('chip') || link.classList.contains('text-mute')) {
            event.preventDefault();
            this.applyUrl(link.getAttribute('href'));
        }
    };

    // --- Core ---

    refresh() {
        this.saveState();
        const params = this.buildParams();
        const url = params.toString() ? `${this.urlValue}?${params}` : this.urlValue;
        this.fetchAndReplace(url, true);
    }

    // Persist current state (minus page) so it survives navigation. Empty state
    // removes the key, which is exactly what both reset paths produce — so
    // "Tout effacer" and the "Réinitialiser" chip clear the preference for free.
    saveState() {
        const params = this.buildParams();
        params.delete('page');
        const qs = params.toString();
        if (qs) {
            localStorage.setItem(FILTERS_KEY, qs);
        } else {
            localStorage.removeItem(FILTERS_KEY);
        }
    }

    applyUrl(href) {
        // href is like "/bibliotheque?reading%5B%5D=reading&page=2"
        const url = new URL(href, window.location.origin);
        this.syncFormsFromParams(url.searchParams);
        this.searchTarget.value = url.searchParams.get('q') || '';
        this.currentSort = url.searchParams.get('sort') || 'recent';
        this.currentPage = parseInt(url.searchParams.get('page') || '1', 10);
        this.updateSortLabel();
        this.saveState();
        this.fetchAndReplace(href, true);
    }

    syncFromUrl() {
        const url = new URL(window.location.href);
        this.syncFormsFromParams(url.searchParams);
        this.searchTarget.value = url.searchParams.get('q') || '';
        this.currentSort = url.searchParams.get('sort') || 'recent';
        this.currentPage = parseInt(url.searchParams.get('page') || '1', 10);
        this.updateSortLabel();
        this.fetchAndReplace(window.location.href, false);
    }

    async fetchAndReplace(url, pushHistory) {
        try {
            const res = await fetch(url, { headers: { 'X-Requested-With': 'XMLHttpRequest' } });
            if (!res.ok) return;
            this.resultsTarget.innerHTML = await res.text();
            if (pushHistory) {
                history.pushState({}, '', url);
            }
            this.applyViewMode();
        } catch (e) {
            // Network failure — leave the current fragment in place.
        }
    }

    buildParams() {
        const params = new URLSearchParams();
        const form = this.formTargets[0]; // sidebar is the source of truth
        if (!form) return params;

        const q = this.searchTarget.value.trim();
        if (q) params.set('q', q);

        form.querySelectorAll('input[type="checkbox"]:checked').forEach(cb => {
            params.append(cb.name, cb.value);
        });

        form.querySelectorAll('select').forEach(sel => {
            if (sel.value !== '') params.set(sel.name, sel.value);
        });

        form.querySelectorAll('input[type="range"]').forEach(r => {
            const v = parseInt(r.value, 10);
            if (v > 0 && v < parseInt(r.max, 10)) {
                params.set(r.name, String(v));
            }
        });

        if (this.currentSort && this.currentSort !== 'recent') {
            params.set('sort', this.currentSort);
        }
        if (this.currentPage > 1) {
            params.set('page', String(this.currentPage));
        }

        return params;
    }

    mirrorForm(sourceForm) {
        this.formTargets.forEach(target => {
            if (target === sourceForm) return;
            target.querySelectorAll('input, select').forEach(input => {
                if (input.type === 'checkbox') {
                    const sourceCb = sourceForm.querySelector(`input[name="${input.name}"][value="${CSS.escape(input.value)}"]`);
                    if (sourceCb) input.checked = sourceCb.checked;
                } else {
                    const sourceEl = sourceForm.querySelector(`[name="${input.name}"]`);
                    if (sourceEl) input.value = sourceEl.value;
                }
            });
        });
    }

    syncFormsFromParams(searchParams) {
        const readingVals = searchParams.getAll('reading[]').concat(searchParams.getAll('reading'));
        const purchaseVals = searchParams.getAll('purchase[]').concat(searchParams.getAll('purchase'));
        const categoryVals = searchParams.getAll('category[]').concat(searchParams.getAll('category'));

        this.formTargets.forEach(form => {
            form.querySelectorAll('input[name="reading[]"]').forEach(cb => { cb.checked = readingVals.includes(cb.value); });
            form.querySelectorAll('input[name="purchase[]"]').forEach(cb => { cb.checked = purchaseVals.includes(cb.value); });
            form.querySelectorAll('input[name="category[]"]').forEach(cb => { cb.checked = categoryVals.includes(cb.value); });

            const minRating = form.querySelector('select[name="minRating"]');
            if (minRating) minRating.value = searchParams.get('minRating') || '';

            const shelf = form.querySelector('select[name="shelf"]');
            if (shelf) shelf.value = searchParams.get('shelf') || '';

            const maxPages = form.querySelector('input[name="maxPages"]');
            if (maxPages) maxPages.value = searchParams.get('maxPages') || maxPages.max;
        });
    }

    updateSortLabel() {
        const item = this.sortItemTargets.find(el => el.dataset.sort === this.currentSort);
        if (item) {
            this.sortLabelTarget.textContent = item.dataset.sortLabel;
            this.sortItemTargets.forEach(el => el.classList.toggle('active', el === item));
        }
    }

    applyViewMode() {
        const grid = this.element.querySelector('[data-library-target="grid"]');
        if (grid) {
            grid.classList.toggle('view--list', this.viewMode === 'list');
        }
        this.viewButtonTargets.forEach(btn => {
            btn.classList.toggle('active', btn.dataset.view === this.viewMode);
        });
    }
}
