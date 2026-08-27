/**
 * ============================================================
 *  Sagar Starter's — First-Party Analytics Tracker
 *  Location: /assets/js/analytics-tracker.js
 * ============================================================
 *  Lightweight, privacy-conscious, async tracking script.
 *  - Anonymous first-party cookie (no cross-site tracking)
 *  - sendBeacon for fire-and-forget delivery
 *  - Duplicate event protection
 *  - Never blocks page rendering
 * ============================================================
 */
(function() {
    'use strict';

    // ── Config ───────────────────────────────────────────────
    var BASE_URL = (window.__ssAnalytics && window.__ssAnalytics.baseUrl) || '';
    var TRACK_URL     = BASE_URL + '/api/analytics_track.php';
    var HEARTBEAT_URL = BASE_URL + '/api/analytics_heartbeat.php';
    var COOKIE_NAME   = '_ss_uid';
    var COOKIE_DAYS   = 365;
    var HEARTBEAT_SEC = 60;

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
        // Fallback
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
        if (sentEvents[eventKey] && (now - sentEvents[eventKey]) < 5000) {
            return true;
        }
        sentEvents[eventKey] = now;
        return false;
    }

    // ── Send Event ───────────────────────────────────────────
    function sendEvent(data) {
        data.visitor_uid = visitorUid;
        data.session_id  = sessionId;
        data.referrer    = document.referrer || '';

        var json = JSON.stringify(data);

        // Prefer sendBeacon (non-blocking, works on page unload)
        if (navigator.sendBeacon) {
            try {
                navigator.sendBeacon(TRACK_URL, new Blob([json], {type: 'application/json'}));
                return;
            } catch (e) {}
        }

        // Fallback: fetch with keepalive
        try {
            fetch(TRACK_URL, {
                method: 'POST',
                body: json,
                headers: {'Content-Type': 'application/json'},
                keepalive: true
            }).catch(function() {});
        } catch (e) {}
    }

    // ── Get Search Context ───────────────────────────────────
    function getSearchFromUrl() {
        try {
            var params = new URLSearchParams(window.location.search);
            return params.get('search') || '';
        } catch (e) {
            return '';
        }
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

        // Check if this came from a search
        var fromSearch = '';
        try {
            var ref = document.referrer;
            if (ref && ref.indexOf('search=') !== -1) {
                var refUrl = new URL(ref);
                fromSearch = refUrl.searchParams.get('search') || '';
            }
        } catch (e) {}

        // Also check sessionStorage for last search
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

        // Store last search for search-to-product tracking
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

    // ── Heartbeat (Live Visitors) ────────────────────────────
    var heartbeatInterval = null;
    function startHeartbeat() {
        if (heartbeatInterval) return;

        function beat() {
            if (document.hidden) return; // Don't beat when tab is hidden
            var data = JSON.stringify({
                visitor_uid: visitorUid,
                session_id: sessionId
            });
            if (navigator.sendBeacon) {
                try {
                    navigator.sendBeacon(HEARTBEAT_URL, new Blob([data], {type: 'application/json'}));
                } catch (e) {}
            }
        }

        heartbeatInterval = setInterval(beat, HEARTBEAT_SEC * 1000);

        // Stop heartbeat when tab is hidden, resume when visible
        document.addEventListener('visibilitychange', function() {
            if (document.hidden) {
                // Send one last beat
                beat();
            }
        });
    }

    // ── Initialize ───────────────────────────────────────────
    function init() {
        try {
            trackPageView();
            trackProductView();
            trackSearch();
            startHeartbeat();
        } catch (e) {
            // Analytics must never break the page
        }
    }

    // Run after DOM is ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }

})();
