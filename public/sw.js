/*
 * Leverage service worker.
 *
 * What it does:
 *   - makes the app installable and shows a friendly offline page when the network is down;
 *   - caches hashed build assets (/build/*) and listing photos (/storage/*) so repeat visits are fast.
 *
 * What it deliberately does NOT do: cache HTML, API/Livewire responses, or anything behind a login.
 * This app moves money, so a stale or another-user's cached page is a bug we refuse to risk. Every
 * navigation goes to the network; the cached offline page is only a fallback when that fails.
 *
 * Bump VERSION to force clients to drop old caches.
 */
const VERSION = 'v1';
const STATIC_CACHE = `static-${VERSION}`;
const IMAGE_CACHE = `images-${VERSION}`;
const OFFLINE_URL = '/offline.html';
const IMAGE_LIMIT = 120;

const PRECACHE = [OFFLINE_URL, '/manifest.webmanifest', '/icons/icon-192.png', '/icons/icon-512.png'];

self.addEventListener('install', (event) => {
    event.waitUntil(
        caches.open(STATIC_CACHE).then((cache) => cache.addAll(PRECACHE)).then(() => self.skipWaiting()),
    );
});

self.addEventListener('activate', (event) => {
    event.waitUntil(
        caches
            .keys()
            .then((keys) => Promise.all(keys.filter((k) => ![STATIC_CACHE, IMAGE_CACHE].includes(k)).map((k) => caches.delete(k))))
            .then(() => self.clients.claim()),
    );
});

async function trim(cacheName, max) {
    const cache = await caches.open(cacheName);
    const keys = await cache.keys();
    if (keys.length > max) {
        await Promise.all(keys.slice(0, keys.length - max).map((k) => cache.delete(k)));
    }
}

self.addEventListener('fetch', (event) => {
    const { request } = event;
    if (request.method !== 'GET') return; // never touch form posts, Livewire updates or webhooks

    const url = new URL(request.url);
    if (url.origin !== self.location.origin) return;

    // Page loads: always the network. Only if it fails entirely, show the offline page.
    if (request.mode === 'navigate') {
        event.respondWith(fetch(request).catch(() => caches.match(OFFLINE_URL)));
        return;
    }

    // Fingerprinted build assets never change for a given URL: cache first.
    if (url.pathname.startsWith('/build/')) {
        event.respondWith(
            caches.match(request).then(
                (hit) =>
                    hit ||
                    fetch(request).then((response) => {
                        if (response.ok) {
                            const copy = response.clone();
                            caches.open(STATIC_CACHE).then((cache) => cache.put(request, copy));
                        }
                        return response;
                    }),
            ),
        );
        return;
    }

    // Listing photos: show the cached copy immediately, refresh it in the background.
    if (url.pathname.startsWith('/storage/')) {
        event.respondWith(
            caches.open(IMAGE_CACHE).then(async (cache) => {
                const hit = await cache.match(request);
                const refresh = fetch(request)
                    .then((response) => {
                        if (response.ok) {
                            cache.put(request, response.clone()).then(() => trim(IMAGE_CACHE, IMAGE_LIMIT));
                        }
                        return response;
                    })
                    .catch(() => hit);
                return hit || refresh;
            }),
        );
    }
    // Everything else passes straight through to the network.
});
