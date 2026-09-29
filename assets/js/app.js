/**
 * KP Memos Core Script
 *
 * Shared behaviour for every page: navigation toggle, csrf-aware fetch,
 * and confirm prompts.
 *
 * @since 8.5
 * @author Kevin Pirnie <me@kpirnie.com>
 * @package KP Memos
 */

(() => {
    'use strict';

    // hold the csrf token from the page meta
    const csrfMeta = document.querySelector('meta[name="kpm-csrf"]');
    const csrf = csrfMeta ? csrfMeta.getAttribute('content') : '';

    /**
     * Fetch wrapper that always sends the csrf token and same-origin credentials
     *
     * @param {string} url     The url to call
     * @param {object} options Standard fetch options
     * @return {Promise<object>} The decoded json response
     */
    const api = async (url, options = {}) => {

        // merge the headers
        const headers = Object.assign({
            'Accept': 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
            'X-CSRF-Token': csrf,
        }, options.headers || {});

        // send json bodies as json
        let body = options.body;
        if (body && !(body instanceof FormData) && typeof body !== 'string') {
            headers['Content-Type'] = 'application/json';
            body = JSON.stringify(body);
        }

        // make the call
        const response = await fetch(url, Object.assign({}, options, {
            headers,
            body,
            credentials: 'same-origin',
            redirect: 'error',
        }));

        // decode the response
        let data = {};
        try {
            data = await response.json();
        } catch (e) {
            data = {};
        }

        // throw on failure so callers can catch
        if (!response.ok || data.success === false) {
            const err = new Error(data.message || 'Request failed');
            err.status = response.status;
            err.data = data;
            throw err;
        }
        return data;
    };

    // expose the helpers
    window.KPM = Object.assign(window.KPM || {}, { api, csrf });

    // mobile navigation toggle
    const toggle = document.querySelector('.kpm-nav-toggle');
    const links = document.getElementById('kpm-nav-links');
    if (toggle && links) {
        toggle.addEventListener('click', () => {
            const open = toggle.getAttribute('aria-expanded') === 'true';
            toggle.setAttribute('aria-expanded', open ? 'false' : 'true');
            links.classList.toggle('kpm-open', !open);
        });
    }

    // register the service worker (online-only; caches the static shell alone)
    if ('serviceWorker' in navigator) {
        window.addEventListener('load', () => {
            navigator.serviceWorker.register('/sw.js', { scope: '/' }).catch(() => {});
        });
    }

    // confirm prompts on any form or button carrying data-confirm
    document.addEventListener('submit', (e) => {
        const form = e.target;
        const message = form.getAttribute('data-confirm');
        if (message && !window.confirm(message)) {
            e.preventDefault();
        }
    });
})();

(() => {
    'use strict';

    /**
     * Get the text of the element a copy / download button points at
     *
     * @param {HTMLElement} button The button
     * @param {string}      attr   The attribute holding the selector
     * @return {string}
     */
    const targetText = (button, attr) => {
        const target = document.querySelector(button.getAttribute(attr));
        if (!target) {
            return '';
        }
        if ('value' in target && target.tagName !== 'BUTTON') {
            return target.value;
        }
        const items = target.querySelectorAll('li');
        return items.length ? Array.from(items).map((li) => li.textContent.trim()).join('\n') : target.textContent.trim();
    };

    // copy buttons
    document.addEventListener('click', async (e) => {
        const button = e.target.closest('[data-copy]');
        if (!button) {
            return;
        }
        try {
            await navigator.clipboard.writeText(targetText(button, 'data-copy'));
            const label = button.textContent;
            button.textContent = 'Copied';
            setTimeout(() => { button.textContent = label; }, 1500);
        } catch (err) {
            window.alert('Copy failed; select the text and copy it manually.');
        }
    });

    // download buttons
    document.addEventListener('click', (e) => {
        const button = e.target.closest('[data-download]');
        if (!button) {
            return;
        }
        const blob = new Blob([targetText(button, 'data-download') + '\n'], { type: 'text/plain' });
        const link = document.createElement('a');
        link.href = URL.createObjectURL(blob);
        link.download = button.getAttribute('data-filename') || 'download.txt';
        document.body.appendChild(link);
        link.click();
        link.remove();
        setTimeout(() => URL.revokeObjectURL(link.href), 1000);
    });
})();
