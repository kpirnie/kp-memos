/**
 * KP Memos Notes Grid
 *
 * Live search and filtering (the server renders the result fragments),
 * pin toggling, and pointer-based drag reordering of pinned notes that
 * works with mouse, pen, and touch.
 *
 * @since 8.5
 * @author Kevin Pirnie <me@kpirnie.com>
 * @package KP Memos
 */

(() => {
    'use strict';

    // hold the elements
    const form = document.getElementById('kpm-filters');
    const results = document.getElementById('kpm-results');
    if (!form || !results) {
        return;
    }
    const panel = document.getElementById('kpm-filter-panel');
    const toggle = form.querySelector('[data-toggle-filters]');
    const tagsField = form.querySelector('input[name="tags"]');

    // ---------------------------------------------------------------------
    // filtering
    // ---------------------------------------------------------------------

    /**
     * Build the query string from the form
     *
     * @param {number} page
     * @return {string}
     */
    const buildQuery = (page) => {
        tagsField.value = Array.from(form.querySelectorAll('input[name="tag"]:checked')).map((c) => c.value).join(',');
        const data = new FormData(form);
        data.delete('tag');
        const params = new URLSearchParams();
        for (const [key, value] of data.entries()) {
            if (value !== '' && !(key === 'category' && value === '0') && !(key === 'visibility' && value === 'all')
                && !(key === 'date_field' && value === 'updated')) {
                params.set(key, value);
            }
        }
        if (page > 1) {
            params.set('page', String(page));
        }
        return params.toString();
    };

    // only the latest request may render
    let requestId = 0;

    /**
     * Fetch and render the results
     *
     * @param {number}  page
     * @param {boolean} push Add a history entry instead of replacing
     */
    const refresh = async (page = 1, push = false) => {
        const id = ++requestId;
        const query = buildQuery(page);
        results.classList.add('kpm-loading');
        try {
            const data = await window.KPM.api('/notes' + (query ? '?' + query : ''));
            if (id !== requestId) {
                return;
            }
            results.innerHTML = data.html;
            const url = '/notes' + (data.query ? '?' + data.query : '');
            history[push ? 'pushState' : 'replaceState'](null, '', url);
            initSortable();
        } catch (err) {
            if (err.status === 401) {
                window.location.href = '/login';
            }
        } finally {
            if (id === requestId) {
                results.classList.remove('kpm-loading');
            }
        }
    };

    // search as you type, debounced; everything else applies immediately
    let debounce = 0;
    form.addEventListener('input', (e) => {
        if (e.target.name === 'q') {
            clearTimeout(debounce);
            debounce = setTimeout(() => {
                // switch to best match while searching, back to recent when cleared
                const sort = form.querySelector('select[name="sort"]');
                if (e.target.value.trim() !== '' && sort.value === 'updated') {
                    sort.value = 'relevance';
                } else if (e.target.value.trim() === '' && sort.value === 'relevance') {
                    sort.value = 'updated';
                }
                refresh(1);
            }, 300);
        }
    });
    form.addEventListener('change', (e) => {
        if (e.target.name !== 'q') {
            refresh(1);
        }
    });
    form.addEventListener('submit', (e) => {
        e.preventDefault();
        refresh(1);
    });

    // filter panel toggle
    toggle.addEventListener('click', () => {
        const open = toggle.getAttribute('aria-expanded') === 'true';
        toggle.setAttribute('aria-expanded', open ? 'false' : 'true');
        panel.hidden = open;
    });

    // reset
    document.addEventListener('click', (e) => {
        if (!e.target.closest('[data-reset]')) {
            return;
        }
        e.preventDefault();
        form.reset();
        form.querySelector('input[name="q"]').value = '';
        form.querySelector('select[name="category"]').value = '0';
        form.querySelector('input[name="visibility"][value="all"]').checked = true;
        form.querySelector('select[name="date_field"]').value = 'updated';
        form.querySelectorAll('input[type="date"]').forEach((i) => { i.value = ''; });
        form.querySelectorAll('input[name="tag"]').forEach((c) => { c.checked = false; });
        form.querySelector('select[name="sort"]').value = 'updated';
        refresh(1, true);
    });

    // chips on cards filter in place
    results.addEventListener('click', (e) => {
        const cat = e.target.closest('[data-filter-category]');
        const tag = e.target.closest('[data-filter-tag]');
        if (!cat && !tag) {
            return;
        }
        e.preventDefault();
        if (cat) {
            form.querySelector('select[name="category"]').value = cat.dataset.filterCategory;
        } else {
            const box = form.querySelector('input[name="tag"][value="' + CSS.escape(tag.dataset.filterTag) + '"]');
            if (box) {
                box.checked = true;
            }
        }
        panel.hidden = false;
        toggle.setAttribute('aria-expanded', 'true');
        refresh(1, true);
    });

    // pagination in place
    results.addEventListener('click', (e) => {
        const link = e.target.closest('.kpm-pager a');
        if (!link) {
            return;
        }
        e.preventDefault();
        const page = parseInt(new URL(link.href).searchParams.get('page') || '1', 10);
        refresh(page, true);
        results.scrollIntoView({ behavior: 'smooth', block: 'start' });
    });

    // back / forward
    window.addEventListener('popstate', () => window.location.reload());

    // ---------------------------------------------------------------------
    // pinning
    // ---------------------------------------------------------------------
    results.addEventListener('click', async (e) => {
        const button = e.target.closest('[data-pin]');
        if (!button) {
            return;
        }
        e.preventDefault();
        const card = button.closest('[data-id]');
        button.disabled = true;
        try {
            await window.KPM.api('/notes/' + card.dataset.id + '/pin', {
                method: 'POST',
                body: { pinned: button.dataset.pin === '1' },
            });
            const page = parseInt(new URLSearchParams(window.location.search).get('page') || '1', 10);
            await refresh(page);
        } catch (err) {
            button.disabled = false;
            window.alert(err.message);
        }
    });

    // ---------------------------------------------------------------------
    // drag reordering of pinned notes
    // ---------------------------------------------------------------------

    /**
     * Wire up pointer dragging on the pinned grid, when it's sortable
     */
    function initSortable() {
        const grid = results.querySelector('[data-pinned-grid][data-sortable]');
        if (!grid) {
            return;
        }

        let drag = null;

        // start dragging from a handle
        grid.addEventListener('pointerdown', (e) => {
            const handle = e.target.closest('[data-drag-handle]');
            if (!handle || e.button !== 0) {
                return;
            }
            e.preventDefault();
            const card = handle.closest('.kpm-note-card');
            const rect = card.getBoundingClientRect();

            // a placeholder holds the card's slot while it floats
            const placeholder = document.createElement('div');
            placeholder.className = 'kpm-note-card kpm-drop-slot';
            placeholder.style.height = rect.height + 'px';
            card.after(placeholder);

            // float the card under the pointer
            card.classList.add('kpm-dragging');
            card.style.width = rect.width + 'px';
            card.style.height = rect.height + 'px';
            card.style.left = rect.left + 'px';
            card.style.top = rect.top + 'px';
            document.body.appendChild(card);

            drag = { card, placeholder, dx: e.clientX - rect.left, dy: e.clientY - rect.top, before: order() };

            // the card now lives outside the grid, so listen on the document until the drop
            document.addEventListener('pointermove', move);
            document.addEventListener('pointerup', finish);
            document.addEventListener('pointercancel', finish);
        });

        // follow the pointer and move the slot to where it would land
        const move = (e) => {
            if (!drag) {
                return;
            }
            drag.card.style.left = (e.clientX - drag.dx) + 'px';
            drag.card.style.top = (e.clientY - drag.dy) + 'px';

            const over = document.elementFromPoint(e.clientX, e.clientY);
            const target = over ? over.closest('[data-pinned-grid] .kpm-note-card:not(.kpm-drop-slot)') : null;
            if (!target) {
                return;
            }
            const r = target.getBoundingClientRect();
            const after = (e.clientY > r.top + r.height / 2) || (e.clientX > r.left + r.width / 2 && e.clientY > r.top);
            target[after ? 'after' : 'before'](drag.placeholder);
        };

        // drop into the slot and save the new order
        const finish = async () => {
            if (!drag) {
                return;
            }
            const { card, placeholder, before } = drag;
            drag = null;
            document.removeEventListener('pointermove', move);
            document.removeEventListener('pointerup', finish);
            document.removeEventListener('pointercancel', finish);
            card.classList.remove('kpm-dragging');
            card.removeAttribute('style');
            placeholder.replaceWith(card);

            const ids = order();
            if (ids.join(',') === before.join(',')) {
                return;
            }
            try {
                await window.KPM.api('/notes/pin-order', { method: 'POST', body: { ids } });
            } catch (err) {
                window.alert('The new order could not be saved.');
                refresh(1);
            }
        };

        /**
         * The pinned ids in their current order
         *
         * @return {number[]}
         */
        function order() {
            return Array.from(grid.querySelectorAll('.kpm-note-card[data-id]')).map((c) => parseInt(c.dataset.id, 10));
        }
    }

    initSortable();
})();
