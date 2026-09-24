/**
 * Sagar Starter's - Production PWA Service Worker
 * Version: 1.0.0
 * 
 * Safety Rules Enforced:
 * - GET requests only
 * - NEVER cache POST, PUT, DELETE
 * - Dynamic e-commerce HTML uses Network-First -> offline.html fallback only
 * - NEVER serve stale prices, stock, GST, cart, orders, or personal data
 * - Strict Network-Only bypass for /admin/, /api/, /user/, /auth/, /cron/, /backups/,
 *   cart.php, checkout.php, payment gateways, webhooks, and private APIs
 */

const CACHE_VERSION = 'sagar-pwa-v1.0';
const STATIC_CACHE  = `static-${CACHE_VERSION}`;
const IMAGE_CACHE   = `images-${CACHE_VERSION}`;

// Dynamically determine the base scope directory (e.g. '' on root, '/SagarSite' on localhost)
const SCOPE_PATH  = new URL(self.registration.scope).pathname.replace(/\/+$/, '');
const OFFLINE_URL = `${SCOPE_PATH}/offline.html`;

// Pre-cache core shell resources on install
const PRECACHE_ASSETS = [
    OFFLINE_URL,
    `${SCOPE_PATH}/assets/images/icons/icon-192.png`,
    `${SCOPE_PATH}/assets/images/icons/icon-512.png`,
    `${SCOPE_PATH}/assets/images/icons/icon-maskable-192.png`,
    `${SCOPE_PATH}/assets/images/icons/icon-maskable-512.png`
];

// Helper to keep image cache bounded
function limitCacheSize(cacheName, maxItems) {
    caches.open(cacheName).then(cache => {
        cache.keys().then(keys => {
            if (keys.length > maxItems) {
                cache.delete(keys[0]).then(() => limitCacheSize(cacheName, maxItems));
            }
        });
    }).catch(() => {});
}

// ── 1. INSTALL EVENT ──────────────────────────────────────────
self.addEventListener('install', event => {
    self.skipWaiting();
    event.waitUntil(
        caches.open(STATIC_CACHE).then(cache => {
            return cache.addAll(PRECACHE_ASSETS).catch(err => {
                console.warn('[PWA SW] Pre-cache warning:', err);
            });
        })
    );
});

// ── 2. ACTIVATE EVENT (Cache Cleanup) ─────────────────────────
self.addEventListener('activate', event => {
    const validCaches = [STATIC_CACHE, IMAGE_CACHE];
    event.waitUntil(
        caches.keys().then(cacheNames => {
            return Promise.all(
                cacheNames.map(cacheName => {
                    if (!validCaches.includes(cacheName)) {
                        console.log('[PWA SW] Deleting obsolete cache:', cacheName);
                        return caches.delete(cacheName);
                    }
                })
            );
        }).then(() => self.clients.claim())
    );
});

// ── 3. STRICT NETWORK-ONLY BYPASS FILTER ─────────────────────
function shouldBypassServiceWorker(url, request) {
    // Only GET requests can be handled by Service Worker caches
    if (request.method !== 'GET') {
        return true;
    }

    const pathname = url.pathname.toLowerCase();

    // Directory exclusions: admin panel, cron jobs, backups, deployment, APIs, logistics
    const excludedPrefixes = [
        '/admin',
        '/api',
        '/user',
        '/auth',
        '/cron',
        '/backups',
        '/deployment',
        '/courier_module',
        '/shipping_module_src',
        '/tracking_module_src'
    ];
    for (let i = 0; i < excludedPrefixes.length; i++) {
        if (pathname.includes(excludedPrefixes[i])) {
            return true;
        }
    }

    // Exact sensitive & transactional file exclusions
    const excludedFiles = [
        'cart.php',
        'cart_actions.php',
        'checkout.php',
        'recover_cart.php',
        'invoice.php',
        'download.php',
        'my-orders.php',
        'price_list.php',
        'phonepe_payment.php',
        'phonepe-webhook.php',
        'phonepe_success.php',
        'phonepe_failure.php',
        'ajax_auth_check.php',
        'submit_review.php',
        'bharatship_webhook.php',
        'awb_track.php',
        'analytics_track.php',
        'analytics_heartbeat.php'
    ];
    for (let j = 0; j < excludedFiles.length; j++) {
        if (pathname.endsWith('/' + excludedFiles[j]) || pathname === excludedFiles[j]) {
            return true;
        }
    }

    return false;
}

// ── 4. FETCH EVENT ────────────────────────────────────────────
self.addEventListener('fetch', event => {
    const request = event.request;
    const url = new URL(request.url);

    // Bypass check: send immediately to network without touching cache
    if (shouldBypassServiceWorker(url, request)) {
        return; // Native browser handling
    }

    const acceptHeader = request.headers.get('accept') || '';

    // A) HTML / Navigation requests: Network-First -> offline.html fallback ONLY
    if (request.mode === 'navigate' || acceptHeader.includes('text/html') || url.pathname.endsWith('.php')) {
        event.respondWith(
            fetch(request).catch(() => {
                return caches.match(OFFLINE_URL);
            })
        );
        return;
    }

    // B) Static Assets (CSS, JS, Fonts) - Stale-While-Revalidate
    const isStaticAsset = url.pathname.match(/\.(css|js|woff|woff2|ttf|eot)$/i) ||
                          url.hostname.includes('fonts.googleapis.com') ||
                          url.hostname.includes('fonts.gstatic.com') ||
                          url.hostname.includes('cdnjs.cloudflare.com');

    if (isStaticAsset) {
        event.respondWith(
            caches.open(STATIC_CACHE).then(cache => {
                return cache.match(request).then(cachedResponse => {
                    const fetchPromise = fetch(request).then(networkResponse => {
                        if (networkResponse && networkResponse.status === 200) {
                            cache.put(request, networkResponse.clone());
                        }
                        return networkResponse;
                    }).catch(() => cachedResponse);

                    return cachedResponse || fetchPromise;
                });
            })
        );
        return;
    }

    // C) Images - Cache-First with size bounding
    const isImage = request.destination === 'image' || url.pathname.match(/\.(png|jpg|jpeg|gif|webp|svg|ico)$/i);
    if (isImage) {
        event.respondWith(
            caches.open(IMAGE_CACHE).then(cache => {
                return cache.match(request).then(cachedResponse => {
                    if (cachedResponse) {
                        return cachedResponse;
                    }
                    return fetch(request).then(networkResponse => {
                        if (networkResponse && networkResponse.status === 200) {
                            cache.put(request, networkResponse.clone());
                            limitCacheSize(IMAGE_CACHE, 60);
                        }
                        return networkResponse;
                    }).catch(() => {
                        // Return nothing or cached placeholder if available
                    });
                });
            })
        );
        return;
    }

    // Default: Native network fetch
    event.respondWith(
        fetch(request).catch(() => caches.match(request))
    );
});

