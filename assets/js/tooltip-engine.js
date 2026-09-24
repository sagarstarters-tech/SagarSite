/**
 * Universal Contextual Hover Tooltip Engine (Vanilla JS)
 * High performance event-delegated tooltip with smart auto-detection & collision avoidance.
 * Covers 100% of website buttons, links, navigation menus, and action controls.
 * Zero external dependencies.
 */
(function () {
    'use strict';

    // Pure touch device check: only disable tooltips if the device has NO hover support
    const isPureTouchDevice = window.matchMedia('(hover: none) and (pointer: coarse)').matches;
    if (isPureTouchDevice) {
        return; // Preserve native mobile touch behavior on pure phones/tablets
    }

    // Singleton tooltip DOM element
    let tooltipEl = null;
    let activeTarget = null;
    let showTimeout = null;
    const SHOW_DELAY = 90; // ms delay before showing for smooth responsiveness

    // ── Icon-to-Context Mapping ────────────────────────────────
    const ICON_CONTEXT_MAP = [
        { regex: /fa-(?:home)/i, text: 'Return to store homepage', icon: 'fa-home' },
        { regex: /fa-(?:trash|trash-can|trash-alt)/i, text: 'Delete / Remove item', icon: 'fa-trash-alt' },
        { regex: /fa-(?:heart|heart-circle-plus)/i, text: 'Add to Wishlist / Saved favorites', icon: 'fa-heart' },
        { regex: /fa-(?:cart-plus)/i, text: 'Add this product to your shopping cart', icon: 'fa-cart-plus' },
        { regex: /fa-(?:shopping-cart|shopping-bag|basket-shopping|cart-shopping|bag-shopping)/i, text: 'View items in your shopping cart', icon: 'fa-shopping-cart' },
        { regex: /fa-(?:pencil|edit|pen-to-square)/i, text: 'Edit / Modify details', icon: 'fa-pencil-alt' },
        { regex: /fa-eye/i, text: 'Quick preview & product details', icon: 'fa-eye' },
        { regex: /fa-search/i, text: 'Search products & categories', icon: 'fa-search' },
        { regex: /fa-(?:share|share-alt|share-nodes)/i, text: 'Share with friends on social media', icon: 'fa-share-alt' },
        { regex: /fa-whatsapp/i, text: 'Chat directly with our support team on WhatsApp', icon: 'fa-whatsapp' },
        { regex: /fa-(?:download|file-arrow-down|file-invoice)/i, text: 'Download official file or invoice', icon: 'fa-download' },
        { regex: /fa-print/i, text: 'Print invoice or document', icon: 'fa-print' },
        { regex: /fa-(?:copy|clipboard)/i, text: 'Copy to clipboard', icon: 'fa-copy' },
        { regex: /fa-filter/i, text: 'Filter & refine product list', icon: 'fa-filter' },
        { regex: /fa-(?:sliders|gear|cog|cogs)/i, text: 'Settings & configurations', icon: 'fa-cog' },
        { regex: /fa-(?:moon|sun|adjust)/i, text: 'Toggle Dark / Light theme', icon: 'fa-adjust' },
        { regex: /fa-(?:globe|language)/i, text: 'Change website display language', icon: 'fa-globe' },
        { regex: /fa-(?:user|user-circle|user-gear|user-shield)/i, text: 'My Account & Profile settings', icon: 'fa-user' },
        { regex: /fa-(?:sign-out-alt|arrow-right-from-bracket)/i, text: 'Log out securely', icon: 'fa-sign-out-alt' },
        { regex: /fa-(?:sign-in-alt|arrow-right-to-bracket)/i, text: 'Log in to your customer account', icon: 'fa-sign-in-alt' },
        { regex: /fa-bell/i, text: 'View notifications & alerts', icon: 'fa-bell' },
        { regex: /fa-(?:arrow-left|chevron-left)/i, text: 'Go back to previous page', icon: 'fa-arrow-left' },
        { regex: /fa-(?:arrow-right|chevron-right)/i, text: 'Proceed / Next step', icon: 'fa-arrow-right' },
        { regex: /fa-(?:sync|rotate|arrows-rotate)/i, text: 'Refresh data', icon: 'fa-sync' },
        { regex: /fa-plus/i, text: 'Add new item / Increase quantity', icon: 'fa-plus' },
        { regex: /fa-minus/i, text: 'Decrease quantity', icon: 'fa-minus' },
        { regex: /fa-(?:lock|shield-halved|shield)/i, text: '100% Safe & Secure Encryption', icon: 'fa-shield-alt' },
        { regex: /fa-(?:truck|truck-fast)/i, text: 'Track order shipping & delivery', icon: 'fa-truck' },
        { regex: /fa-(?:box|boxes-stacked|box-open)/i, text: 'View your orders & shipments', icon: 'fa-box-open' },
        { regex: /fa-(?:phone|phone-flip)/i, text: 'Call our customer helpline', icon: 'fa-phone' },
        { regex: /fa-envelope/i, text: 'Send an email to support', icon: 'fa-envelope' },
        { regex: /fa-bars/i, text: 'Toggle navigation menu', icon: 'fa-bars' },
        { regex: /fa-(?:times|xmark|close)/i, text: 'Close window / Dismiss', icon: 'fa-times' },
        { regex: /fa-(?:calendar|calendar-alt|calendar-days)/i, text: 'View calendar / dates', icon: 'fa-calendar-alt' },
        { regex: /fa-(?:info-circle|circle-info|question-circle|circle-question)/i, text: 'More information & help', icon: 'fa-info-circle' },
        { regex: /fa-(?:play)/i, text: 'Start / Run process', icon: 'fa-play' },
        { regex: /fa-(?:pause)/i, text: 'Pause process', icon: 'fa-pause' },
        { regex: /fa-(?:stop)/i, text: 'Stop process', icon: 'fa-stop' },
        { regex: /fa-(?:clone)/i, text: 'Duplicate / Clone item', icon: 'fa-clone' },
        { regex: /fa-(?:file-pdf|file-lines|file-csv|file-excel)/i, text: 'Download or view document', icon: 'fa-file-alt' },
        { regex: /fa-(?:check|check-circle|circle-check)/i, text: 'Confirm / Completed', icon: 'fa-check' },
        { regex: /fa-(?:bolt|lightning)/i, text: 'Instant action', icon: 'fa-bolt' },
        { regex: /fa-(?:chart-line|chart-bar|chart-pie)/i, text: 'View analytics & reports', icon: 'fa-chart-line' },
        { regex: /fa-(?:camera|image|images)/i, text: 'View or upload images', icon: 'fa-image' },
        { regex: /fa-(?:qrcode)/i, text: 'Scan QR code', icon: 'fa-qrcode' },
        { regex: /fa-(?:external-link|arrow-up-right-from-square)/i, text: 'Open in new window', icon: 'fa-external-link-alt' }
    ];

    // ── URL / HREF Semantic Mapping (for icon-only links) ─────
    const HREF_CONTEXT_MAP = [
        { match: /(?:\/index\.php|\/)$/i, text: 'Return to store homepage' },
        { match: /\/shop\.php/i, text: 'Browse catalog of products' },
        { match: /(?:about\.php|slug=about)/i, text: 'Learn about our company' },
        { match: /(?:contact\.php|slug=contact)/i, text: 'Get in touch with customer support' },
        { match: /(?:track_order\.php|\/track)/i, text: 'Check real-time delivery and shipping status' },
        { match: /\/cart\.php/i, text: 'View and manage items in your shopping cart' },
        { match: /\/checkout\.php/i, text: 'Proceed to safe and secure checkout' },
        { match: /\/wishlist\.php/i, text: 'View products you have saved for later' },
        { match: /\/profile\.php/i, text: 'Manage profile details, passwords, and addresses' },
        { match: /(?:\/my-orders\.php|\/user\/orders\.php|\/orders\.php)/i, text: 'View your order history & invoices' },
        { match: /\/login\.php/i, text: 'Sign in to your account' },
        { match: /\/register\.php/i, text: 'Create a new customer account' },
        { match: /action=logout/i, text: 'Securely log out of your account' },
        { match: /\/admin\//i, text: 'Open admin management dashboard' },
        { match: /(?:wa\.me|whatsapp\.com)/i, text: 'Chat directly with our support team on WhatsApp' },
        { match: /^mailto:/i, text: 'Send an email to our support team' },
        { match: /^tel:/i, text: 'Call our customer support helpline' }
    ];

    /**
     * Initializes the single tooltip DOM container
     */
    function initTooltipDOM() {
        if (tooltipEl) return;
        tooltipEl = document.createElement('div');
        tooltipEl.className = 'ss-tooltip-box';
        tooltipEl.setAttribute('role', 'tooltip');
        tooltipEl.setAttribute('aria-hidden', 'true');
        document.body.appendChild(tooltipEl);
    }

    /**
     * Helper to detect if an element is an icon or contains an icon
     */
    function hasIcon(el) {
        if (!el || el.nodeType !== 1) return false;

        const tag = el.tagName ? el.tagName.toLowerCase() : '';
        if (tag === 'i' || tag === 'svg') return true;

        if (el.matches && (
            el.matches('.fa, .fas, .far, .fab, .fal, .fad, [class*="fa-"], .bi, [class*="bi-"], .material-icons, .material-symbols-outlined, .btn-close, .close, .action-icon, .btn-icon, .nav-icon')
        )) {
            return true;
        }

        if (el.querySelector && el.querySelector('i, svg, .btn-close, [class*="fa-"], [class*="bi-"], .material-icons, .material-symbols-outlined, .action-icon, .btn-icon, img.icon, img[src*="icon"]')) {
            return true;
        }

        return false;
    }

    /**
     * Helper to determine whether an element has visible textual label / anchor text.
     * Excludes icons, badge counters/numbers, currency symbols, and visually-hidden accessibility text.
     */
    function hasVisibleTextLabel(el) {
        if (!el || el.nodeType !== 1) return false;

        const rawText = el.textContent || '';
        if (!rawText.trim()) return false;

        // Clone the element to safely inspect its contents without mutating the live DOM
        const clone = el.cloneNode(true);

        // Selectors for elements that do not represent button labels or link text
        const nonLabelSelectors = [
            'i',
            'svg',
            'img.icon',
            'img[src*="icon"]',
            '.badge',
            '.badge-pill',
            '.rounded-pill',
            '.cart-count',
            '.count',
            '.counter',
            '.badge-counter',
            '[class*="badge"]',
            '.visually-hidden',
            '.sr-only',
            '[aria-hidden="true"]',
            'script',
            'style'
        ];

        try {
            const elementsToRemove = clone.querySelectorAll(nonLabelSelectors.join(','));
            for (let i = 0; i < elementsToRemove.length; i++) {
                elementsToRemove[i].remove();
            }
        } catch (e) {
            // Fallback safety
        }

        // Get text without whitespace, symbols, currency, and numbers
        let remaining = (clone.textContent || '')
            .replace(/[\s\u00A0\u200B-\u200D\uFEFF]+/g, '');

        // If the remaining text only consists of numbers, currency symbols, or punctuation (e.g. cart total "₹500", "2", "(0)")
        // it is data/counter, NOT a textual button/link label
        const textWithoutData = remaining.replace(/[0-9₹$,.()\/+\-%\s]/g, '');

        return textWithoutData.length > 0;
    }

    /**
     * Determines whether an element qualifies as icon-only:
     * Has an icon AND has NO button label, link text, or visible text.
     */
    function isIconOnlyElement(el) {
        if (!el || el.nodeType !== 1) return false;
        return hasIcon(el) && !hasVisibleTextLabel(el);
    }

    /**
     * Determines whether an element is genuinely clickable / interactive.
     * Strictly verifies that the element is an actionable button, link, form trigger,
     * or interactive widget. Non-clickable items (stat cards, badges, headings, display icons) return false.
     */
    function isClickableElement(el) {
        if (!el || el.nodeType !== 1) return false;

        // Elements with explicit custom tooltip or title attribute are always interactive candidates
        if (el.hasAttribute('data-tooltip') || el.hasAttribute('data-hint') || 
            el.hasAttribute('title') || el.hasAttribute('data-ss-title')) {
            return true;
        }

        const tag = el.tagName ? el.tagName.toLowerCase() : '';

        // Buttons (unless disabled)
        if (tag === 'button') {
            return !el.disabled;
        }

        // Links
        if (tag === 'a') {
            const href = el.getAttribute('href');
            if (href && href !== '#' && href !== 'javascript:void(0)' && href !== 'javascript:;') {
                return true;
            }
            if (el.hasAttribute('onclick') || el.hasAttribute('data-bs-toggle') || el.hasAttribute('data-mdb-toggle')) {
                return true;
            }
        }

        // Form interactive controls
        if (tag === 'input') {
            const type = (el.getAttribute('type') || '').toLowerCase();
            return ['button', 'submit', 'reset', 'image'].includes(type) && !el.disabled;
        }

        // ARIA interactive roles
        const role = (el.getAttribute('role') || '').toLowerCase();
        if (['button', 'link', 'tab', 'menuitem', 'checkbox', 'switch'].includes(role)) {
            return true;
        }

        // Click handlers and framework toggles
        if (el.hasAttribute('onclick') || 
            el.hasAttribute('data-bs-toggle') || 
            el.hasAttribute('data-mdb-toggle') || 
            el.hasAttribute('data-bs-target') || 
            el.hasAttribute('data-mdb-target') || 
            el.hasAttribute('data-action')) {
            return true;
        }

        // Recognized interactive classes
        if (el.matches && el.matches(
            '.btn, .btn-act, .btn-close, .action-icon, .btn-icon, .theme-toggle, .language-btn, .nav-link, .dropdown-item, .bottom-nav-item, #whatsapp-link, .social-icon-circle, .selector-pill-btn'
        )) {
            return true;
        }

        return false;
    }

    /**
     * Finds the nearest target element eligible for a tooltip.
     * STRICT RULES:
     * 1. If an element or icon explicitly has [data-tooltip], [data-hint], or [title], it qualifies.
     * 2. Otherwise, tooltips ONLY appear on CLICKABLE elements (button, link, onclick, etc.).
     * 3. Clickable elements with visible text labels do NOT show tooltips (user can already read text).
     * 4. Non-clickable icons (stat cards, info badges, decorative icons) NEVER show a tooltip!
     */
    function getInteractiveTarget(target) {
        if (!target || target === document.body || target === document.documentElement) return null;

        // Skip ignored elements or elements explicitly asking for no tooltip
        if (target.closest('[data-no-tooltip], .no-tooltip, .no-custom-tooltip')) return null;

        // 1. Explicit custom tooltip or title attribute on target or nearby container
        const explicitTooltipEl = target.closest('[data-tooltip], [data-hint], [title], [data-ss-title]');
        if (explicitTooltipEl) {
            // Never trigger tooltips on huge document containers or table rows
            if (explicitTooltipEl === document.body || explicitTooltipEl === document.documentElement) return null;
            return explicitTooltipEl;
        }

        // 2. Check if target is inside a CLICKABLE container (button, link, action control)
        const clickableContainer = target.closest(
            'button, a, [role="button"], [role="link"], input[type="submit"], input[type="button"], input[type="reset"], .btn, .btn-act, .btn-close, .theme-toggle, .language-btn, .action-icon, .btn-icon, .nav-link, .dropdown-item, .dropdown-toggle, .bottom-nav-item, #whatsapp-link, .social-icon-circle, .selector-pill-btn, [onclick], [data-bs-toggle], [data-mdb-toggle]'
        );

        if (clickableContainer) {
            // Must be genuinely clickable (not disabled, not empty)
            if (!isClickableElement(clickableContainer)) {
                return null;
            }

            // STRICT RULE: If the clickable container has visible text (e.g. "Add Product"),
            // DO NOT show tooltip! Only show tooltip if it is strictly icon-only
            if (isIconOnlyElement(clickableContainer)) {
                return clickableContainer;
            }
            return null;
        }

        // 3. Standalone element:
        // Since it is NOT inside a clickable container and has NO explicit tooltip attribute,
        // it is strictly NON-CLICKABLE (e.g. stat card icon, heading icon, decorative badge).
        // NEVER show a tooltip!
        return null;
    }

    /**
     * Resolves the tooltip message and optional icon for an eligible element
     */
    function resolveTooltipContent(el) {
        if (!el) return null;

        // Priority 1: Explicit data-tooltip / data-hint attribute
        const explicitTooltip = el.getAttribute('data-tooltip') || el.getAttribute('data-hint');
        if (explicitTooltip && explicitTooltip.trim()) {
            return { text: explicitTooltip.trim(), icon: null };
        }

        // Priority 2: Title attribute on element or on child icon
        let title = el.getAttribute('title');
        let titleOwner = el;
        if (!title) {
            const childWithTitle = el.querySelector('[title]');
            if (childWithTitle) {
                title = childWithTitle.getAttribute('title');
                titleOwner = childWithTitle;
            }
        }
        if (title && title.trim()) {
            titleOwner.setAttribute('data-ss-title', title.trim());
            titleOwner.removeAttribute('title'); // Prevent native browser tooltip overlap
            return { text: title.trim(), icon: null };
        }

        const cachedTitle = el.getAttribute('data-ss-title') || (el.querySelector('[data-ss-title]') ? el.querySelector('[data-ss-title]').getAttribute('data-ss-title') : null);
        if (cachedTitle && cachedTitle.trim()) {
            return { text: cachedTitle.trim(), icon: null };
        }

        // Priority 3: Informative aria-label attribute
        const ariaLabel = el.getAttribute('aria-label') || (el.querySelector('[aria-label]') ? el.querySelector('[aria-label]').getAttribute('aria-label') : null);
        if (ariaLabel && ariaLabel.trim() && ariaLabel.length > 2 && !/^(?:button|toggle|menu|icon)$/i.test(ariaLabel.trim())) {
            return { text: ariaLabel.trim(), icon: null };
        }

        // Priority 4: Close button special case
        if (el.matches && (el.matches('.btn-close, .close') || el.querySelector('.btn-close, .close'))) {
            return { text: 'Close', icon: 'fa-times' };
        }

        // Priority 5: Icon-based intelligent detection (only for verified clickable icon buttons)
        const iconEl = (el.tagName && (el.tagName.toLowerCase() === 'i' || el.tagName.toLowerCase() === 'svg')) ? el : el.querySelector('i, svg, [class*="fa-"], [class*="bi-"], .material-icons');
        if (iconEl) {
            const iconClass = (iconEl.className || '') + ' ' + (iconEl.getAttribute('data-icon') || '');
            for (let i = 0; i < ICON_CONTEXT_MAP.length; i++) {
                if (ICON_CONTEXT_MAP[i].regex.test(iconClass)) {
                    return { text: ICON_CONTEXT_MAP[i].text, icon: ICON_CONTEXT_MAP[i].icon };
                }
            }
        }

        // Priority 6: Href-based matching for icon-only links
        const href = el.getAttribute('href') || '';
        if (href && href !== '#' && !href.startsWith('javascript:')) {
            for (let i = 0; i < HREF_CONTEXT_MAP.length; i++) {
                if (HREF_CONTEXT_MAP[i].match.test(href)) {
                    return { text: HREF_CONTEXT_MAP[i].text, icon: null };
                }
            }
        }

        // DO NOT show generic 'Action' fallback. If purpose is unknown, show no tooltip.
        return null;
    }

    /**
     * Positions the tooltip accurately with boundary collision avoidance
     */
    function positionTooltip(el, positionPreference) {
        if (!tooltipEl) return;

        const targetRect = el.getBoundingClientRect();
        const tooltipRect = tooltipEl.getBoundingClientRect();
        const padding = 12;
        const arrowOffset = 8;

        let placement = positionPreference || el.getAttribute('data-tooltip-pos') || 'top';

        // Auto collision check: if top overflows window, flip to bottom
        if (placement === 'top' && targetRect.top - tooltipRect.height - arrowOffset < padding) {
            placement = 'bottom';
        } else if (placement === 'bottom' && targetRect.bottom + tooltipRect.height + arrowOffset > window.innerHeight - padding) {
            placement = 'top';
        }

        let top = 0;
        let left = 0;

        if (placement === 'top') {
            top = targetRect.top - tooltipRect.height - arrowOffset;
            left = targetRect.left + (targetRect.width / 2) - (tooltipRect.width / 2);
        } else if (placement === 'bottom') {
            top = targetRect.bottom + arrowOffset;
            left = targetRect.left + (targetRect.width / 2) - (tooltipRect.width / 2);
        } else if (placement === 'left') {
            top = targetRect.top + (targetRect.height / 2) - (tooltipRect.height / 2);
            left = targetRect.left - tooltipRect.width - arrowOffset;
            if (left < padding) placement = 'bottom'; // flip if offscreen
        } else if (placement === 'right') {
            top = targetRect.top + (targetRect.height / 2) - (tooltipRect.height / 2);
            left = targetRect.right + arrowOffset;
            if (left + tooltipRect.width > window.innerWidth - padding) placement = 'bottom';
        }

        // Recalculate if flipped to bottom
        if (placement === 'bottom' && top < targetRect.bottom) {
            top = targetRect.bottom + arrowOffset;
            left = targetRect.left + (targetRect.width / 2) - (tooltipRect.width / 2);
        }

        // Clamp horizontal position so tooltip stays cleanly within viewport
        const maxLeft = window.innerWidth - tooltipRect.width - padding;
        left = Math.max(padding, Math.min(left, maxLeft));

        // Apply coordinates
        tooltipEl.style.top = `${Math.round(top)}px`;
        tooltipEl.style.left = `${Math.round(left)}px`;

        // Reset and apply direction classes
        tooltipEl.classList.remove('pos-top', 'pos-bottom', 'pos-left', 'pos-right');
        tooltipEl.classList.add(`pos-${placement}`);
    }

    /**
     * Show tooltip for element
     */
    function show(el) {
        initTooltipDOM();
        const content = resolveTooltipContent(el);
        if (!content || !content.text) {
            hide();
            return;
        }

        activeTarget = el;

        // Construct HTML content with optional icon
        let innerHTML = '';
        if (content.icon) {
            innerHTML += `<i class="fas ${content.icon} ss-tooltip-icon"></i>`;
        }
        innerHTML += `<span>${escapeHTML(content.text)}</span>`;
        tooltipEl.innerHTML = innerHTML;

        // Position & Display
        positionTooltip(el);
        tooltipEl.classList.add('ss-tooltip-show');
        tooltipEl.setAttribute('aria-hidden', 'false');
    }

    /**
     * Hide active tooltip
     */
    function hide() {
        clearTimeout(showTimeout);
        if (activeTarget) {
            if (activeTarget.hasAttribute('data-ss-title')) {
                const originalTitle = activeTarget.getAttribute('data-ss-title');
                if (originalTitle) {
                    activeTarget.setAttribute('title', originalTitle);
                }
            }
            const childrenWithTitle = activeTarget.querySelectorAll('[data-ss-title]');
            for (let i = 0; i < childrenWithTitle.length; i++) {
                const childOrigTitle = childrenWithTitle[i].getAttribute('data-ss-title');
                if (childOrigTitle) {
                    childrenWithTitle[i].setAttribute('title', childOrigTitle);
                }
            }
        }
        activeTarget = null;
        if (tooltipEl) {
            tooltipEl.classList.remove('ss-tooltip-show');
            tooltipEl.setAttribute('aria-hidden', 'true');
        }
    }

    /**
     * Helper to escape HTML characters in text
     */
    function escapeHTML(str) {
        return str.replace(/[&<>'"]/g, function (tag) {
            const chars = {
                '&': '&amp;',
                '<': '&lt;',
                '>': '&gt;',
                "'": '&#39;',
                '"': '&quot;'
            };
            return chars[tag] || tag;
        });
    }

    // Event Delegation: Mouseover / Focusin
    document.addEventListener('mouseover', function (e) {
        const target = getInteractiveTarget(e.target);
        if (!target) {
            hide();
            return;
        }
        if (target === activeTarget) return;

        clearTimeout(showTimeout);
        showTimeout = setTimeout(function () {
            show(target);
        }, SHOW_DELAY);
    }, { passive: true });

    // Event Delegation: Mouseout / Focusout
    document.addEventListener('mouseout', function (e) {
        if (!activeTarget) return;
        const toElement = e.relatedTarget;
        if (toElement && activeTarget.contains(toElement)) return;
        hide();
    }, { passive: true });

    // Keyboard Accessibility: Focus in / out
    document.addEventListener('focusin', function (e) {
        const target = getInteractiveTarget(e.target);
        if (target) {
            clearTimeout(showTimeout);
            show(target);
        }
    }, { passive: true });

    document.addEventListener('focusout', function () {
        hide();
    }, { passive: true });

    // Hide tooltip when scrolling or clicking
    window.addEventListener('scroll', hide, { passive: true });
    document.addEventListener('click', hide, { passive: true });

    // Export minimal API for external calls if needed
    window.SagarTooltip = {
        show: show,
        hide: hide,
        refresh: function () {
            if (activeTarget) positionTooltip(activeTarget);
        }
    };

})();
