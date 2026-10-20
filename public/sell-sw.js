// Service worker for the installable «فروش سریع» admin app (scope /admin/sell).
// Network-only on purpose: stock and prices must always be live, so nothing is
// cached — this file exists only so phones offer «Add to Home screen».
self.addEventListener('install', () => self.skipWaiting());
self.addEventListener('activate', (event) => event.waitUntil(self.clients.claim()));
self.addEventListener('fetch', (event) => event.respondWith(fetch(event.request)));
