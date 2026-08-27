/**
 * ============================================================
 *  Sagar Starter's — First-Party Analytics & Live Telemetry
 *  Location: /assets/js/analytics-tracker.js
 * ============================================================
 *  Lightweight, privacy-conscious, async tracking script.
 *  - Real-time heartbeat (every 25s) with instant leave/pagehide detection
 *  - Anonymous first-party cookie (no cross-site tracking)
 *  - sendBeacon with keepalive fetch fallback
 *  - Non-blocking, zero-overhead execution
 * ============================================================
 */
(function() {
    'use strict';

    // ── Guard: Skip if tracking disabled or admin user ───────
    if (window.__ssAnalytics && window.__ssAnalytics.disabled) {
        return;
    }

    // ── Config & Endpoint Resolution ─────────────────────────
    var rawBase = (window.__ssAnalytics && window.__ssAnalytics.baseUrl) || '';
    var BASE_URL = rawBase ? rawBase.replace(/\/+$/, '') : '';
    var TRACK_URL     = BASE_URL + '/api/analytics_track.php';
    var HEARTBEAT_URL = BASE_URL + '/api/analytics_heartbeat.php';
    var COOKIE_NAME   = '_ss_uid';
    var COOKIE_DAYS   = 365;
    var HEARTBEAT_SEC = 25; // 25 seconds for responsive live tracking

    // ── Cookie Helpers ───────────────────────────────────────
    function getCookie(name) {
        var match = document.cookie.match(new RegExp('(?:^|;\\s*)' + name + '=([^;]*)'));
        return match ? decodeURIComponent(match[1]) : null;
    }

    function setCookie(name, value, days) {
        var d = new Date();
        d.setTime(d.getTime() + (days * 86400000));
        var secure = location.protocol === 'https:' ? '; Secure' : '';
        document.cookie = name + '=' + encodeURIComponent(value) +
            '; expires=' + d.toUTCString() +
            '; path=/; SameSite=Lax' + secure;
    }

    // ── Generate Random Hex ID ───────────────────────────────
    function generateId() {
        if (window.crypto && window.crypto.getRandomValues) {
            var arr = new Uint8Array(16);
            window.crypto.getRandomValues(arr);
            return Array.from(arr, function(b) { return b.toString(16).padStart(2, '0'); }).join('');
        }
        var id = '';
        for (var i = 0; i < 32; i++) {
            id += Math.floor(Math.random() * 16).toString(16);
        }
        return id;
    }

    // ── Visitor & Session IDs ────────────────────────────────
    var visitorUid = getCookie(COOKIE_NAME);
    if (!visitorUid || visitorUid.length < 16) {
        visitorUid = generateId();
        setCookie(COOKIE_NAME, visitorUid, COOKIE_DAYS);
    }

    // Session ID: per browser session (sessionStorage)
    var sessionId;
    try {
        sessionId = sessionStorage.getItem('_ss_sid');
        if (!sessionId || sessionId.length < 16) {
            sessionId = generateId();
            sessionStorage.setItem('_ss_sid', sessionId);
        }
    } catch (e) {
        sessionId = generateId();
    }

    // ── Duplicate Protection ─────────────────────────────────
    var sentEvents = {};
    function isDuplicate(eventKey) {
        var now = Date.now();
        if (sentEvents[eventKey] && (now - sentEvents[eventKey]) < 4000) {
            return true;
        }
        sentEvents[eventKey] = now;
        return false;
    }

    // ── Send Analytics Event ─────────────────────────────────
    function sendEvent(data) {
        data.visitor_uid = visitorUid;
        data.session_id  = sessionId;
        data.referrer    = document.referrer || '';

        var json = JSON.stringify(data);
        var sent = false;

        // 1. Try sendBeacon
        if (navigator.sendBeacon) {
            try {
                sent = navigator.sendBeacon(TRACK_URL, new Blob([json], { type: 'application/json' }));
            } catch (e) {
                sent = false;
            }
        }

        // 2. Fallback: fetch with keepalive
        if (!sent && window.fetch) {
            try {
                fetch(TRACK_URL, {
                    method: 'POST',
                    body: json,
                    headers: { 'Content-Type': 'application/json' },
                    keepalive: true
                }).catch(function() {});
            } catch (e) {}
        }
    }

    // ── Heartbeat & Live Telemetry ───────────────────────────
    function sendHeartbeat(action) {
        var act = action || 'heartbeat';
        var payload = JSON.stringify({
            visitor_uid: visitorUid,
            session_id:  sessionId,
            action:      act
        });

        var sent = false;
        if (navigator.sendBeacon) {
            try {
                sent = navigator.sendBeacon(HEARTBEAT_URL, new Blob([payload], { type: 'application/json' }));
            } catch (e) {
                sent = false;
            }
        }

        if (!sent && window.fetch) {
            try {
                fetch(HEARTBEAT_URL, {
                    method: 'POST',
                    body: payload,
                    headers: { 'Content-Type': 'application/json' },
                    keepalive: true
                }).catch(function() {});
            } catch (e) {}
        }
    }

    var heartbeatInterval = null;
    function startHeartbeat() {
        if (heartbeatInterval) return;

        // Periodic heartbeat ping while tab is active and visible
        heartbeatInterval = setInterval(function() {
            if (!document.hidden) {
                sendHeartbeat('heartbeat');
            }
        }, HEARTBEAT_SEC * 1000);

        // Visibility Change Handler
        document.addEventListener('visibilitychange', function() {
            if (document.hidden) {
                // User minimized or switched away from the tab
                sendHeartbeat('leave');
            } else {
                // User came back to the tab — restore active live status immediately
                sendHeartbeat('heartbeat');
            }
        });

        // Tab Close / Page Unload Handlers
        window.addEventListener('pagehide', function() {
            sendHeartbeat('leave');
        });
        window.addEventListener('beforeunload', function() {
            sendHeartbeat('leave');
        });
    }

    // ── Track Page View ──────────────────────────────────────
    function trackPageView() {
        var url = window.location.pathname + window.location.search;
        var key = 'pv:' + url;
        if (isDuplicate(key)) return;

        sendEvent({
            event:      'page_view',
            page_url:   url,
            page_title: document.title || ''
        });
    }

    // ── Track Product View ───────────────────────────────────
    function trackProductView() {
        var el = document.querySelector('[data-analytics-product-id]');
        if (!el) return;

        var productId   = el.getAttribute('data-analytics-product-id');
        var productName = el.getAttribute('data-analytics-product-name') || '';
        if (!productId) return;

        var key = 'prodview:' + productId;
        if (isDuplicate(key)) return;

        var fromSearch = '';
        try {
            var ref = document.referrer;
            if (ref && ref.indexOf('search=') !== -1) {
                var refUrl = new URL(ref);
                fromSearch = refUrl.searchParams.get('search') || '';
            }
        } catch (e) {}

        if (!fromSearch) {
            try {
                fromSearch = sessionStorage.getItem('_ss_last_search') || '';
            } catch (e) {}
        }

        sendEvent({
            event:        'product_view',
            page_url:     window.location.pathname + window.location.search,
            page_title:   document.title || '',
            product_id:   parseInt(productId, 10),
            product_name: productName,
            from_search:  fromSearch
        });
    }

    // ── Track Search ─────────────────────────────────────────
    function trackSearch() {
        var container = document.querySelector('[data-analytics-search]');
        if (!container) return;

        var query       = container.getAttribute('data-analytics-search') || '';
        var resultCount = parseInt(container.getAttribute('data-analytics-results') || '0', 10);

        if (!query || query.length === 0) return;

        var key = 'search:' + query;
        if (isDuplicate(key)) return;

        try {
            sessionStorage.setItem('_ss_last_search', query);
        } catch (e) {}

        sendEvent({
            event:        'search',
            page_url:     window.location.pathname + window.location.search,
            page_title:   document.title || '',
            search_query: query,
            result_count: resultCount
        });
    }

    // ── Initialize ───────────────────────────────────────────
    function init() {
        try {
            trackPageView();
            trackProductView();
            trackSearch();
            startHeartbeat();
        } catch (e) {}
    }

    // ── Non-blocking Idle / Load Initialization ───────────────
    if (document.readyState === 'complete') {
        if ('requestIdleCallback' in window) {
            requestIdleCallback(init, { timeout: 1500 });
        } else {
            setTimeout(init, 100);
        }
    } else {
        window.addEventListener('load', function() {
            if ('requestIdleCallback' in window) {
                requestIdleCallback(init, { timeout: 1500 });
            } else {
                setTimeout(init, 100);
            }
        }, { once: true });
    }

})();
