const CACHE_NAME = 'sagar-store-v2';
const urlsToCache = [
    '/assets/css/style.css',
    '/assets/js/main.js'
];

self.addEventListener('install', event => {
    self.skipWaiting();
    event.waitUntil(
        caches.open(CACHE_NAME)
            .then(cache => {
                return cache.addAll(urlsToCache).catch(err => console.warn('Cache addAll warning:', err));
            })
    );
});

self.addEventListener('activate', event => {
    event.waitUntil(
        caches.keys().then(cacheNames => {
            return Promise.all(
                cacheNames.map(cache => {
                    if (cache !== CACHE_NAME) {
                        return caches.delete(cache);
                    }
                })
            );
        }).then(() => self.clients.claim())
    );
});

self.addEventListener('fetch', event => {
    const request = event.request;
    const acceptHeader = request.headers.get('accept') || '';
    
    // Always use Network-First for HTML/Navigation/PHP requests to ensure live session state
    if (request.mode === 'navigate' || acceptHeader.includes('text/html') || request.url.includes('.php')) {
        event.respondWith(
            fetch(request).catch(() => caches.match(request))
        );
        return;
    }

    // Cache-First for static assets
    event.respondWith(
        caches.match(request).then(response => {
            return response || fetch(request);
        })
    );
});

