/**
 * KP Memos Service Worker
 *
 * Online only by design: notes, pages, and api responses are never cached,
 * so nothing private is ever stored on the device. Only the static shell
 * (styles, scripts, fonts, icons) is cached for speed, and a navigation
 * that fails while offline gets a small "you're offline" page.
 *
 * @since 8.5
 * @author Kevin Pirnie <me@kpirnie.com>
 * @package KP Memos
 */

'use strict';

// bump to drop every old static cache
const CACHE = 'kpm-static-v1';

// the static shell
const SHELL = [
    '/assets/css/app.css',
    '/assets/js/app.js',
    '/assets/img/logo.svg',
    '/assets/img/icon-192.png',
    '/assets/fonts/sora-latin-wght-normal.woff2',
    '/assets/fonts/orbitron-latin-wght-normal.woff2',
    '/assets/fonts/jetbrains-mono-latin-wght-normal.woff2',
];

// shown when a page can't be reached
const OFFLINE_HTML = '<!DOCTYPE html><html lang="en"><head><meta charset="utf-8">'
    + '<meta name="viewport" content="width=device-width, initial-scale=1"><title>Offline · KP Memos</title>'
    + '<style>body{margin:0;min-height:100vh;display:grid;place-items:center;background:#070e20;color:#dde8f5;'
    + 'font-family:system-ui,sans-serif;text-align:center;padding:24px}h1{color:#fff;font-size:1.4rem}'
    + 'p{color:#6b8cae}button{margin-top:16px;background:linear-gradient(135deg,#2b8eff,#1a6fd4);color:#fff;'
    + 'border:0;border-radius:10px;padding:12px 24px;font-weight:600;cursor:pointer}</style></head><body><div>'
    + '<h1>You\'re offline</h1><p>KP Memos needs a connection. Your notes are never stored on this device.</p>'
    + '<button onclick="location.reload()">Try again</button></div></body></html>';

// cache the shell
self.addEventListener('install', (event) => {
    event.waitUntil(caches.open(CACHE).then((cache) => cache.addAll(SHELL)).then(() => self.skipWaiting()));
});

// drop old caches and take control
self.addEventListener('activate', (event) => {
    event.waitUntil(
        caches.keys()
            .then((keys) => Promise.all(keys.filter((key) => key !== CACHE).map((key) => caches.delete(key))))
            .then(() => self.clients.claim())
    );
});

// route requests
self.addEventListener('fetch', (event) => {
    const request = event.request;
    const url = new URL(request.url);

    // only same-origin gets
    if (request.method !== 'GET' || url.origin !== self.location.origin) {
        return;
    }

    // static assets: cache first, filled on demand; versioned urls make this safe
    if (url.pathname.startsWith('/assets/')) {
        event.respondWith(
            caches.open(CACHE).then(async (cache) => {
                const hit = await cache.match(request, { ignoreSearch: false }) || await cache.match(url.pathname);
                if (hit) {
                    return hit;
                }
                const response = await fetch(request);
                if (response.ok && response.type === 'basic') {
                    cache.put(request, response.clone());
                }
                return response;
            })
        );
        return;
    }

    // pages: always the network; offline gets the notice, never a cached page
    if (request.mode === 'navigate') {
        event.respondWith(
            fetch(request).catch(() => new Response(OFFLINE_HTML, {
                status: 503,
                headers: { 'Content-Type': 'text/html; charset=utf-8', 'Cache-Control': 'no-store' },
            }))
        );
    }

    // everything else (api calls, downloads) passes straight through, uncached
});
