/**
 * EL-FAITH MICROFINANCE - Progressive Web App Service Worker
 * Version: 2.1.0-PROD
 * Scope: /ELFAITH%20MICROFINANCE/
 * Provides offline navigation, app shell caching, and offline fallback
 */

const CACHE_NAME = 'elfaith-pwa-v2.1';

// Base path detection
const BASE_PATH = self.registration.scope;

// Core static assets to pre-cache on install
const PRECACHE_ASSETS = [
    BASE_PATH + 'offline.html',
    BASE_PATH + 'assets/manifest.json',
    BASE_PATH + 'assets/app.js',
    BASE_PATH + 'assets/icons/icon-192.png',
    BASE_PATH + 'assets/icons/icon-512.png',
    BASE_PATH + 'assets/icons/apple-touch-icon.png',
    BASE_PATH + 'assets/icons/favicon.png',
    'https://cdn.tailwindcss.com',
    'https://unpkg.com/lucide@latest'
];

// App pages to attempt caching during install/first run
const PRECACHE_PAGES = [
    BASE_PATH + 'agent/index.php',
    BASE_PATH + 'agent/collect.php',
    BASE_PATH + 'agent/offline_queue.php',
    BASE_PATH + 'agent/my_customers.php'
];

// 1. Install Event: Pre-cache static assets
self.addEventListener('install', event => {
    event.waitUntil(
        caches.open(CACHE_NAME).then(async cache => {
            console.log('[PWA SW] Pre-caching core app shell & offline assets...');
            // Precache core assets
            try {
                await cache.addAll(PRECACHE_ASSETS);
            } catch (err) {
                console.warn('[PWA SW] Core assets pre-cache partial:', err);
            }
            // Precache initial pages (non-blocking if not logged in)
            for (const page of PRECACHE_PAGES) {
                try {
                    const res = await fetch(page);
                    if (res && res.status === 200) {
                        await cache.put(page, res);
                    }
                } catch (e) {
                    // Ignore pages that require auth during pre-cache
                }
            }
        })
    );
    self.skipWaiting();
});

// 2. Activate Event: Clean up stale caches
self.addEventListener('activate', event => {
    event.waitUntil(
        caches.keys().then(keys => {
            return Promise.all(
                keys.map(key => {
                    if (key !== CACHE_NAME) {
                        console.log('[PWA SW] Removing old cache:', key);
                        return caches.delete(key);
                    }
                })
            );
        }).then(() => self.clients.claim())
    );
});

// 3. Fetch Event: Intelligent routing & caching
self.addEventListener('fetch', event => {
    const req = event.request;
    const url = new URL(req.url);

    // Bypass non-GET requests (e.g. POST to APIs handled by client-side queue)
    if (req.method !== 'GET') {
        return;
    }

    // Bypass backend dynamic APIs or backup downloads to avoid stale caches
    if (url.pathname.includes('/api/') || url.pathname.includes('/backup.php') || url.pathname.includes('/backups/')) {
        return;
    }

    // A. Static CDN Assets & Images: Cache-First strategy
    if (
        url.hostname.includes('cdn.tailwindcss.com') ||
        url.hostname.includes('unpkg.com') ||
        req.destination === 'image' ||
        req.destination === 'font' ||
        req.destination === 'style' ||
        req.destination === 'script'
    ) {
        event.respondWith(
            caches.match(req).then(cachedResponse => {
                if (cachedResponse) {
                    return cachedResponse;
                }
                return fetch(req).then(networkResponse => {
                    if (networkResponse && networkResponse.status === 200) {
                        const copy = networkResponse.clone();
                        caches.open(CACHE_NAME).then(cache => cache.put(req, copy));
                    }
                    return networkResponse;
                }).catch(() => {
                    // Offline fallback for images
                    if (req.destination === 'image') {
                        return caches.match(BASE_PATH + 'assets/icons/icon-192.png');
                    }
                });
            })
        );
        return;
    }

    // B. Navigation & HTML Pages: Network-First with Cache Fallback strategy
    if (req.mode === 'navigate' || req.headers.get('accept')?.includes('text/html')) {
        event.respondWith(
            fetch(req)
                .then(networkResponse => {
                    // Only cache successful OK responses
                    if (networkResponse && networkResponse.status === 200) {
                        const copy = networkResponse.clone();
                        caches.open(CACHE_NAME).then(cache => {
                            cache.put(req, copy);
                        });
                    }
                    return networkResponse;
                })
                .catch(async () => {
                    console.log('[PWA SW] Network failed, looking in cache for:', req.url);
                    const cachedResponse = await caches.match(req);
                    if (cachedResponse) {
                        return cachedResponse;
                    }
                    // If page is not in cache, try exact matching without query parameters
                    const cleanUrl = req.url.split('?')[0];
                    const cleanMatch = await caches.match(cleanUrl);
                    if (cleanMatch) {
                        return cleanMatch;
                    }
                    // Fall back to offline.html
                    console.log('[PWA SW] Serving offline fallback page');
                    return caches.match(BASE_PATH + 'offline.html');
                })
        );
        return;
    }

    // C. Default: Network with Cache fallback
    event.respondWith(
        fetch(req)
            .then(networkResponse => {
                if (networkResponse && networkResponse.status === 200) {
                    const copy = networkResponse.clone();
                    caches.open(CACHE_NAME).then(cache => cache.put(req, copy));
                }
                return networkResponse;
            })
            .catch(() => caches.match(req))
    );
});

