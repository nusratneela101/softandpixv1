/**
 * SoftandPix Service Worker
 * Network-first for API/dynamic, Cache-first for static assets
 */

const CACHE_NAME = 'softandpix-v2';
const STATIC_CACHE = 'softandpix-static-v2';
const OFFLINE_URL = '/public/offline.html';

const STATIC_ASSETS = [
    '/public/offline.html',
    '/public/assets/css/app.css',
    '/public/assets/js/app.js',
    'https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css',
    'https://cdn.jsdelivr.net/npm/bootstrap-icons@1.8.1/font/bootstrap-icons.css',
    'https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js',
];

const DYNAMIC_PATHS = ['/api/', '/admin/', '/client/', '/developer/', '/chat/', '/invoice/'];

// Install event - cache static assets
self.addEventListener('install', (event) => {
    event.waitUntil(
        caches.open(STATIC_CACHE).then((cache) => {
            return cache.addAll(STATIC_ASSETS).catch(() => {
                // Ignore individual failures
            });
        }).then(() => self.skipWaiting())
    );
});

// Activate event - clean old caches
self.addEventListener('activate', (event) => {
    event.waitUntil(
        caches.keys().then((cacheNames) => {
            return Promise.all(
                cacheNames
                    .filter((name) => name !== CACHE_NAME && name !== STATIC_CACHE)
                    .map((name) => caches.delete(name))
            );
        }).then(() => self.clients.claim())
    );
});

// Fetch event - Network-first for dynamic, Cache-first for static
self.addEventListener('fetch', (event) => {
    const { request } = event;
    const url = new URL(request.url);

    // Skip non-GET requests and cross-origin requests — pass them straight to network
    if (request.method !== 'GET' || url.origin !== location.origin) {
        event.respondWith(fetch(request));
        return;
    }

    // API/dynamic paths — network first
    const isDynamic = DYNAMIC_PATHS.some((p) => url.pathname.startsWith(p));
    if (isDynamic) {
        event.respondWith(networkFirstStrategy(request));
        return;
    }

    // Static assets — cache first
    event.respondWith(cacheFirstStrategy(request));
});

async function networkFirstStrategy(request) {
    try {
        const networkResponse = await fetch(request);
        const cache = await caches.open(CACHE_NAME);
        cache.put(request, networkResponse.clone());
        return networkResponse;
    } catch {
        const cached = await caches.match(request);
        if (cached) return cached;
        if (request.headers.get('accept')?.includes('text/html')) {
            return caches.match(OFFLINE_URL);
        }
        return new Response('Offline', { status: 503 });
    }
}

async function cacheFirstStrategy(request) {
    const cached = await caches.match(request);
    if (cached) return cached;
    try {
        const networkResponse = await fetch(request);
        const cache = await caches.open(STATIC_CACHE);
        cache.put(request, networkResponse.clone());
        return networkResponse;
    } catch {
        if (request.headers.get('accept')?.includes('text/html')) {
            return caches.match(OFFLINE_URL);
        }
        return new Response('Offline', { status: 503 });
    }
}

// Background sync for chat messages
self.addEventListener('sync', (event) => {
    if (event.tag === 'chat-sync') {
        event.waitUntil(syncChatMessages());
    }
});

async function syncChatMessages() {
    try {
        const db = await openIDB();
        const messages = await getAllPending(db);
        for (const msg of messages) {
            try {
                await fetch('/api/chat_send.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(msg),
                });
                await deletePending(db, msg.id);
            } catch {}
        }
    } catch {}
}

function openIDB() {
    return new Promise((resolve, reject) => {
        const req = indexedDB.open('softandpix-offline', 1);
        req.onupgradeneeded = (e) => e.target.result.createObjectStore('pending', { keyPath: 'id', autoIncrement: true });
        req.onsuccess = (e) => resolve(e.target.result);
        req.onerror = () => reject(req.error);
    });
}

function getAllPending(db) {
    return new Promise((resolve, reject) => {
        const tx = db.transaction('pending', 'readonly');
        const req = tx.objectStore('pending').getAll();
        req.onsuccess = () => resolve(req.result);
        req.onerror = () => reject(req.error);
    });
}

function deletePending(db, id) {
    return new Promise((resolve, reject) => {
        const tx = db.transaction('pending', 'readwrite');
        const req = tx.objectStore('pending').delete(id);
        req.onsuccess = () => resolve();
        req.onerror = () => reject(req.error);
    });
}

// Push notification support
self.addEventListener('push', (event) => {
    let data = {};
    if (event.data) {
        try { data = event.data.json(); } catch { data = { body: event.data.text() }; }
    }
    const options = {
        body: data.body || '',
        icon: data.icon || '/public/assets/icons/icon-192x192.png',
        badge: '/public/assets/icons/icon-72x72.png',
        tag: data.tag || 'softandpix',
        renotify: !!data.tag,
        data: { url: data.url || '/' },
        vibrate: [100, 50, 100],
        actions: data.actions || [],
    };
    event.waitUntil(
        self.registration.showNotification(data.title || 'SoftandPix', options)
    );
});

self.addEventListener('notificationclick', (event) => {
    event.notification.close();
    const url = event.notification.data?.url || '/';
    event.waitUntil(
        clients.matchAll({ type: 'window', includeUncontrolled: true }).then((windowClients) => {
            // If a window is already open at the target URL, focus it
            for (const client of windowClients) {
                if (client.url === url && 'focus' in client) {
                    return client.focus();
                }
            }
            // Otherwise open a new window
            if (clients.openWindow) {
                return clients.openWindow(url);
            }
        })
    );
});

// Handle subscription expiry/refresh
self.addEventListener('pushsubscriptionchange', (event) => {
    event.waitUntil(
        self.registration.pushManager.subscribe(event.oldSubscription.options)
            .then((newSubscription) => {
                return fetch('/api/push.php?action=subscribe', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        endpoint: newSubscription.endpoint,
                        keys: {
                            p256dh: arrayBufferToBase64(newSubscription.getKey('p256dh')),
                            auth: arrayBufferToBase64(newSubscription.getKey('auth')),
                        },
                    }),
                    credentials: 'same-origin',
                });
            })
            .catch(() => {
                // Subscription renewal failed — user will need to re-subscribe
            })
    );
});

function arrayBufferToBase64(buffer) {
    const bytes = new Uint8Array(buffer);
    return btoa(String.fromCharCode(...bytes))
        .replace(/\+/g, '-')
        .replace(/\//g, '_')
        .replace(/=+$/, '');
}
