/*
 * Roster service worker. Keeps the app installable and fast, never works offline for actions:
 * clock-ins and every other POST go straight to the network, so nothing is stored on the phone.
 */
const VERSION = 'roster-v1';
const SHELL = ['/offline.html', '/icons/icon-192.png', '/manifest.webmanifest'];

self.addEventListener('install', (event) => {
    event.waitUntil(caches.open(VERSION).then((c) => c.addAll(SHELL)).then(() => self.skipWaiting()));
});

self.addEventListener('activate', (event) => {
    event.waitUntil(
        caches.keys().then((keys) => Promise.all(keys.filter((k) => k !== VERSION).map((k) => caches.delete(k)))).then(() => self.clients.claim()),
    );
});

self.addEventListener('fetch', (event) => {
    const req = event.request;
    if (req.method !== 'GET') return; // network only
    const url = new URL(req.url);
    if (url.origin !== self.location.origin) return;

    // Hashed build assets never change: cache first.
    if (url.pathname.startsWith('/build/') || url.pathname.startsWith('/icons/')) {
        event.respondWith(
            caches.match(req).then((hit) => hit || fetch(req).then((res) => {
                const copy = res.clone();
                if (res.ok) caches.open(VERSION).then((c) => c.put(req, copy));
                return res;
            })),
        );
        return;
    }

    // Pages: always the network (data is personal and live); a calm offline page when there is none.
    if (req.mode === 'navigate') {
        event.respondWith(fetch(req).catch(() => caches.match('/offline.html')));
    }
});

self.addEventListener('push', (event) => {
    let data = {};
    try { data = event.data ? event.data.json() : {}; } catch (e) { data = { title: 'Roster', body: event.data && event.data.text() }; }
    event.waitUntil(self.registration.showNotification(data.title || 'Roster', {
        body: data.body || '',
        icon: data.icon || '/icons/icon-192.png',
        badge: '/icons/icon-192.png',
        tag: data.tag,
        data: data.data || {},
    }));
});

self.addEventListener('notificationclick', (event) => {
    event.notification.close();
    const path = (event.notification.data && event.notification.data.url) || '/me/today';
    const target = new URL(path, self.location.origin);
    if (target.origin !== self.location.origin) return;
    event.waitUntil(self.clients.matchAll({ type: 'window', includeUncontrolled: true }).then((wins) => {
        for (const w of wins) {
            if ('focus' in w) { w.navigate(target.href); return w.focus(); }
        }
        return self.clients.openWindow(target.href);
    }));
});
